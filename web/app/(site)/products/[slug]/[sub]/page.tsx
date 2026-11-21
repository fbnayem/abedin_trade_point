import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { categories, getCategory, getGroup, getSubcategory, productsInSub, subcategories, subsInCategory, subUrl, categoryUrl } from "@/lib/catalogue";
import ProductCard from "@/components/ProductCard";
import Crumbs from "@/components/Crumbs";
import CategoryHelp from "@/components/CategoryHelp";

export const dynamicParams = false;

export function generateStaticParams() {
  return subcategories.map((s) => ({ slug: s.category, sub: s.slug }));
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string; sub: string }> }): Promise<Metadata> {
  const { sub } = await params;
  const s = getSubcategory(sub);
  if (!s) return {};
  return {
    title: `${s.name} for Bulk Orders in Bangladesh | Abedin Trade Point`,
    description: `${s.intro} ${productsInSub(s.slug).length} styles available for quotation.`.slice(0, 300),
    alternates: { canonical: subUrl(s) },
  };
}

export default async function SubcategoryPage({ params }: { params: Promise<{ slug: string; sub: string }> }) {
  const { slug, sub } = await params;
  const s = getSubcategory(sub);
  const c = getCategory(slug);
  if (!s || !c || s.category !== c.slug) notFound();
  const items = productsInSub(s.slug);
  const group = getGroup(c.group);
  return (
    <div className="container">
      <Crumbs items={[{ name: "Products", href: "/products/" }, { name: c.name, href: categoryUrl(c) }, { name: s.name }]} />
      <div className="layout-side">
        <aside className="side-nav" aria-label="Categories">
          {categories.filter((x) => x.group === c.group || x.slug === c.slug).map((x) => (
            <div key={x.slug}>
              <h2>{x.name}</h2>
              <ul>
                {subsInCategory(x.slug).map((y) => (
                  <li key={y.slug}>
                    <a href={subUrl(y)} aria-current={y.slug === s.slug ? "page" : undefined}>
                      {y.name} <small>{productsInSub(y.slug).length}</small>
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
          <p><a href="/products/">All categories →</a></p>
        </aside>
        <div>
          <h1>{s.name}</h1>
          <p className="lead">{s.intro}</p>
          <ul className="chips">
            {subsInCategory(c.slug).map((y) => (
              <li key={y.slug}><a href={subUrl(y)} aria-current={y.slug === s.slug ? "page" : undefined}>{y.name}</a></li>
            ))}
          </ul>
          <div className="toolbar">
            <span>{items.length} product types · Price on quotation</span>
            {group && <a href={`/products/${group.slug}/`}>More in {group.name}</a>}
          </div>
          <div className="grid">
            {items.map((p, i) => (
              <ProductCard key={p.slug} p={p} />
            ))}
          </div>
          <CategoryHelp prompt={`For ${s.name.toLowerCase()}, tell us: ${s.spec_prompt}`} button={c.button} />
        </div>
      </div>
      <div style={{ height: "3rem" }} />
    </div>
  );
}
