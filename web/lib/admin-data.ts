import { listPaths, readJson, writeJson } from "./store";
import type { Enquiry } from "./enquiry";

export const validRef = (ref: string) => /^ATP-\d{6}-[A-Z0-9]{4}$/.test(ref);

export async function listEnquiries(): Promise<Enquiry[]> {
  const paths = (await listPaths("enquiries/")).filter((p) => p.endsWith(".json"));
  const all = await Promise.all(paths.map((p) => readJson<Enquiry>(p)));
  return all.filter((e): e is Enquiry => !!e).sort((a, b) => b.created.localeCompare(a.created));
}

export async function getEnquiry(ref: string) {
  if (!validRef(ref)) return null;
  return readJson<Enquiry>(`enquiries/${ref}.json`);
}

export async function saveEnquiry(e: Enquiry) {
  e.updated = new Date().toISOString();
  await writeJson(`enquiries/${e.ref}.json`, e);
}
