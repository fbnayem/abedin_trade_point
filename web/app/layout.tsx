import type { Metadata, Viewport } from "next";
import Script from "next/script";
import "./globals.css";
import { site } from "@/lib/site";

export const metadata: Metadata = {
  metadataBase: new URL(site.url),
  title: { default: "Bulk Supplies in Bangladesh | Abedin Trade Point", template: "%s" },
  description: "Request quotations for garments, bags, stationery, corporate gifts and other bulk supplies in Bangladesh. Send one list for several items.",
  robots: site.noindex ? { index: false, follow: false } : { index: true, follow: true },
  openGraph: { siteName: site.name, type: "website", locale: "en_GB" },
  icons: { icon: "/img/logo-placeholder.svg" },
};

export const viewport: Viewport = { themeColor: "#1F5F99", width: "device-width", initialScale: 1 };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  const org = {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: site.name,
    url: site.url,
    areaServed: "BD",
    ...(site.phone ? { telephone: site.phone } : {}),
    ...(site.email ? { email: site.email } : {}),
    ...(site.address ? { address: site.address } : {}),
  };
  return (
    <html lang="en-GB">
      <head>
        <link rel="preload" href="/fonts/Inter-Variable.woff2" as="font" type="font/woff2" crossOrigin="" />
      </head>
      <body>
        {children}
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(org) }} />
        {site.ga4 && (
          <>
            <Script src={`https://www.googletagmanager.com/gtag/js?id=${site.ga4}`} strategy="afterInteractive" />
            <Script id="ga4" strategy="afterInteractive">{`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${site.ga4}',{anonymize_ip:true});`}</Script>
          </>
        )}
      </body>
    </html>
  );
}
