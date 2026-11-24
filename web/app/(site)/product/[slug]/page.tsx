import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { DECIMAL_UNITS, UNITS, categoryUrl, getCategory, getProduct, getSubcategory, imageFor, products, productsInSub, productUrl, subUrl } from "@/lib/catalogue";
import { site, whatsappUrl } from "@/lib/site";
import Crumbs from "@/components/Crumbs";
import ProductImage from "@/components/ProductImage";
import ProductCard from "@/components/ProductCard";
import AddToQuoteForm from "@/components/AddToQuoteForm";

export const dynamicParams = false;

export function generateStaticParams() {
  return products.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const p = getProduct(slug);
  if (!p) return {};
  const img = imageFor(p.slug);
  return {
    title: `${p.name} - Bulk Orders | Abedin Trade Point`,
    description: `${p.description} Request a quotation for bulk quantities in Bangladesh.`.slice(0, 300),
    alternates: { canonical: productUrl(p) },
    openGraph: img ? { images: [{ url: img.src, width: 900, height: 900 }] } : undefined,
  };
}

export default async function ProductPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const p = getProduct(slug);
  if (!p) notFound();
  const sub = getSubcategory(p.subcategory)!;
  const cat = getCategory(p.category)!;
  const img = imageFor(p.slug);
  const related = productsInSub(sub.slug).filter((x) => x.slug !== p.slug).slice(0, 4);
  const ld = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: p.name,
    sku: p.sku,
    description: p.description,
    category: `${cat.name} > ${sub.name}`,
    brand: { "@type": "Brand", name: site.name },
    ...(img ? { image: site.url + img.src } : {}),
  };
  return (
    <div className="container">
      <Crumbs items={[{ name: "Products", href: "/products/" }, { name: cat.name, href: categoryUrl(cat) }, { name: sub.name, href: subUrl(sub) }, { name: p.name }]} />
      <div className="pdp">
        <div>
          <div className="pdp__img">
            <ProductImage category={p.category} alt={p.name} credit={img} eager label={sub.name} />
          </div>
          {img && !img.own && (
            <p className="pdp__credit">
              Sample photo: <a href={img.source} target="_blank" rel="noopener nofollow">{img.author}</a>, {img.licenseUrl ? <a href={img.licenseUrl} target="_blank" rel="noopener nofollow">{img.license}</a> : img.license}, via {img.via}. The supplied product is agreed in your quotation.
            </p>
          )}
        </div>
        <div>
          <p className="pdp__sku">{sub.name} · SKU {p.sku}</p>
          <h1 style={{ fontSize: "clamp(1.75rem, 1.3rem + 1.6vw, 2.5rem)" }}>{p.name}</h1>
          <p className="pdp__price">Price on quotation</p>
          <p>{p.description}</p>
          <table className="specs">
            <caption className="sr-only">Typical specifications</caption>
            <tbody>
              {Object.entries(p.specs).map(([k, v]) => (
                <tr key={k}><th scope="row">{k}</th><td>{v}</td></tr>
              ))}
              <tr><th scope="row">Order unit</th><td>{UNITS[p.unit] ?? p.unit}</td></tr>
              {p.sizes.length > 0 && <tr><th scope="row">Sizes</th><td>{p.sizes.join(", ")}</td></tr>}
              {p.colours.length > 0 && <tr><th scope="row">Colours</th><td>{p.colours.join(", ")} or custom</td></tr>}
            </tbody>
          </table>
          <AddToQuoteForm
            slug={p.slug}
            name={p.name}
            sku={p.sku}
            unit={p.unit}
            unitLabel={UNITS[p.unit] ?? "Piece"}
            decimal={DECIMAL_UNITS.includes(p.unit)}
            sizes={p.sizes}
            colours={p.colours}
            image={img?.src}
            whatsapp={whatsappUrl(`${p.name} (${p.sku})`) || undefined}
          />
          <p className="atp-help"><strong>Helpful details to include:</strong> {p.spec_prompt}</p>
          <p className="atp-notice">Product images and descriptions identify the listed option. Any proposed alternative must be described separately in the quotation and agreed before supply.</p>
        </div>
      </div>
      {related.length > 0 && (
        <section style={{ marginTop: "3.5rem" }}>
          <div className="section-head">
            <h2>More {sub.name}</h2>
            <a href={subUrl(sub)}>View all {productsInSub(sub.slug).length} →</a>
          </div>
          <div className="grid">
            {related.map((r) => <ProductCard key={r.slug} p={r} />)}
          </div>
        </section>
      )}
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} />
      <div style={{ height: "3rem" }} />
    </div>
  );
}
