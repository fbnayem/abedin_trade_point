import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { html, pages } from "@/lib/content";
import Crumbs from "@/components/Crumbs";

export const dynamicParams = false;
const SLUGS = Object.keys(pages).filter((s) => s !== "request-a-quote" && pages[s].status === "publish");

export function generateStaticParams() {
  return SLUGS.map((page) => ({ page }));
}

export async function generateMetadata({ params }: { params: Promise<{ page: string }> }): Promise<Metadata> {
  const { page } = await params;
  const m = pages[page];
  if (!m) return {};
  return { title: m.meta_title, description: m.meta_description, alternates: { canonical: `/${page}/` } };
}

export default async function StaticPage({ params }: { params: Promise<{ page: string }> }) {
  const { page } = await params;
  const m = pages[page];
  if (!m || !SLUGS.includes(page)) notFound();
  let body = html(`page-${page}`);
  if (page === "faqs") {
    const ld = {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      mainEntity: [...body.matchAll(/<summary>([\s\S]*?)<\/summary>([\s\S]*?)<\/details>/g)].map((x) => ({
        "@type": "Question",
        name: x[1].replace(/<[^>]+>/g, "").trim(),
        acceptedAnswer: { "@type": "Answer", text: x[2].replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim() },
      })),
    };
    body += `<script type="application/ld+json">${JSON.stringify(ld).replace(/</g, "\\u003c")}</script>`;
  }
  return (
    <div className="container">
      <Crumbs items={[{ name: m.title }]} />
      <div className="narrow">
        <h1>{m.h1}</h1>
      </div>
      <div className="wp-content narrow" style={{ paddingBottom: "4rem" }} dangerouslySetInnerHTML={{ __html: body }} />
    </div>
  );
}
