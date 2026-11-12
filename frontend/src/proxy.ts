import { NextResponse, type NextRequest } from "next/server";

/**
 * Guard UX (bukan authz final — otorisasi data tetap di Laravel).
 * Next.js 16: konvensi `middleware` diganti `proxy`.
 */
const PROTECTED_PREFIXES = [
  "/seller",
  "/admin",
  "/keranjang",
  "/checkout",
  "/pembayaran",
  "/pesanan",
  "/akun",
  "/notifikasi",
];

export function proxy(request: NextRequest) {
  const { pathname, search } = request.nextUrl;
  const authed = request.cookies.has("zentro_auth");
  const isProtected = PROTECTED_PREFIXES.some(
    (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`),
  );

  if (!authed && isProtected) {
    const next = `${pathname}${search}`;
    const url = new URL(`/login?next=${encodeURIComponent(next)}`, request.url);

    return NextResponse.redirect(url);
  }

  // Catatan: jangan redirect `/login` hanya karena cookie penanda ada.
  // Cookie penanda bisa tertinggal sementara token sesi sudah hilang; halaman
  // login sendiri yang memutuskan redirect setelah AuthProvider sinkron.

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|icon.png|apple-icon.png|brand/).*)"],
};
