# Abedin Trade Point website

WordPress + WooCommerce quote-request catalogue for Abedin Trade Point (bulk supply, Bangladesh). There is no checkout: every product leads to **Request a Quote**, with three ways to enquire (quote list, typed requirement, purchase-list upload).

```
wp-content/
  themes/abedin-trade-point/   block theme: design, header/footer, homepage, templates, patterns
  plugins/atp-quote/           quote list, enquiry form, enquiries dashboard, quotation PDF,
                               catalogue rules, setup, site settings, SEO basics
```

Requires WordPress 6.5+, WooCommerce 9+, PHP 8.0+.

## Install

1. Install and activate WooCommerce (skip its setup wizard).
2. Copy `wp-content/themes/abedin-trade-point` and `wp-content/plugins/atp-quote` into the site (or zip each folder and upload it in the admin). Activate the theme, then the plugin.
3. **Enquiries → Setup → Create pages and categories.** This creates every page with the approved copy, the ten product groups and all 31 categories, sets product links to `/products/<category>/` and `/product/<product>/`, and sets permalinks to "Post name".
4. Optional: **Enquiries → Setup → Add sample products** (31 example products with no prices, photos or stock claims) to review the site before real products are ready. One click removes them later.
5. **Enquiries → Site Settings:** phone, WhatsApp, sales email, address, hours, staff notification emails, GA4 ID, company profile PDF.
6. Work through the launch checklist on the Setup screen (it lists only what is still missing).

## What is on the site

| Area | Details |
|---|---|
| Pages | Home, Products, Bulk Supply, Custom Branding, Industries, About, Contact, Request a Quote, FAQs, Ordering & Delivery, Privacy. Selected Work is created as a draft with a case-study layout; publish it when approved examples exist. |
| Catalogue | 10 groups and 31 categories, each with its H1, intro, requirement prompt, quote button and meta title/description from the plan. The 9 launch categories are published; the other 22 are drafts. |
| Draft categories | Tick **Published on website** on a category (Products → Categories) to make it live. Drafts and products only in drafts are hidden from menus, listings, search, sitemaps and the quote list, and return 404 to visitors. Logged-in staff can preview them. A group with no published categories is hidden too. |
| Products menu | The header "Products" item opens View All Categories plus every group that has a published category. It updates automatically. |
| Category pages | Breadcrumbs, intro, product grid, "Help Us Prepare Your Quotation", unlisted-item link, other categories in the group, and the purchase-list prompt. Healthcare categories show the "listed items only, confirmed during quotation" notice. |
| Search | Product search understands the plan's synonyms (T-shirt/tee, khata/exercise book, school bag/backpack, corporate gift/promotional item, and a few more). |
| Enquiries | Status, owner, follow-up date, notes and history on each enquiry; staff email and buyer receipt with failure logging and retry; private file uploads. |
| Quotation PDF | Each enquiry has a **Quotation** box prefilled from the buyer's quote list. Add prices, delivery, VAT and terms, save, then **Open print view / save as PDF**. Each saved change creates a new revision number. |
| SEO | Meta titles and descriptions for pages and categories (editable on each page and category), organisation structured data, no fake price offers, quote page and search results set to noindex. Steps aside automatically if Yoast SEO or Rank Math is installed. |
| Analytics | Add a GA4 measurement ID in Site Settings. A `quote_request_saved` event is pushed once per saved enquiry, with no personal details. Logged-in staff are not tracked. |
| Company profile | Upload the PDF under Media and paste its URL in Site Settings; the **Download Company Profile** button then appears on the About page. |

## Placeholders still to replace

Everything waiting for real business details is highlighted in yellow on the site and listed on **Enquiries → Setup** and the dashboard:

- **Contact details:** shown as `[BUSINESS PHONE]`, `[WHATSAPP NUMBER]`, `[SALES EMAIL]`, `[BUSINESS ADDRESS]`, `[BUSINESS HOURS]` until filled in Site Settings. Turn off "Show highlighted placeholders" before launch; empty fields are then simply hidden.
- **Logo:** a dashed "ATP LOGO" mark shows until the logo is added in Appearance → Editor → Header → Site Logo.
- **Ordering & Delivery and Privacy pages:** published with highlighted `[BRACKETED]` fields (quotation validity, cancellation, issue-reporting timeframe, returns, warranty, retention period, providers, effective date). Edit the pages to replace them.
- **Domain:** nothing in the code depends on the domain. Set it in Settings → General (or `WP_HOME`/`WP_SITEURL`) when the site moves, and run a search-replace if content was built on a staging URL.
- **Products and photos:** sample products use the WooCommerce placeholder image.

## Hosting notes

- Send email through an SMTP service (e.g. Brevo or Amazon SES) with SPF, DKIM and DMARC. Failed emails show on the enquiry with a Retry option.
- Uploads go to `wp-content/uploads/atp-private/` with an Apache deny rule. Better: `define( 'ATP_PRIVATE_DIR', '/path/outside/webroot/atp-private' );` in wp-config.php. On Nginx add `location ^~ /wp-content/uploads/atp-private/ { deny all; }`.
- Exclude `/request-a-quote/` and `/wp-json/atp/` from page caching.
- Use HTTPS, daily backups with a tested restore, and keep the domain, hosting and admin accounts in the business's name.

## Tested

On WordPress 6.5.5, WooCommerce 9.4.2, PHP 8.3, with Playwright at 1280 px and 390 px:

- Quote flow: add from grids and product pages, size required on variable products, quantity edits, error messages keep typed details, valid submit saves one enquiry with uploads, refresh does not duplicate or re-fire the conversion event.
- Catalogue: hub shows 5 groups and 9 published categories; menu lists only those groups; draft category and empty group return 404 to visitors and 200 with a preview note to staff; synonyms find the right products; no horizontal scroll on hub, category and contact pages.
- Admin: launch checklist lists missing items; quotation prefilled from the quote list, totals with delivery and VAT correct, status set to Quoted and logged, print view saved to PDF; visitors cannot open the print view or downloads.

## Not included

Bangla version, online payment/checkout, real product photos and the final logo.
