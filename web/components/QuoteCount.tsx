"use client";
import { useEffect, useState } from "react";
import { onQuoteChange, readQuote } from "@/lib/quote-store";

export default function QuoteCount({ className = "atp-quote-pill", label = "Quote List" }: { className?: string; label?: string }) {
  const [n, setN] = useState(0);
  useEffect(() => {
    const update = () => setN(readQuote().length);
    update();
    return onQuoteChange(update);
  }, []);
  return (
    <a className={className} href="/request-a-quote/" aria-label={`${label}: ${n} item${n === 1 ? "" : "s"}`}>
      {label} <span className="atp-count">{n}</span>
    </a>
  );
}
