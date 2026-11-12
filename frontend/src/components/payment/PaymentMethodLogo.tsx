"use client";

import { cn } from "@/lib/cn";

/**
 * Logo metode pembayaran (aset di `public/banks`, lihat README folder tsb).
 * Metode tanpa aset ditampilkan sebagai teks pendek.
 */
const LOGO_SRC: Record<string, string> = {
  bca_va: "/banks/bca.svg",
  bni_va: "/banks/bni.svg",
  bri_va: "/banks/bri.svg",
  mandiri_va: "/banks/mandiri.svg",
  permata_va: "/banks/permata.svg",
  cimb_va: "/banks/cimb.svg",
  qris: "/banks/qris.svg",
  gopay: "/banks/gopay.svg",
};

export function PaymentMethodLogo({
  method,
  label,
  className,
}: {
  method: string | null;
  label?: string | null;
  className?: string;
}) {
  const key = (method ?? "").toLowerCase();
  const src = LOGO_SRC[key];

  if (src) {
    return (
      /* eslint-disable-next-line @next/next/no-img-element */
      <img
        src={src}
        alt=""
        aria-hidden="true"
        className={cn("h-5 w-14 shrink-0 object-contain", className)}
      />
    );
  }

  const text = (label ?? method ?? "").trim().split(" ")[0]?.toUpperCase();

  if (!text) {
    return null;
  }

  return (
    <span
      aria-hidden="true"
      className={cn(
        "shrink-0 text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-subtle",
        className,
      )}
    >
      {text}
    </span>
  );
}
