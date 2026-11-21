import { adminConfigured, currentAdmin } from "@/lib/auth";
import { login } from "@/lib/admin-actions";
import { listEnquiries } from "@/lib/admin-data";
import { STATUSES, type Status } from "@/lib/enquiry";
import { emailConfigured } from "@/lib/email";
import { storageReady, usingBlob } from "@/lib/store";

const fmt = (iso: string) => new Date(iso).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" });

export default async function AdminHome({ searchParams }: { searchParams: Promise<{ status?: string; q?: string; error?: string }> }) {
  const sp = await searchParams;
  if (!adminConfigured()) {
    return (
      <div className="panel" style={{ maxWidth: 640, margin: "2rem auto" }}>
        <h1 style={{ fontSize: "1.5rem" }}>Staff dashboard is not switched on yet</h1>
        <p>Add an <code>ADMIN_PASSWORD</code> environment variable (and a long random <code>SESSION_SECRET</code>) in the Vercel project settings, then redeploy.</p>
      </div>
    );
  }
  const who = await currentAdmin();
  if (!who) {
    return (
      <form action={login} className="panel atp-form" style={{ maxWidth: 420, margin: "3rem auto" }}>
        <h1 style={{ fontSize: "1.5rem" }}>Staff sign in</h1>
        {sp.error && <p className="atp-alert--error" role="alert">That password is not correct.</p>}
        <div className="field-wrap">
          <label htmlFor="name">Your name</label>
          <input id="name" name="name" type="text" autoComplete="name" required />
        </div>
        <div className="field-wrap">
          <label htmlFor="password">Password</label>
          <input id="password" name="password" type="password" autoComplete="current-password" required />
        </div>
        <button className="btn btn--block" type="submit">Sign in</button>
      </form>
    );
  }
  const all = await listEnquiries();
  const status = sp.status && sp.status in STATUSES ? (sp.status as Status) : "";
  const q = (sp.q || "").toLowerCase().trim();
  const rows = all.filter((e) => (!status || e.status === status) && (!q || JSON.stringify([e.ref, e.contact, e.items.map((i) => i.name), e.freeText]).toLowerCase().includes(q)));
  const today = new Date().toISOString().slice(0, 10);
  return (
    <>
      <h1 style={{ fontSize: "1.75rem" }}>Enquiries</h1>
      {(!usingBlob() || !emailConfigured()) && (
        <p className="atp-notice">
          {!storageReady() ? "Storage is not connected: connect a Vercel Blob store so enquiries can be saved. " : !usingBlob() ? "Saving to the local .data folder (development). " : ""}
          {!emailConfigured() ? "Email alerts are off: set RESEND_API_KEY, EMAIL_FROM and NOTIFY_EMAILS to receive an email for each enquiry." : ""}
        </p>
      )}
      <form className="atp-form no-print" style={{ display: "flex", gap: 8, flexWrap: "wrap", margin: "1rem 0" }}>
        <select name="status" defaultValue={status} style={{ maxWidth: 220 }} aria-label="Status">
          <option value="">All statuses ({all.length})</option>
          {Object.entries(STATUSES).map(([k, v]) => <option key={k} value={k}>{v} ({all.filter((e) => e.status === k).length})</option>)}
        </select>
        <input type="text" name="q" defaultValue={sp.q || ""} placeholder="Search name, phone, item, reference" style={{ maxWidth: 360 }} aria-label="Search" />
        <button className="btn btn--sm" type="submit">Filter</button>
      </form>
      {rows.length === 0 ? (
        <p className="atp-empty">No enquiries yet.</p>
      ) : (
        <div style={{ overflowX: "auto" }}>
          <table>
            <thead><tr><th>Reference</th><th>Received</th><th>Customer</th><th>Requirement</th><th>District</th><th>Status</th><th>Owner</th><th>Follow up</th></tr></thead>
            <tbody>
              {rows.map((e) => (
                <tr key={e.ref}>
                  <td><a href={`/admin/enquiry/${e.ref}/`}><strong>{e.ref}</strong></a></td>
                  <td>{fmt(e.created)}</td>
                  <td>{e.contact.name}{e.contact.org ? <><br /><small>{e.contact.org}</small></> : null}</td>
                  <td>{e.items.length ? `${e.items.length} item${e.items.length > 1 ? "s" : ""}` : ""}{e.freeText ? `${e.items.length ? " + " : ""}written list` : ""}{e.files.length ? ` + ${e.files.length} file${e.files.length > 1 ? "s" : ""}` : ""}</td>
                  <td>{e.contact.district}</td>
                  <td><span className={`status status--${e.status}`}>{STATUSES[e.status]}</span></td>
                  <td>{e.owner || "—"}</td>
                  <td style={e.followUp && e.followUp <= today && !["won", "lost"].includes(e.status) ? { color: "#B42318", fontWeight: 700 } : undefined}>{e.followUp || "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
