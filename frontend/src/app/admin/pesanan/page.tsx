"use client";

import Link from "next/link";
import { Search } from "lucide-react";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Input, Select } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useAdminOrders } from "@/features/admin";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "pending_payment", label: "Menunggu bayar" },
  { value: "paid", label: "Dibayar" },
  { value: "processing", label: "Diproses" },
  { value: "partially_shipped", label: "Sebagian dikirim" },
  { value: "shipped", label: "Dikirim" },
  { value: "completed", label: "Selesai" },
  { value: "cancelled", label: "Dibatalkan" },
  { value: "expired", label: "Kedaluwarsa" },
  { value: "refunded", label: "Direfund" },
];

export default function AdminOrdersPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", q: "", page: "1" });
  const orders = useAdminOrders({
    status: filters.status || undefined,
    q: filters.q || undefined,
    page: filters.page,
    per_page: 20,
  });

  const items = orders.data?.items ?? [];
  const pagination = orders.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader
        title="Pesanan"
        description="Daftar seluruh pesanan lintas penjual (read-only)."
        eyebrow="Admin"
      />

      <div className="flex flex-wrap gap-2">
        <Select
          aria-label="Status"
          className="h-9 w-48"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>
              {status.label}
            </option>
          ))}
        </Select>
        <div className="relative">
          <span className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-subtle">
            <Icon as={Search} size="xs" />
          </span>
          <Input
            aria-label="Cari nomor pesanan"
            className="h-9 w-64 pl-8"
            value={filters.q}
            placeholder="Cari nomor pesanan"
            onChange={(event) => setFilters({ q: event.target.value, page: "1" })}
          />
        </div>
      </div>

      {orders.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Tidak ada pesanan" description="Tidak ada data pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Nomor</TH>
                <TH>Pembeli</TH>
                <TH>Status</TH>
                <TH numeric>Total</TH>
                <TH>Dibuat</TH>
              </TR>
            </THead>
            <TBody>
              {items.map((order) => (
                <TR key={order.id}>
                  <TD>
                    <span className="num text-sm">{order.order_number}</span>
                  </TD>
                  <TD>
                    <span className="block text-sm text-ink">{order.buyer?.name ?? "-"}</span>
                    <span className="block text-xs text-ink-subtle">{order.buyer?.email ?? ""}</span>
                  </TD>
                  <TD>
                    <StatusBadge domain="order" value={order.status} />
                  </TD>
                  <TD numeric>
                    <Money value={order.grand_total} />
                  </TD>
                  <TD className="text-ink-muted">{formatDateTime(order.created_at)}</TD>
                </TR>
              ))}
            </TBody>
          </TableWrap>
        </Card>
      )}

      {pagination ? (
        <Pagination
          page={pagination.page}
          lastPage={pagination.last_page}
          onChange={(page) => setFilters({ page: String(page) })}
        />
      ) : null}

      <p className="text-xs text-ink-subtle">
        Perlu tindakan? Buka{" "}
        <Link href="/admin/refund" className="text-brand hover:underline">
          Refund
        </Link>{" "}
        atau{" "}
        <Link href="/admin/payout" className="text-brand hover:underline">
          Payout
        </Link>
        .
      </p>
    </div>
  );
}
