"use client";

import Link from "next/link";
import { ChevronRight, ReceiptText } from "lucide-react";
import { EmptyState } from "@/components/ui/EmptyState";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { Money } from "@/components/ui/Money";
import { Icon } from "@/components/ui/Icon";
import { useOrders, usePendingPaymentsSync } from "@/features/orders";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";
import { cn } from "@/lib/cn";

const tabs = [
  { value: "", label: "Semua" },
  { value: "pending_payment", label: "Menunggu bayar" },
  { value: "paid", label: "Dibayar" },
  { value: "shipped", label: "Dikirim" },
  { value: "completed", label: "Selesai" },
];

export default function OrdersPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", page: "1" });
  const orders = useOrders({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 10,
  });

  const items = orders.data?.items ?? [];
  const pagination = orders.data?.pagination;

  const pendingOrderNumbers = items
    .filter((order) => order.status === "pending_payment")
    .map((order) => order.order_number);

  // Tarik status pembayaran terbaru dari provider untuk pesanan yang menunggu.
  usePendingPaymentsSync(pendingOrderNumbers);

  return (
    <div className="container-page py-8">
      <h1 className="text-2xl font-semibold tracking-[-0.01em] text-ink">Pesanan saya</h1>

      <div className="mt-4 flex gap-5 overflow-x-auto border-b border-line">
        {tabs.map((tab) => {
          const active = filters.status === tab.value;

          return (
            <button
              key={tab.value}
              type="button"
              onClick={() => setFilters({ status: tab.value, page: "1" })}
              className={cn(
                "-mb-px shrink-0 border-b-2 pb-2.5 text-sm",
                active
                  ? "border-brand font-medium text-ink"
                  : "border-transparent text-ink-muted hover:text-ink",
              )}
            >
              {tab.label}
            </button>
          );
        })}
      </div>

      {pendingOrderNumbers.length > 0 ? (
        <p className="mt-2 text-xs text-ink-subtle" aria-live="polite">
          Status pembayaran yang menunggu diperiksa otomatis.
        </p>
      ) : null}

      {orders.isPending ? (
        <div className="mt-5 space-y-3">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-24 w-full" />
        </div>
      ) : orders.isError ? (
        <p className="mt-6 text-sm text-danger">Gagal memuat pesanan.</p>
      ) : items.length === 0 ? (
        <div className="mt-6">
          <EmptyState
            icon={ReceiptText}
            title="Belum ada pesanan"
            description="Pesanan yang kamu buat akan muncul di sini."
            action={
              <Link href="/produk" className="text-sm font-medium text-brand hover:underline">
                Lihat produk
              </Link>
            }
          />
        </div>
      ) : (
        <div className="mt-5 divide-y divide-line rounded-[12px] border border-line bg-canvas">
          {items.map((order) => (
            <Link
              key={order.id}
              href={`/pesanan/${order.order_number}`}
              className="flex flex-wrap items-center gap-3 px-4 py-4 hover:bg-paper/60"
            >
              <div className="min-w-0 flex-1">
                <p className="num text-sm font-medium text-ink">{order.order_number}</p>
                <p className="mt-0.5 text-xs text-ink-subtle">
                  {formatDateTime(order.created_at)}
                  {order.seller_orders?.length
                    ? ` · ${order.seller_orders.length} penjual`
                    : ""}
                </p>
              </div>
              <StatusBadge domain="order" value={order.status} />
              <Money value={order.grand_total} className="text-sm font-semibold text-ink" />
              <Icon as={ChevronRight} size="xs" className="text-ink-subtle" />
            </Link>
          ))}
        </div>
      )}

      {pagination ? (
        <Pagination
          page={pagination.page}
          lastPage={pagination.last_page}
          onChange={(page) => setFilters({ page: String(page) })}
        />
      ) : null}
    </div>
  );
}
