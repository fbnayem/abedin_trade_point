import { createHmac, timingSafeEqual } from "node:crypto";
import { cookies } from "next/headers";

/**
 * Staff sign-in for /admin. Set ADMIN_PASSWORD (and SESSION_SECRET, any long
 * random string) in the Vercel project. Sessions last 12 hours.
 */
const COOKIE = "atp_admin";
const TTL = 12 * 60 * 60 * 1000;
const secret = () => process.env.SESSION_SECRET || process.env.ADMIN_PASSWORD || "";

export const adminConfigured = () => Boolean(process.env.ADMIN_PASSWORD);

function sign(value: string) {
  return createHmac("sha256", secret()).update(value).digest("base64url");
}

export function checkPassword(input: string) {
  const want = Buffer.from(process.env.ADMIN_PASSWORD || "");
  const got = Buffer.from(input || "");
  return want.length > 0 && want.length === got.length && timingSafeEqual(want, got);
}

export function makeSession(name: string) {
  const payload = Buffer.from(JSON.stringify({ n: name.slice(0, 40), e: Date.now() + TTL })).toString("base64url");
  return `${payload}.${sign(payload)}`;
}

export async function currentAdmin(): Promise<string | null> {
  if (!adminConfigured()) return null;
  const raw = (await cookies()).get(COOKIE)?.value;
  if (!raw) return null;
  const [payload, sig] = raw.split(".");
  if (!payload || !sig) return null;
  const good = Buffer.from(sign(payload));
  const given = Buffer.from(sig);
  if (good.length !== given.length || !timingSafeEqual(good, given)) return null;
  try {
    const data = JSON.parse(Buffer.from(payload, "base64url").toString());
    return data.e > Date.now() ? String(data.n || "Staff") : null;
  } catch {
    return null;
  }
}

export const cookieOptions = { name: COOKIE, httpOnly: true, secure: process.env.NODE_ENV === "production", sameSite: "lax" as const, path: "/", maxAge: TTL / 1000 };
