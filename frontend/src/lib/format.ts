/**
 * Pemformatan nilai untuk konteks Indonesia.
 */

const rupiah = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  maximumFractionDigits: 0,
});

/** Semua nilai uang ZENTRO adalah integer rupiah. */
export function formatRupiah(amount: number): string {
  return rupiah.format(amount);
}

export function formatNumber(value: number): string {
  return new Intl.NumberFormat("id-ID").format(value);
}

export function formatDateTime(value: string | Date | null): string {
  if (!value) return "-";

  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

export function formatDate(value: string | Date | null): string {
  if (!value) return "-";

  return new Intl.DateTimeFormat("id-ID", { dateStyle: "medium" }).format(
    new Date(value),
  );
}

export function maskAccountNumber(value: string | null): string {
  if (!value) return "-";
  if (value.length <= 4) return "*".repeat(value.length);

  return `${"*".repeat(value.length - 4)}${value.slice(-4)}`;
}

/** Relatif; panggil hanya di klien (hindari Date.now saat prerender). */
export function formatRelative(value: string | Date | null): string {
  if (!value) return "-";

  const diff = Date.now() - new Date(value).getTime();
  const minutes = Math.round(diff / 60_000);

  if (Math.abs(minutes) < 1) return "baru saja";
  if (minutes < 60) return `${minutes} menit lalu`;

  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours} jam lalu`;

  const days = Math.round(hours / 24);
  if (days < 30) return `${days} hari lalu`;

  return formatDate(value);
}
