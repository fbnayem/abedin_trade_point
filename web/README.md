# Abedin Trade Point website (Next.js on Vercel)

Quote-request catalogue for Abedin Trade Point. No checkout: buyers add products to a quote list, or type or upload their purchase list, and staff answer with a written quotation.

## What is where

| Path | What it holds |
| --- | --- |
| `content/categories.json` | Departments (e.g. Garments, Uniforms & Workwear) and their groups. `published: false` hides a department. |
| `content/subcategories.json` | Product categories inside each department (T-Shirts, Polo Shirts, Jute Bags…). |
| `content/products.json` | Every product type with its specs, sizes, colours and the search words used to find a sample photo. |
| `content/site.json` | Phone, WhatsApp, email, address, hours, GA4 ID. Empty values show highlighted placeholders until filled. |
| `content/html/` | Page copy (About, FAQs, Privacy…) and homepage sections. |
| `content/image-overrides.json` | Pin or hide a sample photo for a product. |
| `public/img/own/<product-slug>.jpg` | Your own product photos. They replace the sample photo automatically. |

## Product photos

`npm run build` runs `scripts/fetch-images.mjs` first. It looks up a free-licence photo for each product on Wikimedia Commons (then Openverse), resizes it and records the author and licence, which the site shows under each photo and on `/image-credits/`. Photos are cached between Vercel builds. To change a photo, add an entry to `content/image-overrides.json`:

```json
{ "v-neck-t-shirt": { "file": "File:Some_better_photo.jpg" }, "henley-t-shirt": { "query": "henley shirt grey" }, "ringer-t-shirt": { "skip": true } }
```

## Environment variables (Vercel → Project → Settings → Environment Variables)

| Variable | Needed for |
| --- | --- |
| `BLOB_READ_WRITE_TOKEN` | Saving enquiries and uploaded files. Added automatically when a Vercel Blob store is connected. |
| `ADMIN_PASSWORD`, `SESSION_SECRET` | Staff dashboard at `/admin/`. |
| `RESEND_API_KEY`, `EMAIL_FROM`, `NOTIFY_EMAILS` | Email alert per enquiry and a receipt to the buyer (via resend.com). Optional. |
| `SITE_PHONE`, `SITE_WHATSAPP`, `SITE_EMAIL`, `SITE_ADDRESS`, `SITE_HOURS`, `SITE_URL` | Override `content/site.json` without a code change. |
| `SITE_SHOW_PLACEHOLDERS=0`, `SITE_NOINDEX=0` | Hide placeholders and allow search engines at launch. |
| `NEXT_PUBLIC_GA4_ID` | Google Analytics 4. The form sends a `quote_request_saved` event with no personal data. |

## Local development

```
npm install
ADMIN_PASSWORD=test npm run dev
```

Without a Blob token, enquiries and uploads are saved in `.data/`.
