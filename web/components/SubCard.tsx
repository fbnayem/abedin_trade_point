import { productsInSub, subImage, subUrl, type Subcategory } from "@/lib/catalogue";
import ProductImage from "./ProductImage";

export default function SubCard({ s, eager = false }: { s: Subcategory; eager?: boolean }) {
  const n = productsInSub(s.slug).length;
  return (
    <a className="scard" href={subUrl(s)}>
      <div className="scard__img">
        <ProductImage category={s.category} alt="" credit={subImage(s)} eager={eager} />
      </div>
      <div className="scard__body">
        <span className="scard__name">{s.name}</span>
        <span className="scard__count">{n} product types</span>
        <span className="scard__cta">Browse {s.name} →</span>
      </div>
    </a>
  );
}
