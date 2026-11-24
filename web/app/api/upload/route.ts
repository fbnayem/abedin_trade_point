import { NextResponse } from "next/server";
import { handleUpload, type HandleUploadBody } from "@vercel/blob/client";
import { randomBytes } from "node:crypto";
import { ALLOWED_TYPES, MAX_BYTES, sniff } from "@/lib/enquiry";
import { storageReady, usingBlob, writeBytes } from "@/lib/store";

export const runtime = "nodejs";

const okName = (p: string) => /^uploads\/[A-Za-z0-9_-]{8,40}\/[^/]{1,160}$/.test(p) && (p.split(".").pop()?.toLowerCase() || "") in ALLOWED_TYPES;

/** Tells the browser which upload method to use. */
export async function GET() {
  return NextResponse.json({ mode: usingBlob() ? "blob" : storageReady() ? "local" : "off" });
}

export async function POST(request: Request) {
  // Local development: the file comes straight to this route.
  if (!usingBlob()) {
    if (!storageReady()) return NextResponse.json({ error: "Uploads are not available yet." }, { status: 503 });
    const form = await request.formData();
    const file = form.get("file");
    if (!(file instanceof File) || file.size > MAX_BYTES) return NextResponse.json({ error: "too_large" }, { status: 400 });
    const name = file.name.replace(/[^\w.\- ]+/g, "_").slice(-120);
    const buf = Buffer.from(await file.arrayBuffer());
    if (!sniff(buf, name)) return NextResponse.json({ error: "bad_type" }, { status: 400 });
    const pathname = `uploads/${randomBytes(12).toString("base64url")}/${name}`;
    await writeBytes(pathname, buf, file.type || "application/octet-stream");
    return NextResponse.json({ pathname });
  }

  // Vercel Blob: issue a short-lived token so the browser uploads directly.
  const body = (await request.json()) as HandleUploadBody;
  try {
    const result = await handleUpload({
      body,
      request,
      onBeforeGenerateToken: async (pathname) => {
        if (!okName(pathname)) throw new Error("File type not allowed");
        return {
          allowedContentTypes: [...new Set(Object.values(ALLOWED_TYPES))],
          maximumSizeInBytes: MAX_BYTES,
          addRandomSuffix: false,
          allowOverwrite: false,
          validUntil: Date.now() + 10 * 60 * 1000,
        };
      },
    });
    return NextResponse.json(result);
  } catch (e) {
    return NextResponse.json({ error: (e as Error).message }, { status: 400 });
  }
}
