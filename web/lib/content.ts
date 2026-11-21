import fs from "node:fs";
import path from "node:path";
import pagesData from "@/content/pages.json";
import { renderShortcodes } from "./site";

export type PageMeta = { title: string; h1: string; status: string; meta_title: string; meta_description: string; template?: string };
export const pages = pagesData as Record<string, PageMeta>;

export function html(name: string) {
  const file = path.join(process.cwd(), "content/html", `${name}.html`);
  return renderShortcodes(fs.readFileSync(file, "utf8"));
}
