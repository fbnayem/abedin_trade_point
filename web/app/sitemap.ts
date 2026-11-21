import type { MetadataRoute } from "next";
import { categories, products, productUrl, publishedGroups, subcategories, subUrl } from "@/lib/catalogue";
import { pages } from "@/lib/content";
import { site } from "@/lib/site";

export default function sitemap(): MetadataRoute.Sitemap {
  const u = (p: string) => site.url + p;
  const staticPages = Object.entries(pages).filter(([, m]) => m.status === "publish").map(([slug]) => u(`/${slug}/`));
  return [
    { url: u("/"), priority: 1 },
    { url: u("/products/"), priority: 0.9 },
    ...publishedGroups().map((g) => ({ url: u(`/products/${g.slug}/`), priority: 0.6 })),
    ...categories.map((c) => ({ url: u(`/products/${c.slug}/`), priority: 0.8 })),
    ...subcategories.map((s) => ({ url: u(subUrl(s)), priority: 0.8 })),
    ...products.map((p) => ({ url: u(productUrl(p)), priority: 0.6 })),
    ...staticPages.map((url) => ({ url, priority: 0.5 })),
  ];
}
