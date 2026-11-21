import { categories, subsInCategory, subUrl, categoryUrl } from "@/lib/catalogue";
import { site } from "@/lib/site";
import HeaderNav, { type MenuGroup } from "./HeaderNav";
import QuoteCount from "./QuoteCount";

export default function Header() {
  const menu: MenuGroup[] = categories.map((c) => ({
    name: c.name,
    href: categoryUrl(c),
    links: subsInCategory(c.slug).map((s) => ({ name: s.name, href: subUrl(s) })),
  }));
  return (
    <header className="atp-header">
      <div className="container" style={{ position: "relative" }}>
        <div className="atp-header__top">
          <a className="atp-brand" href="/">
            <img src={site.logo || "/img/logo-placeholder.svg"} alt="" width={44} height={44} />
            <span>{site.name}</span>
          </a>
          <HeaderNav menu={menu} />
        </div>
        <div className="atp-header__tools">
          <form className="atp-search" action="/search/" role="search">
            <label className="sr-only" htmlFor="atp-q">Search products</label>
            <input id="atp-q" type="search" name="q" placeholder="Search products, e.g. T-shirt, jute bag, exercise book" />
            <button className="btn" type="submit">Search</button>
          </form>
          <QuoteCount />
          <a className="btn" href="/request-a-quote/">Request a Quote</a>
        </div>
      </div>
    </header>
  );
}
