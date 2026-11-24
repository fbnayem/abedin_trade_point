import siteData from "@/content/site.json";

/**
 * Business details. Edit content/site.json, or override any field on Vercel
 * with an environment variable (SITE_PHONE, SITE_WHATSAPP, SITE_EMAIL,
 * SITE_ADDRESS, SITE_HOURS, SITE_PROFILE_URL, SITE_URL, NEXT_PUBLIC_GA4_ID,
 * SITE_SHOW_PLACEHOLDERS=0, SITE_NOINDEX=0).
 */
const env = process.env;
const flag = (v: string | undefined, d: boolean) => (v === undefined || v === "" ? d : v === "1" || v === "true");

export const site = {
  name: siteData.name,
  tagline: siteData.tagline,
  url: (env.SITE_URL || siteData.url).replace(/\/$/, ""),
  phone: env.SITE_PHONE || siteData.phone,
  whatsapp: env.SITE_WHATSAPP || siteData.whatsapp,
  email: env.SITE_EMAIL || siteData.email,
  address: env.SITE_ADDRESS || siteData.address,
  hours: env.SITE_HOURS || siteData.hours,
  profileUrl: env.SITE_PROFILE_URL || siteData.profileUrl,
  logo: env.SITE_LOGO || siteData.logo,
  showPlaceholders: flag(env.SITE_SHOW_PLACEHOLDERS, siteData.showPlaceholders),
  noindex: flag(env.SITE_NOINDEX, siteData.noindex),
  ga4: env.NEXT_PUBLIC_GA4_ID || siteData.ga4,
};

const LABELS = {
  phone: "BUSINESS PHONE",
  whatsapp: "WHATSAPP NUMBER",
  email: "SALES EMAIL",
  address: "BUSINESS ADDRESS",
  hours: "BUSINESS HOURS",
} as const;

export const esc = (s: string) =>
  s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");

export function placeholder(key: keyof typeof LABELS) {
  return site.showPlaceholders ? `<mark class="atp-placeholder">[${LABELS[key]}]</mark>` : "";
}

export function whatsappUrl(item = "") {
  const n = site.whatsapp.replace(/\D/g, "");
  if (!n) return "";
  const text = item ? `Hello Abedin Trade Point, I would like a quotation for: ${item}` : "Hello Abedin Trade Point, I would like to discuss a supply requirement.";
  return `https://wa.me/${n}?text=${encodeURIComponent(text)}`;
}

export function contactBlockHtml() {
  const rows: string[] = [];
  const row = (label: string, body: string) => body && rows.push(`<li><span class="atp-contact-label">${label}</span> ${body}</li>`);
  row("Call Our Team", site.phone ? `<a href="tel:${site.phone.replace(/[^0-9+]/g, "")}">${esc(site.phone)}</a>` : placeholder("phone"));
  const wa = whatsappUrl();
  row("WhatsApp", wa ? `<a href="${esc(wa)}" target="_blank" rel="noopener">Message on WhatsApp</a>` : placeholder("whatsapp"));
  row("Email", site.email ? `<a href="mailto:${esc(site.email)}">${esc(site.email)}</a>` : placeholder("email"));
  row("Address", site.address ? esc(site.address).replace(/\n/g, "<br>") : placeholder("address"));
  row("Hours", site.hours ? esc(site.hours) : placeholder("hours"));
  return rows.length ? `<ul class="atp-contact-list">${rows.join("")}</ul>` : "";
}

export function whatsappButtonHtml(label = "Chat on WhatsApp", item = "") {
  const url = whatsappUrl(item);
  if (!url) return "";
  return `<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="${esc(url)}" target="_blank" rel="noopener">${esc(label)}</a></div>`;
}

export function profileDownloadHtml() {
  if (!site.profileUrl) return "";
  return `<div class="atp-profile"><p>Read a short overview of Abedin Trade Point, our published supply categories and the process for sending a quotation request.</p><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="${esc(site.profileUrl)}" download>Download Company Profile</a></div></div>`;
}

/** Replace the WordPress-era shortcodes left in the content HTML. */
export function renderShortcodes(html: string) {
  return html
    .replace(/\[atp_contact_block\]/g, contactBlockHtml())
    .replace(/\[atp_profile_download\]/g, profileDownloadHtml())
    .replace(/\[atp_whatsapp_button(?:\s+label="([^"]*)")?\]/g, (_m, label) => whatsappButtonHtml(label || undefined))
    .replace(/\[products[^\]]*\]/g, "");
}
