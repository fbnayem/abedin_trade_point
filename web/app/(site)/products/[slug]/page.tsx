import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  categoriesInGroup, categoryUrl, getCategory, getGroup, productsInCategory, productsInSub, publishedGroups, categories, subsInCategory,
} from "@/lib/catalogue";
import SubCard from "@/components/SubCard";
import ProductCard from "@/components/ProductCard";
import Crumbs from "@/components/Crumbs";
import CategoryHelp from "@/components/CategoryHelp";

export const dynamicParams = false;

export function generateStaticParams() {
  return [...publishedGroups().map((g) => ({ slug: g.slug })), ...categories.map((c) => ({ slug: c.slug }))];
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const c = getCategory(slug);
  if (c) return { title: c.meta_title, description: c.meta_description, alternates: { canonical: categoryUrl(c) } };
  const g = getGroup(slug);
  if (g) return { title: `${g.name} | Abedin Trade Point`, description: `Bulk ${g.name.toLowerCase()} supplies in Bangladesh. Browse categories and request one quotation for several items.`, alternates: { canonical: `/products/${g.slug}/` } };
  return {};
}

export default async function CategoryPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const c = getCategory(slug);
  if (c) {
    const subs = subsInCategory(c.slug);
    const group = getGroup(c.group);
    return (
      <div className="container">
        <Crumbs items={[{ name: "Products", href: "/products/" }, ...(group ? [{ name: group.name, href: `/products/${group.slug}/` }] : []), { name: c.name }]} />
        <h1>{c.h1}</h1>
        <p className="lead">{c.intro}</p>
        <ul className="chips">
          {subs.map((s) => (
            <li key={s.slug}><a href={`#${s.slug}`}>{s.name} ({productsInSub(s.slug).length})</a></li>
          ))}
        </ul>
        <div className="grid grid--subs">
          {subs.map((s, i) => (
            <SubCard key={s.slug} s={s} eager={i < 4} />
          ))}
        </div>
        {subs.map((s) => (
          <section key={s.slug} id={s.slug} style={{ marginTop: "3rem", scrollMarginTop: "150px" }}>
            <div className="section-head">
              <div>
                <h2>{s.name}</h2>
                <p>{s.intro}</p>
              </div>
              <a className="btn btn--outline btn--sm" href={`/products/${c.slug}/${s.slug}/`}>Open {s.name}</a>
            </div>
            <div className="grid">
              {productsInSub(s.slug).map((p) => (
                <ProductCard key={p.slug} p={p} />
              ))}
            </div>
          </section>
        ))}
        <CategoryHelp prompt={c.prompt} button={c.button} />
        <div style={{ height: "3rem" }} />
      </div>
    );
  }
  const g = getGroup(slug);
  if (!g) notFound();
  const cats = categoriesInGroup(g.slug);
  if (!cats.length) notFound();
  return (
    <div className="container">
      <Crumbs items={[{ name: "Products", href: "/products/" }, { name: g.name }]} />
      <h1>{g.name}</h1>
      <p className="lead">Choose a category to see the product types available for quotation.</p>
      {cats.map((cat) => (
        <section className="hub-cat" key={cat.slug}>
          <h2 style={{ display: "flex", justifyContent: "space-between", gap: 12, alignItems: "baseline" }}>
            <span>{cat.name}</span>
            <a className="has-small-font-size" href={categoryUrl(cat)}>View all {productsInCategory(cat.slug).length} products →</a>
          </h2>
          <div className="grid grid--subs">
            {subsInCategory(cat.slug).map((s) => (
              <SubCard key={s.slug} s={s} />
            ))}
          </div>
        </section>
      ))}
      <CategoryHelp />
      <div style={{ height: "3rem" }} />
    </div>
  );
}
