import CategoryHelp from "@/components/CategoryHelp";
import SiteLayout from "./(site)/layout";

export default function NotFound() {
  return (
    <SiteLayout>
    <div className="container" style={{ paddingBlock: "3rem" }}>
      <h1>Page Not Found</h1>
      <p className="lead">The page you are looking for may have moved. Search the catalogue or browse all products.</p>
      <form className="atp-search" action="/search/" role="search" style={{ maxWidth: 640, margin: "1rem 0 1.5rem" }}>
        <label className="sr-only" htmlFor="nq">Search products</label>
        <input id="nq" type="search" name="q" placeholder="Search products" />
        <button className="btn" type="submit">Search</button>
      </form>
      <p><a className="btn btn--outline" href="/products/">Browse Products</a></p>
      <CategoryHelp />
    </div>
    </SiteLayout>
  );
}
