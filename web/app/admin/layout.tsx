import type { Metadata } from "next";
import { currentAdmin } from "@/lib/auth";
import { logout } from "@/lib/admin-actions";

export const metadata: Metadata = { title: "Staff Dashboard | Abedin Trade Point", robots: { index: false, follow: false } };
export const dynamic = "force-dynamic";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const who = await currentAdmin();
  return (
    <>
      <header className="atp-header no-print">
        <div className="container atp-header__top">
          <a className="atp-brand" href="/admin/"><img src="/img/logo-placeholder.svg" alt="" width={36} height={36} /> Staff Dashboard</a>
          <nav className="atp-nav" style={{ display: "flex" }}>
            <a href="/" target="_blank">View website</a>
            {who && (
              <form action={logout}>
                <button className="atp-link-btn" type="submit">Sign out ({who})</button>
              </form>
            )}
          </nav>
        </div>
      </header>
      <main className="container admin" id="main">{children}</main>
    </>
  );
}
