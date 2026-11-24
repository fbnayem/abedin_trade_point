"use client";
import { useState } from "react";
import { addToQuote } from "@/lib/quote-store";

type Props = { slug: string; name: string; sku: string; unit: string; image?: string; className?: string };

export default function AddButton({ slug, name, sku, unit, image, className = "btn btn--sm" }: Props) {
  const [added, setAdded] = useState(false);
  return (
    <button
      type="button"
      className={className}
      onClick={() => {
        if (addToQuote({ slug, name, sku, unit, qty: 1, image })) {
          setAdded(true);
          setTimeout(() => setAdded(false), 2500);
        }
      }}
      aria-label={`Add ${name} to your quote list`}
    >
      {added ? "Added ✓" : "Add to Quote"}
    </button>
  );
}
