"use client";
import { useEffect, useMemo, useRef, useState } from "react";
import { clearQuote, onQuoteChange, readQuote, writeQuote, type QuoteItem } from "@/lib/quote-store";

type Mode = "list" | "type" | "upload";
type Up = { id: string; name: string; size: number; state: "uploading" | "done" | "bad"; pathname?: string; type: string; error?: string };

const ALLOWED = ["pdf", "xlsx", "docx", "jpg", "jpeg", "png"];
const MAX = 10 * 1024 * 1024;
const UNIT_LABEL: Record<string, string> = { piece: "Piece", pack: "Pack", set: "Set", ream: "Ream", roll: "Roll", pair: "Pair", box: "Box", pad: "Pad", carton: "Carton", metre: "Metre", kg: "Kilogram" };

function uid() {
  return typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

export default function QuoteForm({ initialMode, initialItem, districts }: { initialMode: Mode; initialItem: string; districts: string[] }) {
  const [mode, setMode] = useState<Mode>(initialMode);
  const [items, setItems] = useState<QuoteItem[]>([]);
  const [loaded, setLoaded] = useState(false);
  const [freeText, setFreeText] = useState(initialItem ? `${initialItem}, ` : "");
  const [uploads, setUploads] = useState<Up[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<{ ref: string; email: string } | null>(null);
  const token = useRef(uid());
  const firedEvent = useRef(false);
  const errRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const load = () => setItems(readQuote());
    load();
    setLoaded(true);
    return onQuoteChange(load);
  }, []);

  useEffect(() => {
    const url = new URL(window.location.href);
    url.searchParams.set("mode", mode);
    url.searchParams.delete("item");
    window.history.replaceState(null, "", url.toString());
  }, [mode]);

  const updateItem = (key: string, patch: Partial<QuoteItem>) => {
    const next = items.map((i) => (i.key === key ? { ...i, ...patch } : i));
    setItems(next);
    writeQuote(next);
  };
  const removeItem = (key: string) => {
    const next = items.filter((i) => i.key !== key);
    setItems(next);
    writeQuote(next);
  };

  async function addFiles(list: FileList | null) {
    if (!list) return;
    const room = 5 - uploads.filter((u) => u.state !== "bad").length;
    const files = Array.from(list).slice(0, Math.max(0, room));
    const modeRes = await fetch("/api/upload/").then((r) => r.json()).catch(() => ({ mode: "off" }));
    for (const file of files) {
      const id = uid();
      const ext = file.name.split(".").pop()?.toLowerCase() || "";
      const base: Up = { id, name: file.name, size: file.size, state: "uploading", type: file.type };
      if (!ALLOWED.includes(ext) || file.size > MAX || file.size === 0) {
        setUploads((u) => [...u, { ...base, state: "bad", error: `${file.name} could not be uploaded. Check the file type and 10 MB limit, then try again.` }]);
        continue;
      }
      setUploads((u) => [...u, base]);
      try {
        let pathname = "";
        const safe = file.name.replace(/[^\w.\- ]+/g, "_").slice(-120);
        if (modeRes.mode === "blob") {
          const { upload } = await import("@vercel/blob/client");
          const folder = uid().replace(/-/g, "").slice(0, 24);
          const r = await upload(`uploads/${folder}/${safe}`, file, { access: "private", handleUploadUrl: "/api/upload/", contentType: file.type || undefined });
          pathname = r.pathname;
        } else if (modeRes.mode === "local") {
          const fd = new FormData();
          fd.append("file", file, safe);
          const r = await fetch("/api/upload/", { method: "POST", body: fd });
          if (!r.ok) throw new Error("upload failed");
          pathname = (await r.json()).pathname;
        } else {
          throw new Error("uploads off");
        }
        setUploads((u) => u.map((x) => (x.id === id ? { ...x, state: "done", pathname } : x)));
      } catch {
        setUploads((u) => u.map((x) => (x.id === id ? { ...x, state: "bad", error: `${file.name} could not be uploaded. Check the file type and 10 MB limit, then try again.` } : x)));
      }
    }
  }

  async function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (busy) return;
    const f = new FormData(e.currentTarget);
    const goodFiles = uploads.filter((u) => u.state === "done");
    const payload = {
      token: token.current,
      mode,
      items: items.map(({ slug, name, sku, unit, qty, size, colour, note }) => ({ slug, name, sku, unit, qty, size, colour, note })),
      freeText,
      specs: String(f.get("specs") || ""),
      files: goodFiles.map((u) => ({ pathname: u.pathname, name: u.name, size: u.size, type: u.type })),
      name: String(f.get("name") || ""),
      org: String(f.get("org") || ""),
      phone: String(f.get("phone") || ""),
      email: String(f.get("email") || ""),
      preferred: String(f.get("preferred") || ""),
      district: String(f.get("district") || ""),
      deliveryDate: String(f.get("deliveryDate") || ""),
      deadline: String(f.get("deadline") || ""),
      budget: String(f.get("budget") || ""),
      privacy: f.get("privacy") === "1",
      website: String(f.get("website") || ""),
    };
    // Same checks as the server, so people see problems before sending.
    const errs: Record<string, string> = {};
    if (!payload.name.trim()) errs.name = "Enter your name.";
    if (!payload.phone.trim() && !payload.email.trim()) errs.phone = "Enter a phone number or email address so we can contact you.";
    if (payload.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(payload.email.trim())) errs.email = "Check the email address.";
    if (payload.phone && payload.phone.replace(/\D/g, "").length < 6) errs.phone = "Check the phone number.";
    if (!payload.items.length && !payload.freeText.trim() && !payload.files.length) errs.items = "Add a product, describe your requirements or upload a purchase list.";
    if (!payload.district.trim()) errs.district = "Enter your delivery district or city.";
    if (!payload.privacy) errs.privacy = "Please confirm you have read the Privacy Notice.";
    if (uploads.some((u) => u.state === "uploading")) errs.files = "Please wait for your files to finish uploading.";
    for (const u of uploads.filter((x) => x.state === "bad")) errs[`file:${u.id}`] = u.error || "";
    setErrors(errs);
    if (Object.keys(errs).length) {
      requestAnimationFrame(() => errRef.current?.focus());
      return;
    }
    setBusy(true);
    try {
      const res = await fetch("/api/enquiry/", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setErrors(data.errors || { form: "Something went wrong. Please try again." });
        requestAnimationFrame(() => errRef.current?.focus());
        return;
      }
      clearQuote();
      setDone({ ref: data.ref, email: data.email || "" });
      if (!firedEvent.current) {
        firedEvent.current = true;
        const w = window as unknown as { dataLayer?: unknown[] };
        (w.dataLayer = w.dataLayer || []).push({ event: "quote_request_saved", quote_mode: mode, quote_items: payload.items.length, quote_files: payload.files.length });
      }
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch {
      setErrors({ form: "We could not reach the server. Check your connection and try again." });
    } finally {
      setBusy(false);
    }
  }

  const errList = useMemo(() => Object.values(errors).filter(Boolean), [errors]);

  if (done) {
    return (
      <div className="atp-success" role="status" tabIndex={-1}>
        <h2>Your Quote Request Has Been Received</h2>
        <p>
          Thank you for contacting Abedin Trade Point. Your reference is <strong>{done.ref}</strong>. Our team will review your requirements and contact you if more information is needed.
        </p>
        {done.email && <p>A confirmation email has been requested for {done.email}.</p>}
        <div className="wp-block-buttons">
          <a className="btn" href="/products/">Continue Browsing</a>
          <a className="btn btn--outline" href="/contact/">Contact Our Team</a>
        </div>
      </div>
    );
  }

  const tab = (m: Mode, label: string) => (
    <button type="button" className={`atp-route${mode === m ? " is-active" : ""}`} aria-pressed={mode === m} onClick={() => setMode(m)}>
      {label}
      {m === "list" && loaded ? ` (${items.length})` : ""}
    </button>
  );

  return (
    <form className="atp-form atp-quote" onSubmit={submit} noValidate>
      <p className="has-muted-color">Add your items or upload a purchase list. Share your contact details and delivery district so we can review the requirement and contact you about the next steps.</p>
      <div className="atp-routes" role="group" aria-label="How would you like to send your requirement?">
        {tab("list", "My Quote List")}
        {tab("type", "Type My Requirements")}
        {tab("upload", "Upload Purchase List")}
      </div>

      <div ref={errRef} tabIndex={-1} aria-live="assertive">
        {errList.length > 0 && (
          <div className="atp-alert--error" role="alert">
            <strong>Your request has not been sent yet. Please check the following:</strong>
            <ul>{errList.map((m, i) => <li key={i}>{m}</li>)}</ul>
          </div>
        )}
      </div>

      <fieldset className="atp-section">
        <legend>Your Items</legend>
        {mode === "list" && (
          <>
            {!loaded ? null : items.length === 0 ? (
              <p className="atp-empty">
                Your quote list is empty. <a href="/products/">Browse products</a> or <button type="button" className="atp-link-btn" onClick={() => setMode("upload")}>upload a purchase list</button> to get started.
              </p>
            ) : (
              <table className="atp-list">
                <thead>
                  <tr><th>Item</th><th>Quantity</th><th>Note</th><th><span className="sr-only">Remove</span></th></tr>
                </thead>
                <tbody>
                  {items.map((i) => (
                    <tr key={i.key}>
                      <td>
                        <div className="atp-list__item">
                          {i.image && <img src={i.image} alt="" width={56} height={56} />}
                          <div>
                            <a href={`/product/${i.slug}/`}><strong>{i.name}</strong></a>
                            <div className="atp-help">
                              {i.sku}
                              {i.size ? ` · Size: ${i.size}` : ""}
                              {i.colour ? ` · Colour: ${i.colour}` : ""}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="atp-list__qty">
                        <label className="sr-only" htmlFor={`qty-${i.key}`}>Quantity for {i.name}</label>
                        <input id={`qty-${i.key}`} type="number" min={1} step="any" value={i.qty} onChange={(e) => updateItem(i.key, { qty: Math.max(0, Number(e.target.value) || 0) })} /> {(UNIT_LABEL[i.unit] || i.unit).toLowerCase()}
                      </td>
                      <td>
                        <label className="sr-only" htmlFor={`note-${i.key}`}>Note for {i.name}</label>
                        <input id={`note-${i.key}`} type="text" maxLength={500} value={i.note || ""} placeholder="Size breakdown, colour, GSM, branding" onChange={(e) => updateItem(i.key, { note: e.target.value })} />
                      </td>
                      <td><button type="button" className="atp-link-btn" onClick={() => removeItem(i.key)} aria-label={`Remove ${i.name}`}>Remove</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            <p className="atp-help" style={{ marginTop: ".75rem" }}>
              Need something not in the catalogue? <button type="button" className="atp-link-btn" onClick={() => setMode("type")}>Type your requirements</button>
            </p>
          </>
        )}
        {mode === "type" && (
          <div className="field-wrap">
            <label htmlFor="freeText">Describe the items you need</label>
            <textarea id="freeText" rows={7} maxLength={5000} value={freeText} onChange={(e) => setFreeText(e.target.value)} aria-invalid={!!errors.items} />
            <p className="atp-help">One item per line with product, specification, quantity and unit. For example: Exercise book, A5, 120 pages, 70 GSM, 500 pieces.</p>
            {items.length > 0 && <p className="atp-help">Your quote list ({items.length} item{items.length === 1 ? "" : "s"}) will be sent too.</p>}
          </div>
        )}
        {mode === "upload" && (
          <div className="field-wrap">
            <label htmlFor="files">Upload purchase list or specification</label>
            <input id="files" type="file" multiple accept=".pdf,.xlsx,.docx,.jpg,.jpeg,.png" onChange={(e) => { addFiles(e.target.files); e.target.value = ""; }} />
            <p className="atp-help">PDF, XLSX, DOCX, JPG or PNG. Up to 5 files, 10 MB each.</p>
            {uploads.length > 0 && (
              <ul className="atp-file-list">
                {uploads.map((u) => (
                  <li key={u.id} className={u.state === "bad" ? "is-bad" : ""}>
                    <span>{u.name} · {(u.size / 1024 / 1024).toFixed(1)} MB · {u.state === "uploading" ? "Uploading…" : u.state === "done" ? "Ready" : "Not uploaded"}</span>
                    <button type="button" className="atp-link-btn" onClick={() => setUploads((x) => x.filter((y) => y.id !== u.id))}>Remove</button>
                  </li>
                ))}
              </ul>
            )}
            {items.length > 0 && <p className="atp-help">Your quote list ({items.length} item{items.length === 1 ? "" : "s"}) will be sent too.</p>}
          </div>
        )}
        <div className="field-wrap" style={{ marginTop: "1rem" }}>
          <label htmlFor="specs">Specifications or special requirements <span className="atp-req">(optional)</span></label>
          <textarea id="specs" name="specs" rows={3} maxLength={3000} placeholder="Sizes, materials, brand preferences, printing or other requirements." />
        </div>
      </fieldset>

      <fieldset className="atp-section">
        <legend>Your Details</legend>
        <div className="atp-grid">
          <div className="field-wrap">
            <label htmlFor="name">Name <span className="atp-req">(required)</span></label>
            <input id="name" name="name" type="text" autoComplete="name" maxLength={120} aria-invalid={!!errors.name} />
          </div>
          <div className="field-wrap">
            <label htmlFor="org">Organisation <span className="atp-req">(optional)</span></label>
            <input id="org" name="org" type="text" autoComplete="organization" maxLength={160} placeholder="Company, institution or project name" />
          </div>
          <div className="field-wrap">
            <label htmlFor="phone">Phone or WhatsApp</label>
            <input id="phone" name="phone" type="tel" autoComplete="tel" maxLength={40} placeholder="+8801XXXXXXXXX" aria-invalid={!!errors.phone} />
          </div>
          <div className="field-wrap">
            <label htmlFor="email">Email</label>
            <input id="email" name="email" type="email" autoComplete="email" maxLength={160} aria-invalid={!!errors.email} />
          </div>
        </div>
        <p className="atp-help" style={{ marginTop: "-6px" }}>Enter a phone number or email so we can contact you.</p>
        <div className="field-wrap">
          <label htmlFor="preferred">Preferred contact method</label>
          <select id="preferred" name="preferred" defaultValue="">
            <option value="">No preference</option>
            <option>Phone call</option>
            <option>WhatsApp</option>
            <option>Email</option>
          </select>
        </div>
      </fieldset>

      <fieldset className="atp-section">
        <legend>Delivery</legend>
        <div className="atp-grid">
          <div className="field-wrap">
            <label htmlFor="district">Delivery district or city <span className="atp-req">(required)</span></label>
            <input id="district" name="district" type="text" list="atp-districts" maxLength={80} autoComplete="address-level2" aria-invalid={!!errors.district} />
            <datalist id="atp-districts">{districts.map((d) => <option key={d} value={d} />)}</datalist>
          </div>
          <div className="field-wrap">
            <label htmlFor="deliveryDate">Requested delivery date <span className="atp-req">(optional)</span></label>
            <input id="deliveryDate" name="deliveryDate" type="date" />
          </div>
          <div className="field-wrap">
            <label htmlFor="deadline">Quotation needed by <span className="atp-req">(optional)</span></label>
            <input id="deadline" name="deadline" type="date" />
          </div>
          <div className="field-wrap">
            <label htmlFor="budget">Budget range <span className="atp-req">(optional)</span></label>
            <input id="budget" name="budget" type="text" maxLength={80} placeholder="Prefer to discuss" />
          </div>
        </div>
        <p className="atp-help">A delivery date is a request, not a confirmed promise. Your full address can follow when the order is discussed.</p>
      </fieldset>

      <div className="atp-hp" aria-hidden="true">
        <label htmlFor="website">Leave this field empty</label>
        <input id="website" name="website" type="text" tabIndex={-1} autoComplete="off" />
      </div>

      <div className="atp-privacy field-wrap">
        <label>
          <input type="checkbox" name="privacy" value="1" aria-invalid={!!errors.privacy} />
          <span>I have read the <a href="/privacy/" target="_blank">Privacy Notice</a>.</span>
        </label>
        <p className="atp-help">We use the details you provide to review your enquiry and contact you about the requested products.</p>
      </div>

      <button className="btn atp-submit" type="submit" disabled={busy}>{busy ? "Sending…" : "Submit Quote Request"}</button>
      <p className="atp-help" style={{ marginTop: ".5rem" }}>No account required. Sending a request does not place an order.</p>
    </form>
  );
}
