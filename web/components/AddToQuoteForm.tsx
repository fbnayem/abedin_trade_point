"use client";
import { useState } from "react";
import { addToQuote } from "@/lib/quote-store";

type Props = {
  slug: string;
  name: string;
  sku: string;
  unit: string;
  unitLabel: string;
  decimal: boolean;
  sizes: string[];
  colours: string[];
  image?: string;
  whatsapp?: string;
};

export default function AddToQuoteForm(p: Props) {
  const [qty, setQty] = useState("");
  const [size, setSize] = useState("");
  const [colour, setColour] = useState("");
  const [note, setNote] = useState("");
  const [error, setError] = useState("");
  const [done, setDone] = useState(false);

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const n = Number(qty);
    if (!qty || !Number.isFinite(n) || n <= 0 || (!p.decimal && !Number.isInteger(n))) {
      setError(p.decimal ? "Enter the quantity you need." : "Enter the quantity you need as a whole number.");
      return;
    }
    if (p.sizes.length && !size) {
      setError("Choose a size, or choose Mixed sizes and add the breakdown in the note.");
      return;
    }
    setError("");
    const ok = addToQuote({ slug: p.slug, name: p.name, sku: p.sku, unit: p.unit, qty: n, size: size || undefined, colour: colour || undefined, note: note.trim().slice(0, 500) || undefined, image: p.image });
    if (!ok) setError("Your quote list is full (100 lines). Send this request first, then start another.");
    else {
      setDone(true);
      setQty("");
      setNote("");
    }
  }

  return (
    <form className="addbox" onSubmit={submit} noValidate>
      {error && <p className="atp-alert--error" role="alert">{error}</p>}
      <div className="row">
        <div>
          <label htmlFor="q-qty">Quantity ({p.unitLabel.toLowerCase()}s)</label>
          <input id="q-qty" inputMode={p.decimal ? "decimal" : "numeric"} type="number" min={p.decimal ? 0.1 : 1} step={p.decimal ? 0.1 : 1} value={qty} onChange={(e) => setQty(e.target.value)} placeholder="e.g. 500" aria-invalid={!!error && !qty} />
        </div>
        {p.sizes.length > 0 ? (
          <div>
            <label htmlFor="q-size">Size</label>
            <select id="q-size" value={size} onChange={(e) => setSize(e.target.value)}>
              <option value="">Choose a size</option>
              <option value="Mixed sizes">Mixed sizes (add breakdown below)</option>
              {p.sizes.map((s) => <option key={s}>{s}</option>)}
            </select>
          </div>
        ) : p.colours.length > 0 ? null : <div />}
        {p.colours.length > 0 && (
          <div>
            <label htmlFor="q-colour">Colour</label>
            <select id="q-colour" value={colour} onChange={(e) => setColour(e.target.value)}>
              <option value="">Any / to discuss</option>
              {p.colours.map((c) => <option key={c}>{c}</option>)}
              <option value="Custom colour">Custom colour (Pantone in note)</option>
            </select>
          </div>
        )}
      </div>
      <div className="row row--1">
        <div>
          <label htmlFor="q-note">Specification or note <span className="atp-req">(optional)</span></label>
          <textarea id="q-note" rows={3} maxLength={500} value={note} onChange={(e) => setNote(e.target.value)} placeholder={p.sizes.length ? "e.g. S 50, M 150, L 200, XL 100. 180 GSM, logo print on chest." : "e.g. size, material, brand preference, printing"} />
        </div>
      </div>
      <div className="addbox__actions">
        <button className="btn" type="submit">Add to Quote</button>
        {done && <a className="btn btn--outline" href="/request-a-quote/">Review Quote List</a>}
        {!done && p.whatsapp && <a className="btn btn--outline" href={p.whatsapp} target="_blank" rel="noopener">Ask on WhatsApp</a>}
      </div>
      <ul className="addbox__notes">
        <li>Minimum quantity confirmed with quotation.</li>
        <li>Lead time confirmed after specification and availability review.</li>
      </ul>
    </form>
  );
}
