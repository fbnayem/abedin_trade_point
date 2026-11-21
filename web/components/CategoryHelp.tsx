import { html } from "@/lib/content";

export default function CategoryHelp({ prompt, button, href = "/request-a-quote/" }: { prompt?: string; button?: string; href?: string }) {
  return (
    <section className="cat-help">
      {prompt && <p className="cat-help__prompt">{prompt}</p>}
      {button && (
        <p><a className="btn" href={href}>{button}</a></p>
      )}
      <div className="wp-content" dangerouslySetInnerHTML={{ __html: html("pattern-category-help") }} />
    </section>
  );
}
