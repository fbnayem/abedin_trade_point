import QuoteCount from "./QuoteCount";

export default function MobileBar() {
  return (
    <div className="atp-mbar">
      <QuoteCount className="atp-mbar__btn" label="Quote List" />
      <a className="atp-mbar__btn atp-mbar__btn--primary" href="/request-a-quote/">Request a Quote</a>
    </div>
  );
}
