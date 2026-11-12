"use client";

import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Select } from "@/components/ui/Input";
import { Money } from "@/components/ui/Money";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useAdminPayments } from "@/features/admin";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "pending", label: "Menunggu" },
  { value: "requires_action", label: "Perlu tindakan" },
  { value: "paid", label: "Lunas" },
  { value: "failed", label: "Gagal" },
  { value: "expired", label: "Kedaluwarsa" },
  { value: "refunded", label: "Direfund" },
  { value: "partially_refunded", label: "Refund sebagian" },
];

const providers = [
  { value: "", label: "Semua provider" },
  { value: "midtrans", label: "Midtrans" },
  { value: "fake", label: "Fake (dev)" },
];

export default function AdminPaymentsPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", provider: "", page: "1" });
  const payments = useAdminPayments({
    status: filters.status || undefined,
    provider: filters.provider || undefined,
    page: filters.page,
    per_page: 20,
  });

  const items = payments.data?.items ?? [];
  const pagination = payments.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader
        title="Pembayaran"
        description="Riwayat percobaan pembayaran lintas pesanan (read-only)."
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
        <Select
          aria-label="Provider"
          className="h-9 w-44"
          value={filters.provider}
          onChange={(event) => setFilters({ provider: event.target.value, page: "1" })}
        >
          {providers.map((provider) => (
            <option key={provider.value} value={provider.value}>
              {provider.label}
            </option>
          ))}
        </Select>
      </div>

      {payments.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Tidak ada pembayaran" description="Tidak ada data pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Pesanan</TH>
                <TH>Provider</TH>
                <TH>Metode</TH>
                <TH>Status</TH>
                <TH numeric>Jumlah</TH>
                <TH>Dibayar</TH>
              </TR>
            </THead>
            <TBody>
              {items.map((payment, index) => (
                <TR key={`${payment.order_number ?? "payment"}-${index}`}>
                  <TD>
                    <span className="num text-sm">{payment.order_number ?? "-"}</span>
                  </TD>
                  <TD className="capitalize text-ink-muted">{payment.provider}</TD>
                  <TD className="text-ink-muted">{payment.method ?? "-"}</TD>
                  <TD>
                    <StatusBadge domain="payment" value={payment.status} />
                  </TD>
                  <TD numeric>
                    <Money value={payment.amount} />
                  </TD>
                  <TD className="text-ink-muted">{formatDateTime(payment.paid_at)}</TD>
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
    </div>
  );
}
