import { site } from "@/lib/site";

export type Crumb = { name: string; href?: string };

export default function Crumbs({ items }: { items: Crumb[] }) {
  const all = [{ name: "Home", href: "/" }, ...items];
  const ld = {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: all.map((c, i) => ({ "@type": "ListItem", position: i + 1, name: c.name, ...(c.href ? { item: site.url + c.href } : {}) })),
  };
  return (
    <nav className="crumbs" aria-label="Breadcrumb">
      <ol>
        {all.map((c, i) => (
          <li key={i}>{c.href && i < all.length - 1 ? <a href={c.href}>{c.name}</a> : <span aria-current="page">{c.name}</span>}</li>
        ))}
      </ol>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} />
    </nav>
  );
}
