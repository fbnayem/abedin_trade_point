import { imageFor, type Credit } from "@/lib/catalogue";

const ICONS: Record<string, string> = {
  "garments-uniforms-workwear": "M16 6l-6 4 3 5 3-2v15h16V13l3 2 3-5-6-4c-1 2-3 3-5 3s-4-1-5-3z",
  "bags-backpacks": "M12 14h24l-2 20H14zM18 14c0-4 2-7 6-7s6 3 6 7",
  "stationery-office-supplies": "M10 8h22v30H10zM14 14h14M14 19h14M14 24h10M36 8l4 2-8 26-3 1-1-3z",
  "paper-printing": "M12 6h18l6 6v28H12zM30 6v6h6M17 20h14M17 25h14M17 30h9",
  "corporate-gifts": "M9 17h30v6H9zM11 23h26v16H11zM24 17v22M24 17c-3-6-9-7-9-3s6 3 9 3c3 0 9 1 9-3s-6-3-9 3",
  "packaging-materials": "M8 16l16-8 16 8v18l-16 8-16-8zM8 16l16 8 16-8M24 24v18",
  "cleaning-hygiene": "M20 6h8v8h-8zM18 14h12l3 6v20H15V20zM20 26h8",
  "office-institutional-furniture": "M14 8h20v14H14zM12 22h24v4H12zM16 26v14M32 26v14M24 26v8",
  "hospital-medical-consumables": "M20 8h8v12h12v8H28v12h-8V28H8v-8h12z",
};

export function Art({ category, label }: { category: string; label?: string }) {
  const d = ICONS[category] || ICONS["packaging-materials"];
  return (
    <div className="art" aria-hidden={label ? undefined : true}>
      <div>
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" strokeLinecap="round">
          <path d={d} />
        </svg>
        {label && <span>{label}</span>}
      </div>
    </div>
  );
}

export default function ProductImage({
  slug,
  category,
  alt,
  credit,
  eager = false,
  label,
}: {
  slug?: string;
  category: string;
  alt: string;
  credit?: Credit | null;
  eager?: boolean;
  label?: string;
}) {
  const c = credit === undefined ? (slug ? imageFor(slug) : null) : credit;
  if (!c) return <Art category={category} label={label} />;
  return <img src={c.src} alt={alt} width={900} height={900} loading={eager ? "eager" : "lazy"} decoding="async" />;
}
