"use client";

export type QuoteItem = {
  key: string;
  slug: string;
  name: string;
  sku: string;
  unit: string;
  qty: number;
  size?: string;
  colour?: string;
  note?: string;
  image?: string;
};

const KEY = "atp_quote_v1";
const EVENT = "atp-quote-change";
export const MAX_LINES = 100;

export function readQuote(): QuoteItem[] {
  try {
    const raw = localStorage.getItem(KEY);
    const list = raw ? JSON.parse(raw) : [];
    return Array.isArray(list) ? list.slice(0, MAX_LINES) : [];
  } catch {
    return [];
  }
}

export function writeQuote(list: QuoteItem[]) {
  try {
    localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX_LINES)));
  } catch {
    /* storage may be unavailable in private mode */
  }
  window.dispatchEvent(new CustomEvent(EVENT));
}

export function addToQuote(item: Omit<QuoteItem, "key">): boolean {
  const key = [item.slug, item.size || "", item.colour || ""].join("|");
  const list = readQuote();
  const found = list.find((i) => i.key === key);
  if (found) {
    found.qty = Math.min(1_000_000, found.qty + item.qty);
    if (item.note) found.note = item.note;
  } else {
    if (list.length >= MAX_LINES) return false;
    list.push({ ...item, key });
  }
  writeQuote(list);
  window.dispatchEvent(new CustomEvent("atp-toast", { detail: "Added to your quote list. Continue browsing or review your items." }));
  return true;
}

export function clearQuote() {
  writeQuote([]);
}

export function onQuoteChange(cb: () => void) {
  const handler = () => cb();
  window.addEventListener(EVENT, handler);
  window.addEventListener("storage", handler);
  return () => {
    window.removeEventListener(EVENT, handler);
    window.removeEventListener("storage", handler);
  };
}
