"use client";
import { useEffect, useRef, useState } from "react";
import { usePathname } from "next/navigation";

export type MenuGroup = { name: string; href: string; links: { name: string; href: string }[] };

const LINKS = [
  { name: "Bulk Supply", href: "/bulk-supply/" },
  { name: "Custom Branding", href: "/custom-branding/" },
  { name: "Industries", href: "/industries/" },
  { name: "About", href: "/about/" },
  { name: "Contact", href: "/contact/" },
];

export default function HeaderNav({ menu }: { menu: MenuGroup[] }) {
  const path = usePathname() || "/";
  const [open, setOpen] = useState(false);
  const [prodOpen, setProdOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setOpen(false);
    setProdOpen(false);
  }, [path]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setOpen(false);
        setProdOpen(false);
      }
    };
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setProdOpen(false);
    };
    document.addEventListener("keydown", onKey);
    document.addEventListener("click", onClick);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("click", onClick);
    };
  }, []);

  const cur = (href: string) => (href === "/" ? path === "/" : path.startsWith(href)) ? "page" : undefined;

  return (
    <>
      <button type="button" className="atp-burger" aria-expanded={open} aria-controls="atp-nav" onClick={() => setOpen(!open)}>
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
        Menu
      </button>
      <nav id="atp-nav" className={`atp-nav${open ? " is-open" : ""}`} aria-label="Main">
        <a href="/" aria-current={cur("/")}>Home</a>
        <div className={`atp-menu${prodOpen ? " is-open" : ""}`} ref={ref}>
          <button type="button" aria-expanded={prodOpen} aria-controls="atp-products-menu" onClick={() => setProdOpen(!prodOpen)}>
            Products
            <svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" strokeWidth="2.5" fill="none" /></svg>
          </button>
          <div className="atp-menu__panel" id="atp-products-menu">
            {menu.map((g) => (
              <div key={g.href}>
                <h3><a href={g.href}>{g.name}</a></h3>
                <ul>
                  {g.links.map((l) => (
                    <li key={l.href}><a href={l.href}>{l.name}</a></li>
                  ))}
                </ul>
              </div>
            ))}
            <a className="atp-menu__all" href="/products/">View all products and categories →</a>
          </div>
        </div>
        {LINKS.map((l) => (
          <a key={l.href} href={l.href} aria-current={cur(l.href)}>{l.name}</a>
        ))}
      </nav>
    </>
  );
}
