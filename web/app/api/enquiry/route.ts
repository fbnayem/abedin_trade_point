import { NextResponse } from "next/server";
import { newReference, sniff, validate, maskEmail, MAX_BYTES, type Enquiry, type EnquiryInput } from "@/lib/enquiry";
import { exists, readBytes, readJson, remove, storageReady, writeJson } from "@/lib/store";
import { sendNotifications } from "@/lib/email";

export const runtime = "nodejs";
export const maxDuration = 30;

export async function POST(request: Request) {
  let input: EnquiryInput;
  try {
    input = (await request.json()) as EnquiryInput;
  } catch {
    return NextResponse.json({ errors: { form: "Something went wrong. Please try again." } }, { status: 400 });
  }
  // Spam trap: real visitors never fill the hidden field.
  if (input.website) return NextResponse.json({ ref: newReference(), email: "" });
  if (!storageReady()) {
    console.error("Enquiry storage is not configured: connect a Vercel Blob store.");
    return NextResponse.json({ errors: { form: "We could not save your request just now. Please contact us by phone or WhatsApp, or try again later." } }, { status: 503 });
  }

  const token = typeof input.token === "string" && /^[A-Za-z0-9-]{16,64}$/.test(input.token) ? input.token : "";
  if (token) {
    const seen = await readJson<{ ref: string; email: string }>(`tokens/${token}.json`);
    if (seen) return NextResponse.json({ ...seen, repeat: true });
  }

  const { errors, data } = validate(input);

  // Check every uploaded file really is the document type it claims to be.
  const goodFiles = [];
  for (const f of data.files) {
    const blob = await readBytes(f.pathname);
    if (!blob || blob.data.length > MAX_BYTES || !sniff(blob.data, f.pathname)) {
      errors[`file:${f.name}`] = `${f.name} could not be uploaded. Check the file type and 10 MB limit, then try again.`;
      await remove(f.pathname);
      continue;
    }
    goodFiles.push({ ...f, size: blob.data.length });
  }
  if (Object.keys(errors).length) return NextResponse.json({ errors }, { status: 422 });

  let ref = newReference();
  for (let i = 0; i < 3 && (await exists(`enquiries/${ref}.json`)); i++) ref = newReference();
  const now = new Date().toISOString();
  const enquiry: Enquiry = {
    ref,
    created: now,
    updated: now,
    status: "new",
    owner: "",
    followUp: "",
    mode: data.mode,
    contact: data.contact,
    items: data.items,
    freeText: data.freeText,
    specs: data.specs,
    files: goodFiles,
    notify: { status: "pending" },
    notes: [],
    history: [{ at: now, text: "Enquiry received from the website." }],
  };
  await writeJson(`enquiries/${ref}.json`, enquiry);
  const reply = { ref, email: data.contact.email ? maskEmail(data.contact.email) : "" };
  if (token) await writeJson(`tokens/${token}.json`, reply);

  const base = new URL(request.url).origin;
  const notify = await sendNotifications(enquiry, base).catch((e) => ({ status: "failed" as const, error: String(e) }));
  enquiry.notify = notify;
  if (notify.status !== "off") enquiry.history.push({ at: new Date().toISOString(), text: notify.status === "sent" ? "Notifications sent." : `Notification failed: ${notify.error}` });
  await writeJson(`enquiries/${ref}.json`, enquiry);

  return NextResponse.json({ ...reply, emailSent: notify.status === "sent" });
}
