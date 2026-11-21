import type { Metadata } from "next";
import { pages } from "@/lib/content";
import { DISTRICTS } from "@/lib/enquiry";
import { contactBlockHtml } from "@/lib/site";
import QuoteForm from "@/components/QuoteForm";
import Crumbs from "@/components/Crumbs";

const m = pages["request-a-quote"];
export const metadata: Metadata = { title: m.meta_title, description: m.meta_description, alternates: { canonical: "/request-a-quote/" } };

export default async function RequestQuote({ searchParams }: { searchParams: Promise<{ mode?: string; item?: string }> }) {
  const sp = await searchParams;
  const mode = sp.mode === "type" || sp.mode === "upload" ? sp.mode : "list";
  return (
    <div className="container" style={{ paddingBottom: "4rem" }}>
      <Crumbs items={[{ name: m.title }]} />
      <div style={{ maxWidth: 860 }}>
        <h1>{m.h1}</h1>
        <QuoteForm initialMode={mode} initialItem={String(sp.item || "").slice(0, 100)} districts={DISTRICTS} />
      </div>
      <aside className="cat-help" style={{ maxWidth: 860 }}>
        <h2 style={{ fontSize: "1.25rem" }}>Prefer to talk first?</h2>
        <div dangerouslySetInnerHTML={{ __html: contactBlockHtml() }} />
      </aside>
    </div>
  );
}
