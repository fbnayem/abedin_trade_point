// Fetch free-licence product photos at build time.
//
// For each product in content/products.json this finds a photo on Wikimedia
// Commons (falling back to Openverse), resizes it to a square JPEG in
// public/img/products/<slug>.jpg and records the author and licence in
// content/generated/image-credits.json, which the site shows on
// /image-credits/ and under each product photo.
//
// - content/image-overrides.json pins a specific Commons file for a product
//   ({"slug": {"file": "File:Name.jpg"}}) or hides a photo ({"slug": {"skip": true}}).
// - Downloaded photos are cached in .next/cache/atp-images so later builds
//   reuse the same picture instead of searching again.
// - Real product photos go in public/img/own/<slug>.jpg and always win.
// - This script never fails the build: a product without a photo shows a
//   neutral illustration instead.

import fs from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const ROOT = path.resolve(import.meta.dirname, "..");
const OUT_DIR = path.join(ROOT, "public/img/products");
const OWN_DIR = path.join(ROOT, "public/img/own");
const GEN_DIR = path.join(ROOT, "content/generated");
const CACHE_DIR = path.join(ROOT, ".next/cache/atp-images");
const CACHE_FILE = path.join(CACHE_DIR, "manifest.json");
const UA = "AbedinTradePointCatalogue/2.0 (website build; product photo lookup)";
const SIZE = 900;
const BLOCK = /\b(logo|map|diagram|flag|coat of arms|icon|chart|poster|screenshot|svg|stamp|sign|graffiti|cartoon|drawing|illustration|patent|advert|museum|painting|portrait of|statue|banknote|coin|text|document|page|book cover|album)\b/i;

const products = JSON.parse(await fs.readFile(path.join(ROOT, "content/products.json"), "utf8"));
const overrides = JSON.parse(await fs.readFile(path.join(ROOT, "content/image-overrides.json"), "utf8").catch(() => "{}"));
const cache = JSON.parse(await fs.readFile(CACHE_FILE, "utf8").catch(() => "{}"));
await fs.mkdir(OUT_DIR, { recursive: true });
await fs.mkdir(GEN_DIR, { recursive: true });
await fs.mkdir(CACHE_DIR, { recursive: true });

const credits = {};
const used = new Set();
const stripHtml = (s = "") => s.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();

async function get(url, type = "json", tries = 3) {
  for (let i = 0; i < tries; i++) {
    try {
      const res = await fetch(url, { headers: { "User-Agent": UA, Accept: type === "json" ? "application/json" : "image/*" }, signal: AbortSignal.timeout(25000) });
      if (res.status === 429 || res.status >= 500) throw new Error(`HTTP ${res.status}`);
      if (!res.ok) return null;
      return type === "json" ? await res.json() : Buffer.from(await res.arrayBuffer());
    } catch (e) {
      if (i === tries - 1) return null;
      await new Promise((r) => setTimeout(r, 1500 * (i + 1)));
    }
  }
  return null;
}

function commonsCandidate(page) {
  const ii = page.imageinfo?.[0];
  if (!ii) return null;
  const meta = ii.extmetadata || {};
  return {
    title: page.title,
    url: ii.thumburl || ii.url,
    width: ii.width,
    height: ii.height,
    mime: ii.mime,
    author: stripHtml(meta.Artist?.value) || "Unknown author",
    license: stripHtml(meta.LicenseShortName?.value) || "See source",
    licenseUrl: meta.LicenseUrl?.value || "",
    source: ii.descriptionurl,
    via: "Wikimedia Commons",
    index: page.index ?? 99,
  };
}

const IIPROP = "prop=imageinfo&iiprop=url|size|mime|extmetadata&iiurlwidth=1200&iiextmetadatafilter=Artist|LicenseShortName|LicenseUrl";

async function commonsByTitle(title) {
  const url = `https://commons.wikimedia.org/w/api.php?action=query&format=json&titles=${encodeURIComponent(title)}&${IIPROP}`;
  const data = await get(url);
  const page = data && Object.values(data.query?.pages || {})[0];
  return page ? commonsCandidate(page) : null;
}

async function commonsSearch(q) {
  const url = `https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search&gsrnamespace=6&gsrlimit=20&gsrsearch=${encodeURIComponent(q + " filetype:bitmap")}&${IIPROP}`;
  const data = await get(url);
  const pages = Object.values(data?.query?.pages || {});
  return pages.map(commonsCandidate).filter(Boolean).sort((a, b) => a.index - b.index);
}

async function openverseSearch(q) {
  const url = `https://api.openverse.org/v1/images/?q=${encodeURIComponent(q)}&license_type=commercial&mature=false&page_size=20`;
  const data = await get(url);
  return (data?.results || []).map((r) => ({
    title: r.title || q,
    url: r.url,
    width: r.width || 0,
    height: r.height || 0,
    mime: "image/" + (r.filetype || "jpeg"),
    author: r.creator || "Unknown author",
    license: `${(r.license || "").toUpperCase()} ${r.license_version || ""}`.trim(),
    licenseUrl: r.license_url || "",
    source: r.foreign_landing_url || r.url,
    via: `Openverse (${r.source || r.provider || "source"})`,
  }));
}

function acceptable(c) {
  if (!c || !c.url) return false;
  if (!/jpe?g|png|webp/i.test(c.mime || "")) return false;
  if (c.width && c.width < 500) return false;
  if (c.height && c.height < 400) return false;
  if (c.width && c.height) {
    const r = c.width / c.height;
    if (r < 0.5 || r > 2.2) return false;
  }
  if (BLOCK.test(c.title)) return false;
  return !used.has(c.source);
}

async function saveImage(slug, buf) {
  const out = await sharp(buf, { failOn: "none" })
    .rotate()
    .resize(SIZE, SIZE, { fit: "cover", position: sharp.strategy.attention })
    .flatten({ background: "#ffffff" })
    .jpeg({ quality: 78, mozjpeg: true })
    .toBuffer();
  await fs.writeFile(path.join(CACHE_DIR, `${slug}.jpg`), out);
  await fs.writeFile(path.join(OUT_DIR, `${slug}.jpg`), out);
}

async function tryCandidates(slug, list) {
  for (const c of list) {
    if (!acceptable(c)) continue;
    const buf = await get(c.url, "buffer", 2);
    if (!buf || buf.length < 5000) continue;
    try {
      await saveImage(slug, buf);
    } catch {
      continue;
    }
    return c;
  }
  return null;
}

async function exists(p) {
  return fs.access(p).then(() => true, () => false);
}

async function handle(p) {
  const o = overrides[p.slug] || {};
  if (o.skip) return;
  if (await exists(path.join(OWN_DIR, `${p.slug}.jpg`))) {
    credits[p.slug] = { src: `/img/own/${p.slug}.jpg`, own: true };
    return;
  }
  const cached = cache[p.slug];
  const wanted = o.file || null;
  if (cached && (!wanted || cached.title === wanted) && (await exists(path.join(CACHE_DIR, `${p.slug}.jpg`)))) {
    await fs.copyFile(path.join(CACHE_DIR, `${p.slug}.jpg`), path.join(OUT_DIR, `${p.slug}.jpg`));
    used.add(cached.source);
    credits[p.slug] = { ...cached, src: `/img/products/${p.slug}.jpg` };
    return;
  }
  if (!online) return;
  let chosen = null;
  if (wanted) chosen = await tryCandidates(p.slug, [await commonsByTitle(wanted)].filter(Boolean));
  const q = o.query || p.image_query || p.name;
  if (!chosen) chosen = await tryCandidates(p.slug, await commonsSearch(q));
  if (!chosen) chosen = await tryCandidates(p.slug, await openverseSearch(q));
  if (!chosen) {
    console.log(`  no photo: ${p.slug} (${q})`);
    return;
  }
  used.add(chosen.source);
  const { index, width, height, mime, url, ...credit } = chosen;
  cache[p.slug] = credit;
  credits[p.slug] = { ...credit, src: `/img/products/${p.slug}.jpg` };
  console.log(`  ${p.slug} <- ${chosen.title} [${chosen.license}]`);
}

// Cached choices first so they keep their photos, then new searches.
const order = [...products].sort((a, b) => (cache[b.slug] ? 1 : 0) - (cache[a.slug] ? 1 : 0));
const queue = [...order];
const started = Date.now();
async function worker() {
  while (queue.length) {
    const p = queue.shift();
    try {
      await handle(p);
    } catch (e) {
      console.log(`  error: ${p.slug}: ${e.message}`);
    }
    if (Date.now() - started > 8 * 60 * 1000) {
      console.log("  time limit reached; remaining products use illustrations");
      queue.length = 0;
    }
  }
}
const online = process.env.ATP_SKIP_IMAGES !== "1" && (await get("https://commons.wikimedia.org/w/api.php?action=query&format=json&meta=siteinfo", "json", 1)) !== null;
if (!online) console.log("Image sources unreachable: using cached and own photos only.");
console.log(`Fetching product photos for ${products.length} products...`);
// Three at a time keeps the build quick without overloading the image APIs.
await Promise.all([worker(), worker(), worker()]);

await fs.writeFile(CACHE_FILE, JSON.stringify(cache, null, 1));
const sorted = Object.fromEntries(Object.keys(credits).sort().map((k) => [k, credits[k]]));
await fs.writeFile(path.join(GEN_DIR, "image-credits.json"), JSON.stringify(sorted, null, 1));
console.log(`Product photos ready: ${Object.keys(credits).length} of ${products.length}.`);
