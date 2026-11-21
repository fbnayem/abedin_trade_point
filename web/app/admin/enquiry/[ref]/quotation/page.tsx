import { notFound, redirect } from "next/navigation";
import { currentAdmin } from "@/lib/auth";
import { getEnquiry } from "@/lib/admin-data";
import { saveQuotation } from "@/lib/admin-actions";
import { quotationNumber, type QuoteLine } from "@/lib/enquiry";
import { UNITS } from "@/lib/catalogue";
import QuoteLines from "@/components/QuoteLines";

export default async function QuotationBuilder({ params, searchParams }: { params: Promise<{ ref: string }>; searchParams: Promise<{ saved?: string }> }) {
  if (!(await currentAdmin())) redirect("/admin/");
  const { ref } = await params;
  const sp = await searchParams;
  const e = await getEnquiry(ref);
  if (!e) notFound();
  const q = e.quotation;
  const lines: QuoteLine[] = q?.lines.length
    ? q.lines
    : e.items.length
      ? e.items.map((i) => ({ item: i.name, spec: [i.size && `Size: ${i.size}`, i.colour && `Colour: ${i.colour}`, i.note].filter(Boolean).join("; "), qty: i.qty, unit: UNITS[i.unit] || i.unit, price: "" }))
      : [{ item: "", spec: "", qty: "", unit: "", price: "" }];
  return (
    <>
      <p><a href={`/admin/enquiry/${e.ref}/`}>← Back to {e.ref}</a></p>
      <h1 style={{ fontSize: "1.75rem" }}>Quotation for {e.contact.name}{e.contact.org ? `, ${e.contact.org}` : ""}</h1>
      {sp.saved && <p className="atp-notice" role="status">Saved{q ? ` as ${quotationNumber(e)}` : ""}. <a href={`/admin/enquiry/${e.ref}/print/`} target="_blank">Open print view / save as PDF</a></p>}
      <p className="atp-help">Prefilled from the buyer&rsquo;s quote list. Add prices and terms, save, then open the print view to save the quotation as PDF. Leave a line&rsquo;s item empty to remove it.</p>
      {e.freeText && <details className="panel"><summary>Buyer&rsquo;s written requirement</summary><pre style={{ whiteSpace: "pre-wrap", font: "inherit" }}>{e.freeText}</pre></details>}
      <form action={saveQuotation} className="panel atp-form">
        <input type="hidden" name="ref" value={e.ref} />
        <QuoteLines initial={lines} delivery={q?.delivery ?? ""} vat={q?.vat ?? ""} />
        <div className="atp-grid">
          <div className="field-wrap"><label htmlFor="validity">Valid for</label><input id="validity" name="validity" type="text" defaultValue={q?.validity ?? "15 days"} /></div>
          <div className="field-wrap"><label htmlFor="lead">Delivery / lead time</label><input id="lead" name="lead" type="text" defaultValue={q?.lead ?? ""} /></div>
          <div className="field-wrap"><label htmlFor="payment">Payment terms</label><input id="payment" name="payment" type="text" defaultValue={q?.payment ?? ""} /></div>
        </div>
        <div className="field-wrap"><label htmlFor="notes">Notes and terms</label><textarea id="notes" name="notes" rows={3} defaultValue={q?.notes ?? ""} /></div>
        <p><label style={{ display: "flex", gap: 8, fontWeight: 500 }}><input type="checkbox" name="markQuoted" value="1" /> Set status to Quoted when saving</label></p>
        <button className="btn" type="submit">Save quotation</button>
      </form>
    </>
  );
}
