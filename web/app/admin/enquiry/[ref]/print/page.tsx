import { notFound, redirect } from "next/navigation";
import { currentAdmin } from "@/lib/auth";
import { getEnquiry } from "@/lib/admin-data";
import { money, quotationNumber, totals } from "@/lib/enquiry";
import { site, placeholder } from "@/lib/site";
import PrintButton from "@/components/PrintButton";

export default async function QuotationPrint({ params }: { params: Promise<{ ref: string }> }) {
  if (!(await currentAdmin())) redirect("/admin/");
  const { ref } = await params;
  const e = await getEnquiry(ref);
  if (!e) notFound();
  const q = e.quotation;
  if (!q) redirect(`/admin/enquiry/${ref}/quotation/`);
  const t = totals(q);
  const from = [site.address || placeholder("address"), site.phone || placeholder("phone"), site.email || placeholder("email")].filter(Boolean).join("<br>");
  return (
    <div className="qdoc">
      <style>{`
        .qdoc{max-width:820px;margin:0 auto;font-size:14px}
        .qdoc header{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #1F5F99;padding-bottom:16px;margin-bottom:24px}
        .qdoc .brand{font-size:22px;font-weight:800;color:#1F5F99}.qdoc .from{font-size:12px;color:#50575e}
        .qdoc .doc{text-align:right}.qdoc .doc h1{margin:0 0 6px;font-size:24px;letter-spacing:.04em}
        .qdoc .cols{display:flex;gap:24px;margin-bottom:24px}.qdoc .cols>div{flex:1}.qdoc .label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#646970;margin:0 0 4px}
        .qdoc table{width:100%;border-collapse:collapse;margin-bottom:16px}.qdoc th,.qdoc td{border-bottom:1px solid #dcdcde;padding:8px;text-align:left;vertical-align:top}
        .qdoc th{background:#f6f7f7;font-size:12px;text-transform:none;letter-spacing:0;color:#1d2327}.qdoc .n{text-align:right;white-space:nowrap}.qdoc .spec{font-size:12px;color:#50575e;white-space:pre-line}
        .qdoc .totals{margin-left:auto;width:320px}.qdoc .totals td{border:0;padding:4px 8px}.qdoc .totals .grand td{border-top:2px solid #1d2327;font-weight:700;font-size:16px}
        .qdoc .terms p{margin:4px 0}.qdoc .foot{margin-top:32px;font-size:12px;color:#646970;border-top:1px solid #dcdcde;padding-top:12px}
        @media print{.admin{padding:0}@page{margin:16mm}}
      `}</style>
      <p className="no-print" style={{ display: "flex", gap: 8 }}><PrintButton /> <a className="btn btn--outline btn--sm" href={`/admin/enquiry/${e.ref}/quotation/`}>Edit</a></p>
      <header>
        <div>
          <div className="brand">{site.name}</div>
          <div className="from" dangerouslySetInnerHTML={{ __html: from }} />
        </div>
        <div className="doc">
          <h1>QUOTATION</h1>
          <div>{quotationNumber(e)}</div>
          <div>Date: {q.date}</div>
          <div>Enquiry: {e.ref}</div>
        </div>
      </header>
      <div className="cols">
        <div>
          <p className="label">Prepared for</p>
          <strong>{e.contact.name}</strong>
          {e.contact.org && <div>{e.contact.org}</div>}
          {e.contact.phone && <div>{e.contact.phone}</div>}
          {e.contact.email && <div>{e.contact.email}</div>}
          <div>{e.contact.district}</div>
        </div>
        <div>
          <p className="label">Terms</p>
          {q.validity && <div>Valid for: {q.validity}</div>}
          {q.lead && <div>Delivery: {q.lead}</div>}
          {q.payment && <div>Payment: {q.payment}</div>}
          {e.owner && <div>Contact: {e.owner}</div>}
        </div>
      </div>
      <table>
        <thead><tr><th>#</th><th>Item and specification</th><th className="n">Qty</th><th>Unit</th><th className="n">Unit price</th><th className="n">Amount</th></tr></thead>
        <tbody>
          {q.lines.map((l, i) => (
            <tr key={i}>
              <td>{i + 1}</td>
              <td><strong>{l.item}</strong>{l.spec && <div className="spec">{l.spec}</div>}</td>
              <td className="n">{l.qty}</td>
              <td>{l.unit}</td>
              <td className="n">{l.price !== "" ? money(Number(l.price)) : "—"}</td>
              <td className="n">{l.price !== "" ? money((Number(l.qty) || 0) * Number(l.price)) : "—"}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <table className="totals">
        <tbody>
          <tr><td>Subtotal</td><td className="n">{money(t.subtotal)}</td></tr>
          {t.delivery > 0 && <tr><td>Delivery</td><td className="n">{money(t.delivery)}</td></tr>}
          {Number(q.vat) > 0 && <tr><td>VAT {q.vat}%</td><td className="n">{money(t.vat)}</td></tr>}
          <tr className="grand"><td>Total</td><td className="n">{money(t.total)}</td></tr>
        </tbody>
      </table>
      {q.notes && <div className="terms"><p className="label">Notes</p><p style={{ whiteSpace: "pre-line" }}>{q.notes}</p></div>}
      <div className="foot">
        Products are supplied as described in this quotation. Any alternative must be agreed in writing before supply. Prices are in Bangladeshi Taka.
      </div>
    </div>
  );
}
