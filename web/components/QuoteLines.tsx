"use client";
import { useState } from "react";
import type { QuoteLine } from "@/lib/enquiry";

const money = (n: number) => "Tk " + n.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function QuoteLines({ initial, delivery: d0, vat: v0 }: { initial: QuoteLine[]; delivery: number | ""; vat: number | "" }) {
  const [lines, setLines] = useState(initial.map((l, i) => ({ ...l, id: i })));
  const [delivery, setDelivery] = useState(String(d0));
  const [vat, setVat] = useState(String(v0));
  const set = (id: number, k: keyof QuoteLine, v: string) => setLines((ls) => ls.map((l) => (l.id === id ? { ...l, [k]: v } : l)));
  const sub = lines.reduce((s, l) => s + (Number(l.qty) || 0) * (Number(l.price) || 0), 0);
  const del = Number(delivery) || 0;
  const tax = Math.round((sub + del) * (Number(vat) || 0)) / 100;
  return (
    <>
      <div style={{ overflowX: "auto" }}>
        <table className="qt">
          <thead><tr><th>Item</th><th>Specification</th><th>Qty</th><th>Unit</th><th>Unit price (Tk)</th><th>Amount</th><th /></tr></thead>
          <tbody>
            {lines.map((l) => (
              <tr key={l.id}>
                <td style={{ minWidth: 180 }}><input name="item" value={l.item} onChange={(e) => set(l.id, "item", e.target.value)} aria-label="Item" /></td>
                <td style={{ minWidth: 220 }}><textarea name="spec" rows={2} value={l.spec} onChange={(e) => set(l.id, "spec", e.target.value)} aria-label="Specification" /></td>
                <td style={{ width: 90 }}><input name="qty" type="number" step="any" min="0" value={l.qty} onChange={(e) => set(l.id, "qty", e.target.value)} aria-label="Quantity" /></td>
                <td style={{ width: 90 }}><input name="unit" value={l.unit} onChange={(e) => set(l.id, "unit", e.target.value)} aria-label="Unit" /></td>
                <td style={{ width: 120 }}><input name="price" type="number" step="0.01" min="0" value={l.price} onChange={(e) => set(l.id, "price", e.target.value)} aria-label="Unit price" /></td>
                <td style={{ whiteSpace: "nowrap" }}>{l.price !== "" ? money((Number(l.qty) || 0) * (Number(l.price) || 0)) : "—"}</td>
                <td><button type="button" className="atp-link-btn" onClick={() => setLines((ls) => ls.filter((x) => x.id !== l.id))}>Remove</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p><button type="button" className="btn btn--sm btn--outline" onClick={() => setLines((ls) => [...ls, { id: Date.now(), item: "", spec: "", qty: "", unit: "", price: "" }])}>Add line</button></p>
      <div className="atp-grid">
        <div className="field-wrap"><label htmlFor="delivery">Delivery charge (Tk)</label><input id="delivery" name="delivery" type="number" step="0.01" min="0" value={delivery} onChange={(e) => setDelivery(e.target.value)} /></div>
        <div className="field-wrap"><label htmlFor="vat">VAT %</label><input id="vat" name="vat" type="number" step="0.01" min="0" max="100" value={vat} onChange={(e) => setVat(e.target.value)} /><p className="atp-help">Leave empty if prices include VAT or VAT does not apply.</p></div>
      </div>
      <p>Subtotal {money(sub)} · Delivery {money(del)} · VAT {money(tax)} · <strong>Total {money(sub + del + tax)}</strong></p>
    </>
  );
}
