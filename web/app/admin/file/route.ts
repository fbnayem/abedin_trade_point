import { currentAdmin } from "@/lib/auth";
import { getEnquiry } from "@/lib/admin-data";
import { readBytes } from "@/lib/store";
import { ALLOWED_TYPES } from "@/lib/enquiry";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

export async function GET(request: Request) {
  if (!(await currentAdmin())) return new Response("Sign in first.", { status: 401 });
  const url = new URL(request.url);
  const e = await getEnquiry(url.searchParams.get("ref") || "");
  const f = e?.files[Number(url.searchParams.get("i"))];
  if (!f) return new Response("Not found", { status: 404 });
  const file = await readBytes(f.pathname);
  if (!file) return new Response("Not found", { status: 404 });
  const ext = f.name.split(".").pop()?.toLowerCase() || "";
  return new Response(new Uint8Array(file.data), {
    headers: {
      "Content-Type": ALLOWED_TYPES[ext] || "application/octet-stream",
      "Content-Disposition": `attachment; filename="${f.name.replace(/[^\w.\- ]+/g, "_")}"`,
      "Cache-Control": "no-store",
      "X-Content-Type-Options": "nosniff",
    },
  });
}
