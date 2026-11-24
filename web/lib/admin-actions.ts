"use server";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { checkPassword, cookieOptions, currentAdmin, makeSession, adminConfigured } from "./auth";
import { getEnquiry, saveEnquiry } from "./admin-data";
import { STATUSES, type Quotation, type Status } from "./enquiry";
import { sendNotifications } from "./email";
import { site } from "./site";

const s = (v: FormDataEntryValue | null, max = 500) => String(v ?? "").trim().slice(0, max);
const num = (v: FormDataEntryValue | null): number | "" => {
  const n = Number(v);
  return String(v ?? "").trim() !== "" && Number.isFinite(n) && n >= 0 ? n : "";
};

async function need() {
  const who = await currentAdmin();
  if (!who) redirect("/admin/");
  return who;
}

export async function login(form: FormData) {
  if (!adminConfigured()) redirect("/admin/");
  await new Promise((r) => setTimeout(r, 400));
  if (!checkPassword(s(form.get("password"), 200))) redirect("/admin/?error=1");
  const name = s(form.get("name"), 40) || "Staff";
  (await cookies()).set({ ...cookieOptions, value: makeSession(name) });
  redirect("/admin/");
}

export async function logout() {
  (await cookies()).delete(cookieOptions.name);
  redirect("/admin/");
}

export async function updateEnquiry(form: FormData) {
  const who = await need();
  const ref = s(form.get("ref"), 40);
  const e = await getEnquiry(ref);
  if (!e) redirect("/admin/");
  const now = new Date().toISOString();
  const status = s(form.get("status"), 30) as Status;
  if (status in STATUSES && status !== e.status) {
    e.history.push({ at: now, text: `Status: ${STATUSES[e.status]} → ${STATUSES[status]} (${who})` });
    e.status = status;
  }
  const owner = s(form.get("owner"), 60);
  if (owner !== e.owner) {
    e.history.push({ at: now, text: `Owner: ${owner || "none"} (${who})` });
    e.owner = owner;
  }
  const follow = s(form.get("followUp"), 10);
  if (/^(\d{4}-\d{2}-\d{2})?$/.test(follow) && follow !== e.followUp) {
    e.history.push({ at: now, text: `Follow-up date: ${follow || "cleared"} (${who})` });
    e.followUp = follow;
  }
  const note = s(form.get("note"), 2000);
  if (note) e.notes.push({ at: now, by: who, text: note });
  await saveEnquiry(e);
  redirect(`/admin/enquiry/${ref}/?saved=1`);
}

export async function resendNotifications(form: FormData) {
  const who = await need();
  const ref = s(form.get("ref"), 40);
  const e = await getEnquiry(ref);
  if (!e) redirect("/admin/");
  e.notify = await sendNotifications(e, site.url);
  e.history.push({ at: new Date().toISOString(), text: e.notify.status === "sent" ? `Notifications re-sent (${who}).` : e.notify.status === "off" ? "Email alerts are not set up." : `Notification failed: ${e.notify.error}` });
  await saveEnquiry(e);
  redirect(`/admin/enquiry/${ref}/`);
}

export async function saveQuotation(form: FormData) {
  const who = await need();
  const ref = s(form.get("ref"), 40);
  const e = await getEnquiry(ref);
  if (!e) redirect("/admin/");
  const items = form.getAll("item");
  const specs = form.getAll("spec");
  const qtys = form.getAll("qty");
  const units = form.getAll("unit");
  const prices = form.getAll("price");
  const lines = items
    .map((it, i) => ({ item: s(it, 200), spec: s(specs[i], 1000), qty: num(qtys[i]), unit: s(units[i], 30), price: num(prices[i]) }))
    .filter((l) => l.item);
  const next: Omit<Quotation, "rev" | "date"> = {
    lines,
    delivery: num(form.get("delivery")),
    vat: num(form.get("vat")),
    validity: s(form.get("validity"), 100),
    lead: s(form.get("lead"), 200),
    payment: s(form.get("payment"), 200),
    notes: s(form.get("notes"), 3000),
  };
  const old = e.quotation;
  const same = old && JSON.stringify({ ...old, rev: 0, date: "" }) === JSON.stringify({ ...next, rev: 0, date: "" });
  const now = new Date().toISOString();
  if (lines.length && !same) {
    e.quotation = { ...next, rev: (old?.rev ?? 0) + 1, date: now.slice(0, 10) };
    e.history.push({ at: now, text: `Quotation revision ${e.quotation.rev} saved (${who}).` });
  }
  if (form.get("markQuoted") === "1" && e.status !== "quoted") {
    e.history.push({ at: now, text: `Status: ${STATUSES[e.status]} → ${STATUSES.quoted} (${who})` });
    e.status = "quoted";
  }
  await saveEnquiry(e);
  redirect(`/admin/enquiry/${ref}/quotation/?saved=1`);
}
