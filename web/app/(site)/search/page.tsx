import type { Metadata } from "next";
import { search, subcategories, subUrl } from "@/lib/catalogue";
import ProductCard from "@/components/ProductCard";
import Crumbs from "@/components/Crumbs";
import CategoryHelp from "@/components/CategoryHelp";

export const metadata: Metadata = { title: "Search Products | Abedin Trade Point", robots: { index: false, follow: true } };

export default async function SearchPage({ searchParams }: { searchParams: Promise<{ q?: string }> }) {
  const { q = "" } = await searchParams;
  const query = String(q).slice(0, 100).trim();
  const results = query ? search(query) : [];
  const subs = query ? subcategories.filter((s) => s.name.toLowerCase().includes(query.toLowerCase().replace(/s$/, ""))) : [];
  return (
    <div className="container">
      <Crumbs items={[{ name: "Search" }]} />
      <h1>{query ? `Search results for “${query}”` : "Search products"}</h1>
      <form className="atp-search" action="/search/" role="search" style={{ maxWidth: 640, margin: "1rem 0 1.5rem" }}>
        <label className="sr-only" htmlFor="sq">Search products</label>
        <input id="sq" type="search" name="q" defaultValue={query} placeholder="e.g. polo shirt, A4 paper, jute bag" />
        <button className="btn" type="submit">Search</button>
      </form>
      {subs.length > 0 && (
        <ul className="chips" aria-label="Matching categories">
          {subs.map((s) => <li key={s.slug}><a href={subUrl(s)}>{s.name} category</a></li>)}
        </ul>
      )}
      {query && (
        <p className="toolbar">{results.length ? `${results.length} matching product${results.length === 1 ? "" : "s"}` : "No products matched your search."}</p>
      )}
      {results.length > 0 ? (
        <div className="grid">
          {results.slice(0, 48).map((p) => <ProductCard key={p.slug} p={p} showSub />)}
        </div>
      ) : query ? (
        <div className="atp-empty">
          <p>We could not find “{query}” in the catalogue, but we may still be able to source it.</p>
          <p><a className="btn" href={`/request-a-quote/?mode=type&item=${encodeURIComponent(query)}`}>Request “{query}” as an Unlisted Item</a></p>
        </div>
      ) : null}
      <CategoryHelp />
      <div style={{ height: "3rem" }} />
    </div>
  );
}
