import catData from "@/content/categories.json";
import subData from "@/content/subcategories.json";
import productData from "@/content/products.json";
import creditData from "@/content/generated/image-credits.json";

export type Group = { slug: string; name: string };
export type Category = {
  slug: string;
  name: string;
  group: string;
  release: string;
  h1: string;
  intro: string;
  prompt: string;
  button: string;
  meta_title: string;
  meta_description: string;
  published: boolean;
  card?: string;
  examples?: string;
};
export type Subcategory = {
  slug: string;
  name: string;
  category: string;
  unit: string;
  intro: string;
  spec_prompt: string;
  featured: boolean;
  sizes: string[];
  colours: string[];
  card: string;
};
export type Product = {
  slug: string;
  name: string;
  sku: string;
  category: string;
  subcategory: string;
  unit: string;
  description: string;
  specs: Record<string, string>;
  spec_prompt: string;
  sizes: string[];
  colours: string[];
  featured: boolean;
  image_query: string;
};
export type Credit = {
  src: string;
  own?: boolean;
  title?: string;
  author?: string;
  license?: string;
  licenseUrl?: string;
  source?: string;
  via?: string;
};

export const groups: Group[] = catData.groups;
export const allCategories: Category[] = catData.categories as Category[];
export const categories: Category[] = allCategories.filter((c) => c.published);
const publishedCats = new Set(categories.map((c) => c.slug));
export const subcategories: Subcategory[] = (subData as unknown as Subcategory[]).filter((s) => publishedCats.has(s.category));
export const products: Product[] = (productData as unknown as Product[]).filter((p) => publishedCats.has(p.category));
const credits = creditData as unknown as Record<string, Credit>;

export const UNITS: Record<string, string> = {
  piece: "Piece",
  pack: "Pack",
  set: "Set",
  ream: "Ream",
  roll: "Roll",
  pair: "Pair",
  box: "Box",
  pad: "Pad",
  carton: "Carton",
  metre: "Metre",
  kg: "Kilogram",
};
export const DECIMAL_UNITS = ["metre", "kg"];

export const getGroup = (slug: string) => groups.find((g) => g.slug === slug);
export const getCategory = (slug: string) => categories.find((c) => c.slug === slug);
export const getSubcategory = (slug: string) => subcategories.find((s) => s.slug === slug);
export const getProduct = (slug: string) => products.find((p) => p.slug === slug);

export const categoriesInGroup = (g: string) => categories.filter((c) => c.group === g);
export const publishedGroups = () => groups.filter((g) => categoriesInGroup(g.slug).length > 0);
export const subsInCategory = (c: string) => subcategories.filter((s) => s.category === c);
export const productsInSub = (s: string) => products.filter((p) => p.subcategory === s);
export const productsInCategory = (c: string) => products.filter((p) => p.category === c);

export const imageFor = (slug: string): Credit | null => credits[slug] ?? null;
export const subImage = (s: Subcategory): Credit | null => {
  for (const p of productsInSub(s.slug)) {
    const c = imageFor(p.slug);
    if (c) return c;
  }
  return null;
};
export const allCredits = () =>
  products.map((p) => ({ product: p, credit: imageFor(p.slug) })).filter((x) => x.credit && !x.credit.own);

export const subUrl = (s: Subcategory) => `/products/${s.category}/${s.slug}/`;
export const productUrl = (p: Product) => `/product/${p.slug}/`;
export const categoryUrl = (c: { slug: string }) => `/products/${c.slug}/`;

export const featuredSubs = () => subcategories.filter((s) => s.featured);

/* Search: simple scoring with synonyms common in Bangladesh procurement lists. */
const SYNONYMS: Record<string, string[]> = {
  tshirt: ["t-shirt", "tee", "t shirt", "gengi", "genji"],
  polo: ["polo shirt", "collar t-shirt"],
  bag: ["bags", "tote", "shopper", "jhola"],
  jute: ["pat", "hessian", "juco"],
  notebook: ["khata", "exercise book", "copy"],
  pen: ["ballpen", "ball pen", "biro"],
  paper: ["a4", "offset", "copy paper"],
  tissue: ["napkin", "toilet roll", "towel"],
  carton: ["box", "cardboard"],
  chair: ["seat", "stool"],
  mask: ["face mask", "surgical"],
  uniform: ["workwear", "apron", "scrubs", "coat"],
  hoodie: ["sweatshirt", "jacket", "winter"],
  cap: ["hat", "topi"],
};

export function search(q: string) {
  const terms = q.toLowerCase().replace(/[^a-z0-9\s-]/g, " ").split(/\s+/).filter((t) => t.length > 1);
  if (!terms.length) return [];
  const expand = new Set(terms);
  for (const t of terms) {
    for (const [k, list] of Object.entries(SYNONYMS)) {
      if (k === t || list.some((w) => w.includes(t) || t.includes(w.replace(/\s/g, "")))) {
        expand.add(k);
        list.forEach((w) => expand.add(w));
      }
    }
  }
  const words = [...expand];
  return products
    .map((p) => {
      const sub = getSubcategory(p.subcategory);
      const name = p.name.toLowerCase();
      const hay = `${name} ${sub?.name ?? ""} ${p.description} ${Object.values(p.specs).join(" ")}`.toLowerCase();
      let score = 0;
      for (const w of words) {
        if (name.includes(w)) score += 5;
        else if ((sub?.name ?? "").toLowerCase().includes(w)) score += 3;
        else if (hay.includes(w)) score += 1;
      }
      return { p, score };
    })
    .filter((x) => x.score > 0)
    .sort((a, b) => b.score - a.score)
    .map((x) => x.p);
}
