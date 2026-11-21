import { notFound, redirect } from "next/navigation";
import { currentAdmin } from "@/lib/auth";
import { getEnquiry } from "@/lib/admin-data";
import { resendNotifications, updateEnquiry } from "@/lib/admin-actions";
import { STATUSES, quotationNumber, totals, money } from "@/lib/enquiry";
import { UNITS } from "@/lib/catalogue";

const fmt = (iso: string) => new Date(iso).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "medium", timeStyle: "short" });

export default async function EnquiryPage({ params, searchParams }: { params: Promise<{ ref: string }>; searchParams: Promise<{ saved?: string }> }) {
  if (!(await currentAdmin())) redirect("/admin/");
  const { ref } = await params;
  const sp = await searchParams;
  const e = await getEnquiry(ref);
  if (!e) notFound();
  const c = e.contact;
  const wa = c.phone.replace(/\D/g, "");
  return (
    <>
      <p><a href="/admin/">← All enquiries</a></p>
      <h1 style={{ fontSize: "1.75rem" }}>{e.ref} <span className={`status status--${e.status}`}>{STATUSES[e.status]}</span></h1>
      {sp.saved && <p className="atp-notice" role="status">Saved.</p>}
      <div className="admin-grid">
        <div>
          <section className="panel">
            <h2>Requested items</h2>
            {e.items.length > 0 ? (
              <table>
                <thead><tr><th>Item</th><th>Options</th><th>Qty</th><th>Note</th></tr></thead>
                <tbody>
                  {e.items.map((i, n) => (
                    <tr key={n}>
                      <td><a href={`/product/${i.slug}/`} target="_blank">{i.name}</a><br /><small>{i.sku}</small></td>
                      <td>{[i.size && `Size: ${i.size}`, i.colour && `Colour: ${i.colour}`].filter(Boolean).join(", ") || "—"}</td>
                      <td>{i.qty} {(UNITS[i.unit] || i.unit).toLowerCase()}</td>
                      <td>{i.note || "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : <p className="has-muted-color">No catalogue items.</p>}
            {e.freeText && (<><h3 style={{ marginTop: "1rem" }}>Written requirement</h3><pre style={{ whiteSpace: "pre-wrap", font: "inherit" }}>{e.freeText}</pre></>)}
            {e.specs && (<><h3 style={{ marginTop: "1rem" }}>Specifications</h3><pre style={{ whiteSpace: "pre-wrap", font: "inherit" }}>{e.specs}</pre></>)}
            {e.files.length > 0 && (
              <>
                <h3 style={{ marginTop: "1rem" }}>Attachments</h3>
                <ul>
                  {e.files.map((f, n) => (
                    <li key={n}><a href={`/admin/file/?ref=${e.ref}&i=${n}`}>{f.name}</a> <small>({(f.size / 1024 / 1024).toFixed(2)} MB)</small></li>
                  ))}
                </ul>
              </>
            )}
          </section>
          <section className="panel">
            <h2>Quotation</h2>
            {e.quotation ? (
              <p>{quotationNumber(e)} · revision {e.quotation.rev} · {e.quotation.date} · total <strong>{money(totals(e.quotation).total)}</strong></p>
            ) : <p className="has-muted-color">No quotation yet.</p>}
            <p style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
              <a className="btn btn--sm" href={`/admin/enquiry/${e.ref}/quotation/`}>{e.quotation ? "Edit quotation" : "Prepare quotation"}</a>
              {e.quotation && <a className="btn btn--sm btn--outline" href={`/admin/enquiry/${e.ref}/print/`} target="_blank">Print or save as PDF</a>}
            </p>
          </section>
          <section className="panel">
            <h2>Notes</h2>
            {e.notes.length ? (
              <ul style={{ listStyle: "none", padding: 0 }}>
                {e.notes.map((n, i) => <li key={i} style={{ padding: "8px 0", borderBottom: "1px solid var(--line)" }}><small className="has-muted-color">{fmt(n.at)} · {n.by}</small><br />{n.text}</li>)}
              </ul>
            ) : <p className="has-muted-color">No notes yet.</p>}
          </section>
          <section className="panel">
            <h2>History</h2>
            <ul style={{ paddingLeft: "1.1rem" }}>
              {[...e.history].reverse().map((h, i) => <li key={i}><small className="has-muted-color">{fmt(h.at)}</small> {h.text}</li>)}
            </ul>
          </section>
        </div>
        <div>
          <section className="panel">
            <h2>Customer</h2>
            <dl className="kv">
              <dt>Name</dt><dd>{c.name}</dd>
              <dt>Organisation</dt><dd>{c.org || "—"}</dd>
              <dt>Phone</dt><dd>{c.phone ? <a href={`tel:${c.phone.replace(/[^0-9+]/g, "")}`}>{c.phone}</a> : "—"}{wa && <> · <a href={`https://wa.me/${wa.startsWith("0") ? "88" + wa : wa}`} target="_blank" rel="noopener">WhatsApp</a></>}</dd>
              <dt>Email</dt><dd>{c.email ? <a href={`mailto:${c.email}?subject=${encodeURIComponent("Your supply enquiry " + e.ref)}`}>{c.email}</a> : "—"}</dd>
              <dt>Prefers</dt><dd>{c.preferred || "No preference"}</dd>
              <dt>District</dt><dd>{c.district}</dd>
              <dt>Delivery date</dt><dd>{c.deliveryDate || "—"}</dd>
              <dt>Quote needed by</dt><dd>{c.deadline || "—"}</dd>
              <dt>Budget</dt><dd>{c.budget || "—"}</dd>
              <dt>Received</dt><dd>{fmt(e.created)}</dd>
            </dl>
          </section>
          <form action={updateEnquiry} className="panel atp-form">
            <h2>Manage</h2>
            <input type="hidden" name="ref" value={e.ref} />
            <div className="field-wrap">
              <label htmlFor="status">Status</label>
              <select id="status" name="status" defaultValue={e.status}>
                {Object.entries(STATUSES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
            </div>
            <div className="field-wrap">
              <label htmlFor="owner">Owner</label>
              <input id="owner" name="owner" type="text" defaultValue={e.owner} placeholder="Who is handling this?" />
            </div>
            <div className="field-wrap">
              <label htmlFor="followUp">Follow-up date</label>
              <input id="followUp" name="followUp" type="date" defaultValue={e.followUp} />
            </div>
            <div className="field-wrap">
              <label htmlFor="note">Add a note</label>
              <textarea id="note" name="note" rows={3} />
            </div>
            <button className="btn btn--block" type="submit">Save</button>
          </form>
          <form action={resendNotifications} className="panel">
            <h2>Email alerts</h2>
            <input type="hidden" name="ref" value={e.ref} />
            <p>Status: <strong>{e.notify.status === "off" ? "Not set up" : e.notify.status}</strong>{e.notify.error ? <><br /><small>{e.notify.error}</small></> : null}</p>
            {e.notify.status !== "sent" && <button className="btn btn--sm btn--outline" type="submit">Send notifications again</button>}
          </form>
        </div>
      </div>
    </>
  );
}
