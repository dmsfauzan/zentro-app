import type { Tone } from "@/lib/design/tone";
import { toneDot, toneText } from "@/lib/design/tone";

interface StatusMeta {
  label: string;
  tone: Tone;
}

const orderStatuses: Record<string, StatusMeta> = {
  pending_payment: { label: "Menunggu bayar", tone: "warning" },
  paid: { label: "Dibayar", tone: "success" },
  processing: { label: "Diproses", tone: "info" },
  partially_shipped: { label: "Sebagian dikirim", tone: "info" },
  shipped: { label: "Dikirim", tone: "info" },
  completed: { label: "Selesai", tone: "success" },
  cancelled: { label: "Dibatalkan", tone: "danger" },
  expired: { label: "Kedaluwarsa", tone: "danger" },
  refunded: { label: "Direfund", tone: "danger" },
};

const paymentStatuses: Record<string, StatusMeta> = {
  pending: { label: "Menunggu", tone: "warning" },
  requires_action: { label: "Perlu tindakan", tone: "warning" },
  paid: { label: "Lunas", tone: "success" },
  failed: { label: "Gagal", tone: "danger" },
  expired: { label: "Kedaluwarsa", tone: "danger" },
  refunded: { label: "Direfund", tone: "danger" },
  partially_refunded: { label: "Refund sebagian", tone: "danger" },
  chargeback: { label: "Chargeback", tone: "danger" },
};

const shipmentStatuses: Record<string, StatusMeta> = {
  pending: { label: "Siap dikemas", tone: "warning" },
  ready: { label: "Siap kirim", tone: "warning" },
  dispatched: { label: "Dikirim", tone: "info" },
  in_transit: { label: "Dalam perjalanan", tone: "info" },
  delivered: { label: "Diterima", tone: "success" },
  failed: { label: "Gagal kirim", tone: "danger" },
  returned: { label: "Dikembalikan", tone: "danger" },
};

const sellerOrderStatuses: Record<string, StatusMeta> = {
  pending: { label: "Menunggu konfirmasi", tone: "warning" },
  confirmed: { label: "Dikonfirmasi", tone: "info" },
  processing: { label: "Disiapkan", tone: "info" },
  shipped: { label: "Dikirim", tone: "info" },
  delivered: { label: "Diterima", tone: "success" },
  completed: { label: "Selesai", tone: "success" },
  cancelled: { label: "Dibatalkan", tone: "danger" },
  refunded: { label: "Direfund", tone: "danger" },
};

const payoutStatuses: Record<string, StatusMeta> = {
  requested: { label: "Diajukan", tone: "warning" },
  approved: { label: "Disetujui", tone: "info" },
  processing: { label: "Diproses", tone: "info" },
  paid: { label: "Dibayar", tone: "success" },
  rejected: { label: "Ditolak", tone: "danger" },
  failed: { label: "Gagal", tone: "danger" },
  cancelled: { label: "Dibatalkan", tone: "neutral" },
};

const refundStatuses: Record<string, StatusMeta> = {
  requested: { label: "Diajukan", tone: "warning" },
  approved: { label: "Disetujui", tone: "info" },
  processing: { label: "Diproses", tone: "info" },
  succeeded: { label: "Berhasil", tone: "success" },
  failed: { label: "Gagal", tone: "danger" },
  rejected: { label: "Ditolak", tone: "danger" },
};

const commissionStatuses: Record<string, StatusMeta> = {
  pending: { label: "Menunggu settlement", tone: "warning" },
  earned: { label: "Diterima", tone: "success" },
  reversed: { label: "Dibatalkan", tone: "danger" },
};

const sellerStatuses: Record<string, StatusMeta> = {
  pending: { label: "Menunggu dokumen", tone: "warning" },
  under_review: { label: "Ditinjau", tone: "info" },
  active: { label: "Aktif", tone: "success" },
  rejected: { label: "Ditolak", tone: "danger" },
  suspended: { label: "Ditangguhkan", tone: "danger" },
};

const productStatuses: Record<string, StatusMeta> = {
  draft: { label: "Draf", tone: "neutral" },
  active: { label: "Tayang", tone: "success" },
  inactive: { label: "Nonaktif", tone: "neutral" },
  rejected: { label: "Ditolak", tone: "danger" },
  archived: { label: "Diarsipkan", tone: "neutral" },
};

const reviewStatuses: Record<string, StatusMeta> = {
  pending: { label: "Menunggu", tone: "warning" },
  published: { label: "Tayang", tone: "success" },
  hidden: { label: "Disembunyikan", tone: "neutral" },
};

const userStatuses: Record<string, StatusMeta> = {
  active: { label: "Aktif", tone: "success" },
  inactive: { label: "Nonaktif", tone: "neutral" },
  suspended: { label: "Ditangguhkan", tone: "warning" },
  banned: { label: "Diblokir", tone: "danger" },
};

const maps = {
  order: orderStatuses,
  payment: paymentStatuses,
  shipment: shipmentStatuses,
  sellerOrder: sellerOrderStatuses,
  payout: payoutStatuses,
  refund: refundStatuses,
  commission: commissionStatuses,
  seller: sellerStatuses,
  product: productStatuses,
  review: reviewStatuses,
  user: userStatuses,
} as const;

export type StatusDomain = keyof typeof maps;

export function statusMeta(domain: StatusDomain, value: string | null | undefined): StatusMeta {
  if (!value) return { label: "-", tone: "neutral" };

  return maps[domain][value] ?? { label: value, tone: "neutral" };
}

export { toneDot, toneText };
