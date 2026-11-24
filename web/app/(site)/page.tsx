import { categories, categoryUrl, featuredSubs, imageFor, productsInSub, subcategories, subImage, subUrl, getProduct } from "@/lib/catalogue";
import { html } from "@/lib/content";
import SubCard from "@/components/SubCard";
import ProductCard from "@/components/ProductCard";
import ProductImage from "@/components/ProductImage";

function swapPlaceholder(source: string, slug: string, alt: string) {
  const c = imageFor(slug);
  if (!c) return source;
  return source.replace(/<div class="wp-block-group atp-photo-placeholder">[\s\S]*?<\/div>/, `<img src="${c.src}" alt="${alt}" style="border-radius:10px;width:100%;aspect-ratio:4/3;object-fit:cover" loading="lazy">`);
}

export default function Home() {
  const collage = featuredSubs().slice(0, 4);
  const popular = subcategories.slice(0, 15);
  const spotlight = featuredSubs()
    .map((s) => productsInSub(s.slug)[1] ?? productsInSub(s.slug)[0])
    .filter(Boolean)
    .slice(0, 8);
  const branding = swapPlaceholder(html("pattern-custom-branding"), getProduct("heavy-canvas-tote-bag") ? "heavy-canvas-tote-bag" : "round-neck-cotton-t-shirt", "Branded bags and T-shirts");
  return (
    <>
      <section className="hero">
        <div className="container hero__grid">
          <div>
            <p className="atp-eyebrow">Bulk Supply &amp; Procurement</p>
            <h1>Bulk Supplies for Businesses, Institutions &amp; Projects</h1>
            <p className="has-muted-color has-medium-font-size">
              Source garments, bags, stationery and other business essentials through Abedin Trade Point. Send your product list, quantities and delivery requirements to request a quotation built around your needs.
            </p>
            <div className="wp-block-buttons">
              <a className="btn" href="/request-a-quote/">Request a Quote</a>
              <a className="btn btn--outline" href="/products/">Browse Products</a>
            </div>
            <p style={{ marginTop: "1rem" }}>Already have a list? <a href="/request-a-quote/?mode=upload">Upload Your Purchase List</a></p>
            <p className="has-logo-grey-color has-small-font-size" style={{ fontWeight: 500 }}>Multiple items. One enquiry. Clear specifications before you order.</p>
          </div>
          <div className="hero__collage">
            {collage.map((s, i) => (
              <a key={s.slug} href={subUrl(s)}>
                <ProductImage category={s.category} alt={s.name} credit={subImage(s)} eager={i < 2} />
                <span>{s.name}</span>
              </a>
            ))}
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="section-head">
            <div>
              <h2>Shop by Category</h2>
              <p>Pick a category to see every product type we can quote for, then add the items and quantities you need to one quote list.</p>
            </div>
            <a className="btn btn--outline" href="/products/">View All {subcategories.length} Categories</a>
          </div>
          <div className="grid grid--subs">
            {popular.map((s, i) => (
              <SubCard key={s.slug} s={s} eager={i < 4} />
            ))}
          </div>
        </div>
      </section>

      <section className="section section--pale">
        <div className="container">
          <div className="section-head">
            <div>
              <h2>Start Your Quote List</h2>
              <p>Select the products you are interested in, add the quantities you need and send them together in one request.</p>
            </div>
          </div>
          <div className="grid">
            {spotlight.map((p) => (
              <ProductCard key={p.slug} p={p} showSub />
            ))}
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="section-head">
            <div>
              <h2>Find the Supplies You Need</h2>
              <p>Explore our main supply departments, then tell us what you need in the quantity, size and finish that works for your organisation.</p>
            </div>
          </div>
          <ul className="chips">
            {categories.map((c) => (
              <li key={c.slug}><a href={categoryUrl(c)}>{c.name}</a></li>
            ))}
          </ul>
        </div>
      </section>

      <div className="wp-content container" dangerouslySetInnerHTML={{ __html: html("pattern-purchase-list") + html("pattern-reasons") + html("pattern-how-it-works") + branding + html("pattern-final-cta") }} />
    </>
  );
}
