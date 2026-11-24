import Header from "@/components/Header";
import Footer from "@/components/Footer";
import MobileBar from "@/components/MobileBar";
import Toast from "@/components/Toast";

export default function SiteLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <a className="skip-link" href="#main">Skip to content</a>
      <Header />
      <main id="main" style={{ overflowX: "clip" }}>{children}</main>
      <Footer />
      <MobileBar />
      <Toast />
    </>
  );
}
