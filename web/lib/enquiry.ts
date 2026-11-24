import { randomBytes } from "node:crypto";

export const STATUSES = {
  new: "New",
  reviewing: "Reviewing",
  awaiting_details: "Awaiting Details",
  quoted: "Quoted",
  won: "Won",
  lost: "Lost / Closed",
} as const;
export type Status = keyof typeof STATUSES;

export type EnquiryItem = { slug: string; name: string; sku: string; unit: string; qty: number; size?: string; colour?: string; note?: string };
export type EnquiryFile = { pathname: string; name: string; size: number; type: string };
export type QuoteLine = { item: string; spec: string; qty: number | ""; unit: string; price: number | "" };
export type Quotation = {
  lines: QuoteLine[];
  delivery: number | "";
  vat: number | "";
  validity: string;
  lead: string;
  payment: string;
  notes: string;
  rev: number;
  date: string;
};

export type Enquiry = {
  ref: string;
  created: string;
  updated: string;
  status: Status;
  owner: string;
  followUp: string;
  mode: "list" | "type" | "upload";
  contact: {
    name: string;
    org: string;
    phone: string;
    email: string;
    preferred: string;
    district: string;
    deliveryDate: string;
    deadline: string;
    budget: string;
  };
  items: EnquiryItem[];
  freeText: string;
  specs: string;
  files: EnquiryFile[];
  notify: { status: "pending" | "sent" | "failed" | "off"; error?: string };
  notes: { at: string; by: string; text: string }[];
  history: { at: string; text: string }[];
  quotation?: Quotation;
};

export type EnquiryInput = {
  token?: string;
  mode?: string;
  items?: unknown;
  freeText?: string;
  specs?: string;
  files?: unknown;
  name?: string;
  org?: string;
  phone?: string;
  email?: string;
  preferred?: string;
  district?: string;
  deliveryDate?: string;
  deadline?: string;
  budget?: string;
  privacy?: boolean;
  website?: string;
};

export const ALLOWED_TYPES: Record<string, string> = {
  pdf: "application/pdf",
  xlsx: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  docx: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  jpg: "image/jpeg",
  jpeg: "image/jpeg",
  png: "image/png",
};
export const MAX_FILES = 5;
export const MAX_BYTES = 10 * 1024 * 1024;

const str = (v: unknown, max = 200) => (typeof v === "string" ? v.replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, "").trim().slice(0, max) : "");
const isDate = (v: string) => /^\d{4}-\d{2}-\d{2}$/.test(v);

export function newReference(now = new Date()) {
  const ymd = now.toISOString().slice(2, 10).replace(/-/g, "");
  const alphabet = "ABCDEFGHJKMNPQRSTUVWXYZ23456789";
  const bytes = randomBytes(4);
  const tail = Array.from(bytes, (b) => alphabet[b % alphabet.length]).join("");
  return `ATP-${ymd}-${tail}`;
}

export function validate(input: EnquiryInput) {
  const errors: Record<string, string> = {};
  const mode = (["list", "type", "upload"].includes(String(input.mode)) ? input.mode : "list") as Enquiry["mode"];
  const items: EnquiryItem[] = (Array.isArray(input.items) ? input.items : [])
    .slice(0, 100)
    .map((i: Record<string, unknown>) => ({
      slug: str(i?.slug, 120),
      name: str(i?.name, 160),
      sku: str(i?.sku, 40),
      unit: str(i?.unit, 20) || "piece",
      qty: Math.min(1_000_000, Math.max(0, Number(i?.qty) || 0)),
      size: str(i?.size, 40) || undefined,
      colour: str(i?.colour, 40) || undefined,
      note: str(i?.note, 500) || undefined,
    }))
    .filter((i) => i.name && i.qty > 0);
  const files: EnquiryFile[] = (Array.isArray(input.files) ? input.files : [])
    .slice(0, MAX_FILES)
    .map((f: Record<string, unknown>) => ({ pathname: str(f?.pathname, 300), name: str(f?.name, 160), size: Number(f?.size) || 0, type: str(f?.type, 120) }))
    .filter((f) => f.pathname.startsWith("uploads/") && !f.pathname.includes(".."));
  const contact = {
    name: str(input.name, 120),
    org: str(input.org, 160),
    phone: str(input.phone, 40),
    email: str(input.email, 160).toLowerCase(),
    preferred: str(input.preferred, 40),
    district: str(input.district, 80),
    deliveryDate: isDate(str(input.deliveryDate, 10)) ? str(input.deliveryDate, 10) : "",
    deadline: isDate(str(input.deadline, 10)) ? str(input.deadline, 10) : "",
    budget: str(input.budget, 80),
  };
  const freeText = str(input.freeText, 5000);
  const specs = str(input.specs, 3000);

  if (!contact.name) errors.name = "Enter your name.";
  if (!contact.phone && !contact.email) errors.phone = "Enter a phone number or email address so we can contact you.";
  if (contact.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(contact.email)) errors.email = "Check the email address.";
  if (contact.phone && contact.phone.replace(/\D/g, "").length < 6) errors.phone = "Check the phone number.";
  if (!items.length && !freeText && !files.length) errors.items = "Add a product, describe your requirements or upload a purchase list.";
  if (!contact.district) errors.district = "Enter your delivery district or city.";
  if (!input.privacy) errors.privacy = "Please confirm you have read the Privacy Notice.";
  return { errors, data: { mode, items, files, contact, freeText, specs } };
}

/** Check the first bytes so a renamed file cannot pass as a document. */
export function sniff(buf: Buffer, name: string): boolean {
  const ext = name.split(".").pop()?.toLowerCase() || "";
  if (!(ext in ALLOWED_TYPES)) return false;
  if (ext === "pdf") return buf.subarray(0, 5).toString("latin1") === "%PDF-";
  if (ext === "png") return buf.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]));
  if (ext === "jpg" || ext === "jpeg") return buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff;
  if (ext === "xlsx" || ext === "docx") {
    if (!(buf[0] === 0x50 && buf[1] === 0x4b)) return false;
    // Reject macro-enabled Office files that were renamed.
    return !buf.includes(Buffer.from("vbaProject.bin"));
  }
  return false;
}

export function maskEmail(email: string) {
  const [u, d] = email.split("@");
  if (!u || !d) return "";
  return `${u[0]}••••@${d}`;
}

export function quotationNumber(e: Enquiry) {
  const q = e.quotation;
  return e.ref.replace("ATP-", "ATPQ-") + (q && q.rev > 1 ? `-R${q.rev - 1}` : "");
}

export function totals(q: Quotation) {
  const sub = q.lines.reduce((s, l) => s + (Number(l.qty) || 0) * (Number(l.price) || 0), 0);
  const delivery = Number(q.delivery) || 0;
  const vat = Math.round((sub + delivery) * (Number(q.vat) || 0)) / 100;
  return { subtotal: sub, delivery, vat, total: sub + delivery + vat };
}

export const money = (n: number) => "Tk " + n.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const DISTRICTS = [
  "Bagerhat", "Bandarban", "Barguna", "Barishal", "Bhola", "Bogura", "Brahmanbaria", "Chandpur", "Chapai Nawabganj", "Chattogram", "Chuadanga", "Cox's Bazar", "Cumilla", "Dhaka", "Dinajpur", "Faridpur", "Feni", "Gaibandha", "Gazipur", "Gopalganj", "Habiganj", "Jamalpur", "Jashore", "Jhalokathi", "Jhenaidah", "Joypurhat", "Khagrachhari", "Khulna", "Kishoreganj", "Kurigram", "Kushtia", "Lakshmipur", "Lalmonirhat", "Madaripur", "Magura", "Manikganj", "Meherpur", "Moulvibazar", "Munshiganj", "Mymensingh", "Naogaon", "Narail", "Narayanganj", "Narsingdi", "Natore", "Netrokona", "Nilphamari", "Noakhali", "Pabna", "Panchagarh", "Patuakhali", "Pirojpur", "Rajbari", "Rajshahi", "Rangamati", "Rangpur", "Satkhira", "Shariatpur", "Sherpur", "Sirajganj", "Sunamganj", "Sylhet", "Tangail", "Thakurgaon",
];
