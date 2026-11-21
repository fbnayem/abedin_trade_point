import { getSubcategory, imageFor, productUrl, type Product } from "@/lib/catalogue";
import ProductImage from "./ProductImage";
import AddButton from "./AddButton";

export default function ProductCard({ p, showSub = false }: { p: Product; showSub?: boolean }) {
  const sub = getSubcategory(p.subcategory);
  const hasOptions = p.sizes.length > 0 || p.colours.length > 0;
  const img = imageFor(p.slug);
  return (
    <article className="pcard">
      <div className="pcard__img">
        <ProductImage category={p.category} alt={p.name} credit={img} />
      </div>
      <div className="pcard__body">
        {showSub && sub && <span className="pcard__sub">{sub.name}</span>}
        <h3 className="pcard__name">
          <a href={productUrl(p)}>{p.name}</a>
        </h3>
        <span className="pcard__price">Price on quotation</span>
        <div className="pcard__actions">
          {hasOptions ? (
            <a className="btn btn--sm" href={productUrl(p)}>Choose Options</a>
          ) : (
            <AddButton slug={p.slug} name={p.name} sku={p.sku} unit={p.unit} image={img?.src} />
          )}
          {!hasOptions && (
            <a className="pcard__more" href={productUrl(p)}>View Specifications</a>
          )}
        </div>
      </div>
    </article>
  );
}
