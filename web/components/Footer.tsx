import { categories, categoryUrl } from "@/lib/catalogue";
import { contactBlockHtml, site } from "@/lib/site";

export default function Footer() {
  return (
    <footer className="atp-footer">
      <div className="container">
        <div className="atp-footer__cols">
          <div>
            <p style={{ fontWeight: 800, fontSize: "1.25rem", marginBottom: ".5rem" }}>{site.name}</p>
            <p className="has-muted-color">Bulk sourcing and supply for organisations in Bangladesh. Explore products or send your purchase list for a quotation.</p>
          </div>
          <div>
            <h4>Products</h4>
            <ul className="links">
              {categories.map((c) => (
                <li key={c.slug}><a href={categoryUrl(c)}>{c.name}</a></li>
              ))}
            </ul>
          </div>
          <div>
            <h4>Explore</h4>
            <ul className="links">
              <li><a href="/products/">All Products</a></li>
              <li><a href="/bulk-supply/">Bulk Supply</a></li>
              <li><a href="/custom-branding/">Custom Branding</a></li>
              <li><a href="/industries/">Industries</a></li>
              <li><a href="/about/">About</a></li>
              <li><a href="/faqs/">FAQs</a></li>
              <li><a href="/ordering-delivery/">Ordering &amp; Delivery</a></li>
              <li><a href="/privacy/">Privacy</a></li>
              <li><a href="/image-credits/">Image Credits</a></li>
            </ul>
          </div>
          <div>
            <h4>Contact</h4>
            <div dangerouslySetInnerHTML={{ __html: contactBlockHtml() }} />
            <p style={{ marginTop: "1rem" }}><a className="btn btn--sm" href="/contact/">Contact Our Team</a></p>
          </div>
        </div>
        <p className="atp-copyright">© {new Date().getFullYear()} {site.name}. All rights reserved.</p>
      </div>
    </footer>
  );
}
