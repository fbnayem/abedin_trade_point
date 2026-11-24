import type { Metadata } from "next";
import { allCredits, getSubcategory, productUrl } from "@/lib/catalogue";
import Crumbs from "@/components/Crumbs";

export const metadata: Metadata = { title: "Image Credits | Abedin Trade Point", description: "Sources and licences for the sample product photos used on this website.", alternates: { canonical: "/image-credits/" } };

export default function Credits() {
  const list = allCredits();
  return (
    <div className="container" style={{ paddingBottom: "4rem" }}>
      <Crumbs items={[{ name: "Image Credits" }]} />
      <h1>Image Credits</h1>
      <p className="lead">
        Sample product photos on this website are free-licence images from Wikimedia Commons and Openverse. They show the type of product and are replaced with Abedin Trade Point&rsquo;s own photos over time. The product supplied is always the one described and agreed in your quotation.
      </p>
      {list.length === 0 ? (
        <p className="atp-empty">No sample photos are in use.</p>
      ) : (
        <div style={{ overflowX: "auto" }}>
          <table className="specs">
            <thead><tr><th>Product</th><th>Photo</th><th>Author</th><th>Licence</th></tr></thead>
            <tbody>
              {list.map(({ product, credit }) => (
                <tr key={product.slug}>
                  <td><a href={productUrl(product)}>{product.name}</a><br /><small className="has-muted-color">{getSubcategory(product.subcategory)?.name}</small></td>
                  <td><a href={credit!.source} target="_blank" rel="noopener nofollow">{(credit!.title || "Source").replace(/^File:/, "")}</a><br /><small className="has-muted-color">via {credit!.via}</small></td>
                  <td>{credit!.author}</td>
                  <td>{credit!.licenseUrl ? <a href={credit!.licenseUrl} target="_blank" rel="noopener nofollow">{credit!.license}</a> : credit!.license}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
