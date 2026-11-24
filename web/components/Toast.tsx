"use client";
import { useEffect, useState } from "react";

export default function Toast() {
  const [msg, setMsg] = useState("");
  useEffect(() => {
    let t: ReturnType<typeof setTimeout>;
    const show = (e: Event) => {
      setMsg(String((e as CustomEvent).detail || ""));
      clearTimeout(t);
      t = setTimeout(() => setMsg(""), 4000);
    };
    window.addEventListener("atp-toast", show);
    return () => window.removeEventListener("atp-toast", show);
  }, []);
  if (!msg) return <div role="status" aria-live="polite" className="sr-only" />;
  return (
    <div className="atp-toast" role="status" aria-live="polite">
      <span>{msg}</span>
      <a href="/request-a-quote/">Review</a>
    </div>
  );
}
