"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useRef } from "react";
import {
  ArrowLeft,
  Bell,
  Check,
  CreditCard,
  Package,
  PackageCheck,
  RotateCcw,
  Truck,
  Wallet,
  type LucideIcon,
} from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, CardBody } from "@/components/ui/Card";
import { Icon } from "@/components/ui/Icon";
import { Skeleton } from "@/components/ui/Skeleton";
import { useMarkNotificationRead, useNotification } from "@/features/notifications";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { ApiError, errorMessage } from "@/lib/api/client";
import { formatDateTime } from "@/lib/format";
import type { AppNotification } from "@/lib/api/domain";

const TYPE_META: Record<string, { label: string; icon: LucideIcon }> = {
  "order.paid": { label: "Pembayaran", icon: CreditCard },
  "seller.order.paid": { label: "Pesanan toko", icon: Package },
  "seller.order.completed": { label: "Pesanan selesai", icon: PackageCheck },
  "shipment.delivered": { label: "Pengiriman", icon: Truck },
  "refund.succeeded": { label: "Refund", icon: RotateCcw },
  "payout.paid": { label: "Payout", icon: Wallet },
  "payout.rejected": { label: "Payout", icon: Wallet },
};

function metaFor(type: string): { label: string; icon: LucideIcon } {
  return TYPE_META[type] ?? { label: type.replaceAll(".", " "), icon: Bell };
}

/** Tautan ke halaman terkait berdasarkan payload `data` notifikasi. */
function relatedLink(
  notification: AppNotification,
  isSeller: boolean,
): { href: string; label: string } | null {
  const data = notification.data ?? {};

  if (typeof data.order_number === "string") {
    return { href: `/pesanan/${data.order_number}`, label: "Lihat pesanan" };
  }

  if (data.seller_order_id !== undefined && data.seller_order_id !== null) {
    return isSeller
      ? { href: `/seller/pesanan/${data.seller_order_id}`, label: "Lihat sub-pesanan" }
      : { href: "/pesanan", label: "Lihat daftar pesanan" };
  }

  if (typeof data.refund_number === "string") {
    return { href: "/pesanan", label: "Lihat daftar pesanan" };
  }

  if (typeof data.payout_number === "string") {
    return { href: "/seller/payout", label: "Lihat payout" };
  }

  return null;
}

export default function NotificationDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const toast = useToast();
  const notification = useNotification(id);
  const markRead = useMarkNotificationRead();
  const autoMarked = useRef(false);

  const data = notification.data;

  useEffect(() => {
    if (!data || data.read_at !== null || autoMarked.current) {
      return;
    }

    autoMarked.current = true;
    markRead.mutate(data.id, {
      onError: (caught) => toast.error("Gagal menandai dibaca", errorMessage(caught)),
    });
  }, [data, markRead, toast]);

  if (notification.isPending) {
    return (
      <div className="container-page py-8">
        <Skeleton className="h-6 w-32" />
        <Skeleton className="mt-4 h-56 w-full" />
      </div>
    );
  }

  if (notification.isError || !data) {
    return (
      <div className="container-page py-14">
        <Alert tone="danger" title="Notifikasi tidak ditemukan">
          {notification.error instanceof ApiError && notification.error.status === 404
            ? "Notifikasi ini tidak ada atau sudah dihapus."
            : errorMessage(notification.error)}
        </Alert>
        <div className="mt-4">
          <ButtonLink href="/notifikasi" variant="secondary">
            Kembali ke notifikasi
          </ButtonLink>
        </div>
      </div>
    );
  }

  const meta = metaFor(data.type);
  const link = relatedLink(data, (user?.roles ?? []).includes("seller"));
  const unread = data.read_at === null;

  return (
    <div className="container-page py-8">
      <Link
        href="/notifikasi"
        className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink"
      >
        <Icon as={ArrowLeft} size="xs" />
        Semua notifikasi
      </Link>

      <Card className="mt-4 border-l-4 border-l-brand">
        <CardBody className="space-y-5">
          <div className="flex flex-wrap items-center gap-3">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand">
              <Icon as={meta.icon} size="md" />
            </span>
            <div className="min-w-0">
              <p className="text-xs font-medium uppercase tracking-[0.12em] text-ink-subtle">
                {meta.label}
              </p>
              <p className="text-xs text-ink-subtle">{formatDateTime(data.created_at)}</p>
            </div>
            <span className="ml-auto flex items-center gap-2">
              {unread ? (
                <span className="rounded-[4px] bg-brand px-1.5 py-0.5 text-[10px] font-medium text-white">
                  Baru
                </span>
              ) : (
                <span className="flex items-center gap-1 text-xs text-ink-subtle">
                  <Icon as={Check} size="xs" />
                  Dibaca
                </span>
              )}
            </span>
          </div>

          <div>
            <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink">{data.title}</h1>
            <p className="mt-2 whitespace-pre-line text-sm leading-6 text-ink-muted">{data.body}</p>
          </div>

          <dl className="grid gap-2 border-t border-line pt-4 text-sm sm:grid-cols-2">
            <div className="flex justify-between gap-4 sm:block">
              <dt className="text-ink-subtle">Tipe</dt>
              <dd className="num text-ink">{data.type}</dd>
            </div>
            <div className="flex justify-between gap-4 sm:block">
              <dt className="text-ink-subtle">Diterima</dt>
              <dd className="text-ink">{formatDateTime(data.created_at)}</dd>
            </div>
            {data.read_at ? (
              <div className="flex justify-between gap-4 sm:block">
                <dt className="text-ink-subtle">Dibaca</dt>
                <dd className="text-ink">{formatDateTime(data.read_at)}</dd>
              </div>
            ) : null}
          </dl>

          <div className="flex flex-wrap gap-2 border-t border-line pt-4">
            {link ? (
              <ButtonLink href={link.href} size="sm">
                {link.label}
              </ButtonLink>
            ) : null}
            {unread ? (
              <Button
                size="sm"
                variant="secondary"
                loading={markRead.isPending}
                onClick={() =>
                  markRead.mutate(data.id, {
                    onSuccess: () => toast.success("Ditandai sudah dibaca"),
                    onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                  })
                }
              >
                Tandai dibaca
              </Button>
            ) : null}
            <ButtonLink href="/notifikasi" size="sm" variant="ghost">
              Kembali
            </ButtonLink>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
