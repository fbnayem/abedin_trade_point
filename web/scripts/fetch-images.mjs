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
// Event, people and artwork photos rarely show the product clearly.
const PEOPLE = /\b(concert|festival|singer|band|musician|rapper|actor|actress|politician|player|match|game|protest|rally|wedding|ceremony|parade|portrait|selfie|crowd|award|premiere|conference 20\d\d|\d{4}-\d{2}-\d{2})\b/i;
const BLOCK = /\b(logo|map|diagram|flag|coat of arms|icon|chart|poster|screenshot|svg|stamp|sign|graffiti|cartoon|drawing|illustration|patent|advert|museum|painting|portrait of|statue|banknote|coin|text|document|page|book cover|album|stadium|conference room|hackathon|battalion|regiment|soldiers?|army|police|competition|making of|covid|reception|clerk|visitors|world cup|lego|toy|toxic|blood|clouds|shown|team|squad|harry potter|wedding|funeral|church|cathedral|museum)\b/i;

// What each product photo must show: the file title has to name the product
// type, so a search never fills the T-shirt grid with a stadium or a portrait.
// q is the broad search used when the product's own search finds nothing.
const SUBS = {
  "t-shirts": { key: /t-?shirt|\btees?\b|tank ?top|singlet|henley|raglan/i, q: "t-shirt" },
  "polo-shirts": { key: /polo[ _-]?shirt|piqu[eé] polo|polo top/i, q: "polo shirt" },
  "formal-shirts": { key: /shirt/i, q: "dress shirt" },
  "hoodies-sweatshirts": { key: /hood(ie|y)|sweatshirt|sweater|jacket|fleece|gilet|\bvest\b|windbreaker|anorak|pullover|jumper/i, q: "hoodie" },
  "workwear-uniforms": { key: /coverall|boiler ?suit|overall|uniform|workwear|hi-?vis|high.visibility|safety vest|lab coat|scrubs|chef|apron|trousers|cargo pants|work ?wear/i, q: "workwear" },
  "jute-bags": { key: /jute|hessian|burlap|gunny|juco/i, q: "jute bag" },
  "cotton-canvas-tote-bags": { key: /tote|shopping bag|shopper|canvas bag|cotton bag|carrier bag|non.?woven|reusable bag/i, q: "tote bag" },
  "backpacks": { key: /backpack|rucksack|daypack|knapsack|school ?bag/i, q: "backpack" },
  "document-conference-bags": { key: /briefcase|satchel|messenger bag|laptop (bag|sleeve|case)|document (bag|pouch|case)|portfolio|tote|conference bag|file bag/i, q: "briefcase" },
  "drawstring-duffel-bags": { key: /drawstring|duffel|duffle|gym bag|holdall|sports bag|kit ?bag|shoe bag/i, q: "drawstring bag" },
  "notebooks-exercise-books": { key: /notebook|exercise book|journal|diary|planner|sketch ?book|notepad|copybook|moleskine|composition book/i, q: "notebook" },
  "pens-markers": { key: /\bpens?\b|marker|highlighter|biro|ballpoint|felt.tip/i, q: "ballpoint pen" },
  "pencils-erasers": { key: /pencil|eraser|rubber|sharpener|crayon/i, q: "pencils" },
  "files-folders": { key: /\bfiles?\b|folder|binder|clipboard|lever arch|display book/i, q: "office folder" },
  "desk-supplies": { key: /stapler|staples|hole ?punch|paper punch|scissors|sticky note|post-it|organi[sz]er|calculator|glue|adhesive tape|paper ?clips?|binder clips?/i, q: "office supplies" },
  "office-paper": { key: /paper|ream|card ?stock|cardstock/i, q: "copy paper" },
  "printed-booklets-manuals": { key: /booklet|manual|brochure|flyer|leaflet|pamphlet|annual report|bound book|printed book|catalogue|catalog/i, q: "booklet" },
  "printed-forms-pads": { key: /receipt|letterhead|stationery|envelope|notepad|writing pad|business cards?|visiting cards?|invoice|carbon(less)? (paper|copy)/i, q: "receipt book" },
  "branded-pens": { key: /\bpens?\b|ballpoint|stylus/i, q: "promotional pen" },
  "mugs-drinkware": { key: /\bmugs?\b|\bcups?\b|bottle|tumbler|flask/i, q: "mug" },
  "caps-hats": { key: /\bcaps?\b|\bhats?\b|visor|beanie|bucket hat|snapback/i, q: "baseball cap" },
  "gift-sets": { key: /gift|hamper|lanyard|key ?chain|key ?ring|welcome kit|merchandise|swag|power bank/i, q: "corporate gift" },
  "corrugated-cartons": { key: /carton|cardboard|corrugated|shipping box|mailer box|pizza box|storage box|archive box/i, q: "cardboard box" },
  "paper-bags": { key: /paper bags?|kraft bag|carrier bag|paper shopping bag/i, q: "paper bag" },
  "labels-stickers": { key: /label|sticker|hologram/i, q: "labels" },
  "packing-tape-wrap": { key: /\btapes?\b|stretch (film|wrap)|shrink ?wrap|bubble ?wrap|pallet wrap/i, q: "packing tape" },
  "tissue-products": { key: /tissue|toilet (paper|roll)|napkin|paper towel|kitchen roll|wipes|kleenex/i, q: "tissue paper" },
  "cleaning-tools-chemicals": { key: /\bmops?\b|broom|dustpan|cleaning cloth|microfib|brush|cleaner|cleaning|detergent|saniti[sz]er|hand ?wash|soap|squeegee|janitor/i, q: "cleaning supplies" },
  "waste-bins-dispensers": { key: /\bbins?\b|trash|garbage|rubbish|waste|dustbin|recycl|dispenser|bin bag/i, q: "waste bin" },
  "office-chairs": { key: /chairs?\b|stool|seating/i, q: "office chair" },
  "desks-classroom-furniture": { key: /\bdesks?\b|\btables?\b|filing cabinet|bookshelf|bookcase|shelving|school bench/i, q: "office desk" },
  "medical-consumables": { key: /mask|glove|syringe|gauze|bandage|shoe cover|surgical cap|gown|thermometer|first.aid|medical supplies/i, q: "medical supplies" },
};
const fits = (p, title = "") => {
  const rule = SUBS[p.subcategory];
  return !rule || rule.key.test(title);
};

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
  const data = await get(url, "json", 1);
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

function acceptable(c, p) {
  if (!c || !c.url) return false;
  if (!fits(p, c.title)) return false;
  if (!/jpe?g|png|webp/i.test(c.mime || "")) return false;
  if (c.width && c.width < 500) return false;
  if (c.height && c.height < 400) return false;
  if (c.width && c.height) {
    const r = c.width / c.height;
    if (r < 0.5 || r > 2.2) return false;
  }
  if (BLOCK.test(c.title) || PEOPLE.test(c.title)) return false;
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

async function tryCandidates(p, list, pinned = false) {
  const slug = p.slug;
  for (const c of list) {
    if (pinned ? !c?.url : !acceptable(c, p)) continue;
    used.add(c.source); // reserve now so parallel workers never pick the same photo
    const buf = await get(c.url, "buffer", 2);
    try {
      if (!buf || buf.length < 5000) throw new Error("empty");
      await saveImage(slug, buf);
    } catch {
      used.delete(c.source);
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
  const cacheOk = cached && (!wanted || cached.title === wanted) && (wanted || (!BLOCK.test(cached.title) && !PEOPLE.test(cached.title) && fits(p, cached.title))) && !used.has(cached.source);
  if (cacheOk) used.add(cached.source);
  if (cacheOk && (await exists(path.join(CACHE_DIR, `${p.slug}.jpg`)))) {
    await fs.copyFile(path.join(CACHE_DIR, `${p.slug}.jpg`), path.join(OUT_DIR, `${p.slug}.jpg`));
    credits[p.slug] = { ...cached, src: `/img/products/${p.slug}.jpg` };
    return;
  }
  if (!online) return;
  let chosen = null;
  // A pinned file is trusted as is.
  if (wanted) chosen = await tryCandidates(p, [await commonsByTitle(wanted)].filter(Boolean), true);
  const q = o.query || p.image_query || p.name;
  // Commons search needs every word to match, so widen step by step.
  const words = q.split(/\s+/);
  const tries = [...new Set([q, words.slice(0, 2).join(" "), p.name.replace(/\b(for|with|and|bulk|orders?)\b/gi, " ").replace(/\s+/g, " ").trim()])];
  for (const t of tries) {
    if (chosen) break;
    chosen = await tryCandidates(p, await commonsSearch(t));
  }
  if (!chosen) chosen = await tryCandidates(p, await openverseSearch(q));
  // Last resort: another photo of the same kind of product.
  const broad = SUBS[p.subcategory]?.q;
  if (!chosen && broad && !tries.includes(broad)) chosen = await tryCandidates(p, await commonsSearch(broad));
  if (!chosen && broad) chosen = await tryCandidates(p, await openverseSearch(broad));
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
    if (Date.now() - started > 18 * 60 * 1000) {
      console.log("  time limit reached; remaining products use illustrations");
      queue.length = 0;
    }
  }
}
const online = process.env.ATP_SKIP_IMAGES !== "1" && (await get("https://commons.wikimedia.org/w/api.php?action=query&format=json&meta=siteinfo", "json", 1)) !== null;
if (!online) console.log("Image sources unreachable: using cached and own photos only.");
console.log(`Fetching product photos for ${products.length} products...`);
// Five at a time keeps the build quick without overloading the image APIs.
await Promise.all([worker(), worker(), worker(), worker(), worker()]);

await fs.writeFile(CACHE_FILE, JSON.stringify(cache, null, 1));
const sorted = Object.fromEntries(Object.keys(credits).sort().map((k) => [k, credits[k]]));
await fs.writeFile(path.join(GEN_DIR, "image-credits.json"), JSON.stringify(sorted, null, 1));
console.log(`Product photos ready: ${Object.keys(credits).length} of ${products.length}.`);
