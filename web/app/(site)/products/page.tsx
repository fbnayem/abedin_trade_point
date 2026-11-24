import type { Metadata } from "next";
import { categoriesInGroup, categoryUrl, productsInCategory, publishedGroups, subcategories, subsInCategory, products } from "@/lib/catalogue";
import SubCard from "@/components/SubCard";
import Crumbs from "@/components/Crumbs";
import CategoryHelp from "@/components/CategoryHelp";

export const metadata: Metadata = {
  title: "Products for Bulk Supply | Abedin Trade Point",
  description: "Browse garments, bags, stationery, paper, corporate gifts, furniture, cleaning, medical and packaging supplies. Add items to one quote list.",
  alternates: { canonical: "/products/" },
};

export default function ProductsHub() {
  const groups = publishedGroups();
  return (
    <div className="container">
      <Crumbs items={[{ name: "Products" }]} />
      <h1>Products for Bulk Supply</h1>
      <p className="lead">
        {subcategories.length} categories and {products.length} product types for offices, schools, factories, events and projects. Open a category, add the items you need and send one quotation request.
      </p>
      <ul className="chips" aria-label="Jump to a department">
        {groups.flatMap((g) => categoriesInGroup(g.slug)).map((c) => (
          <li key={c.slug}><a href={`#${c.slug}`}>{c.name}</a></li>
        ))}
      </ul>
      {groups.map((g) => (
        <section className="hub-group" key={g.slug} id={g.slug}>
          <h2>{g.name}</h2>
          {categoriesInGroup(g.slug).map((c) => (
            <div className="hub-cat" key={c.slug} id={c.slug}>
              <h3>
                <span>{c.name}</span>
                <a href={categoryUrl(c)}>View all {productsInCategory(c.slug).length} products →</a>
              </h3>
              <div className="grid grid--subs">
                {subsInCategory(c.slug).map((s) => (
                  <SubCard key={s.slug} s={s} />
                ))}
              </div>
            </div>
          ))}
        </section>
      ))}
      <CategoryHelp />
      <div style={{ height: "3rem" }} />
    </div>
  );
}
