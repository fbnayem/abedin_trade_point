import { STATUSES, type Enquiry } from "./enquiry";
import { site } from "./site";

/**
 * Email alerts through Resend (https://resend.com). Set RESEND_API_KEY,
 * EMAIL_FROM (an address on a domain verified in Resend) and NOTIFY_EMAILS
 * (comma-separated staff addresses). Without them, enquiries are still saved
 * and appear in the staff dashboard.
 */
export const emailConfigured = () => Boolean(process.env.RESEND_API_KEY && process.env.EMAIL_FROM && process.env.NOTIFY_EMAILS);

async function send(to: string[], subject: string, text: string, replyTo?: string) {
  const res = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: { Authorization: `Bearer ${process.env.RESEND_API_KEY}`, "Content-Type": "application/json" },
    body: JSON.stringify({ from: process.env.EMAIL_FROM, to, subject, text, ...(replyTo ? { reply_to: replyTo } : {}) }),
    signal: AbortSignal.timeout(15000),
  });
  if (!res.ok) throw new Error(`Email service returned ${res.status}: ${(await res.text()).slice(0, 200)}`);
}

export async function sendNotifications(e: Enquiry, baseUrl: string): Promise<Enquiry["notify"]> {
  if (!emailConfigured()) return { status: "off" };
  const errors: string[] = [];
  const staff = String(process.env.NOTIFY_EMAILS).split(",").map((s) => s.trim()).filter(Boolean);
  const c = e.contact;
  let body = `New quote request ${e.ref}\n\nName: ${c.name}\nOrganisation: ${c.org}\nPhone: ${c.phone}\nEmail: ${c.email}\nPreferred contact: ${c.preferred}\nDistrict: ${c.district}\nDelivery date: ${c.deliveryDate}\nQuotation needed by: ${c.deadline}\nBudget: ${c.budget}\nStatus: ${STATUSES[e.status]}\n\n`;
  if (e.items.length) {
    body += "Items:\n" + e.items.map((i) => `- ${i.name}${i.size ? `, ${i.size}` : ""}${i.colour ? `, ${i.colour}` : ""} × ${i.qty} ${i.unit}${i.note ? ` (${i.note})` : ""}`).join("\n") + "\n\n";
  }
  if (e.freeText) body += `Written requirement:\n${e.freeText}\n\n`;
  if (e.specs) body += `Specifications:\n${e.specs}\n\n`;
  if (e.files.length) body += `${e.files.length} attachment(s). Download them from the enquiry screen (not attached here for privacy).\n\n`;
  body += `Open: ${baseUrl}/admin/enquiry/${e.ref}/\n`;
  try {
    await send(staff, `New quote request ${e.ref} from ${c.name}`, body, c.email || undefined);
  } catch (err) {
    errors.push((err as Error).message);
  }
  if (c.email) {
    const first = c.name.split(" ")[0];
    const receipt = `Hi ${first},\n\nThank you for contacting ${site.name}. We have received your supply request under reference ${e.ref}.\n\nWe will review the items, quantities and delivery requirements you provided. If we need more information to prepare a quotation, our team will contact you.\n\nYou can reply to this email with additional details and include your reference.\n\nRegards,\n${site.name}`;
    try {
      await send([c.email], `We received your supply enquiry | ${e.ref}`, receipt, site.email || staff[0]);
    } catch (err) {
      errors.push((err as Error).message);
    }
  }
  return errors.length ? { status: "failed", error: errors.join("; ") } : { status: "sent" };
}
