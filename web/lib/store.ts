import fs from "node:fs/promises";
import path from "node:path";
import { del, get, list, put } from "@vercel/blob";

/**
 * Private storage for enquiries and uploaded files.
 * On Vercel: a private Vercel Blob store (BLOB_READ_WRITE_TOKEN).
 * Locally without a token: the .data/ folder.
 */
export const usingBlob = () => Boolean(process.env.BLOB_READ_WRITE_TOKEN);
const LOCAL = path.join(process.cwd(), ".data");
const safe = (p: string) => {
  const clean = path.posix.normalize(p).replace(/^\/+/, "");
  if (clean.startsWith("..")) throw new Error("Bad path");
  return clean;
};

export function storageReady() {
  return usingBlob() || !process.env.VERCEL;
}

export async function writeJson(p: string, data: unknown) {
  const body = JSON.stringify(data, null, 1);
  if (usingBlob()) {
    await put(safe(p), body, { access: "private", contentType: "application/json", addRandomSuffix: false, allowOverwrite: true, cacheControlMaxAge: 60 });
    return;
  }
  const file = path.join(LOCAL, safe(p));
  await fs.mkdir(path.dirname(file), { recursive: true });
  await fs.writeFile(file, body);
}

export async function readBytes(p: string): Promise<{ data: Buffer; type: string } | null> {
  if (usingBlob()) {
    const r = await get(safe(p), { access: "private", useCache: false }).catch(() => null);
    if (!r || r.statusCode !== 200 || !r.stream) return null;
    const data = Buffer.from(await new Response(r.stream).arrayBuffer());
    return { data, type: r.blob.contentType || "application/octet-stream" };
  }
  try {
    const data = await fs.readFile(path.join(LOCAL, safe(p)));
    return { data, type: "application/octet-stream" };
  } catch {
    return null;
  }
}

export async function readJson<T>(p: string): Promise<T | null> {
  const r = await readBytes(p);
  if (!r) return null;
  try {
    return JSON.parse(r.data.toString("utf8")) as T;
  } catch {
    return null;
  }
}

export async function exists(p: string) {
  return (await readBytes(p)) !== null;
}

export async function listPaths(prefix: string): Promise<string[]> {
  if (usingBlob()) {
    const out: string[] = [];
    let cursor: string | undefined;
    do {
      const r = await list({ prefix: safe(prefix), cursor, limit: 1000 });
      out.push(...r.blobs.map((b) => b.pathname));
      cursor = r.hasMore ? r.cursor : undefined;
    } while (cursor);
    return out;
  }
  const dir = path.join(LOCAL, safe(prefix));
  try {
    const names = await fs.readdir(dir, { recursive: true });
    return names.map((n) => path.posix.join(safe(prefix), String(n).split(path.sep).join("/")));
  } catch {
    return [];
  }
}

export async function writeBytes(p: string, data: Buffer, type: string) {
  if (usingBlob()) {
    await put(safe(p), data, { access: "private", contentType: type, addRandomSuffix: false, allowOverwrite: true });
    return;
  }
  const file = path.join(LOCAL, safe(p));
  await fs.mkdir(path.dirname(file), { recursive: true });
  await fs.writeFile(file, data);
}

export async function remove(p: string) {
  if (usingBlob()) {
    await del(safe(p)).catch(() => undefined);
    return;
  }
  await fs.rm(path.join(LOCAL, safe(p)), { force: true });
}
