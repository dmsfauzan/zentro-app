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
import { useSellerCommissions } from "@/features/seller";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "pending", label: "Menunggu settlement" },
  { value: "earned", label: "Diterima" },
  { value: "reversed", label: "Dibatalkan" },
];

export default function SellerCommissionsPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", page: "1" });
  const commissions = useSellerCommissions({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });

  const items = commissions.data?.items ?? [];
  const pagination = commissions.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader
        title="Komisi"
        description="Potongan komisi platform per sub-pesanan pada tokomu."
        eyebrow="Keuangan"
      />

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

      {commissions.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState
          title="Belum ada komisi"
          description="Komisi muncul setelah pembayaran pesanan berhasil."
        />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Sub-pesanan</TH>
                <TH numeric>Basis</TH>
                <TH numeric>Rate</TH>
                <TH numeric>Komisi</TH>
                <TH>Status</TH>
                <TH>Settlement</TH>
              </TR>
            </THead>
            <TBody>
              {items.map((commission) => (
                <TR key={commission.id}>
                  <TD>
                    <span className="num text-sm">
                      {commission.seller_order_number ?? `#${commission.seller_order_id}`}
                    </span>
                    <span className="block text-xs text-ink-subtle">
                      {formatDateTime(commission.created_at)}
                    </span>
                  </TD>
                  <TD numeric>
                    <Money value={commission.base_amount} />
                  </TD>
                  <TD numeric>{(commission.rate_bp / 100).toFixed(2)}%</TD>
                  <TD numeric>
                    <Money value={commission.commission_amount} />
                  </TD>
                  <TD>
                    <StatusBadge domain="commission" value={commission.status} />
                  </TD>
                  <TD className="text-ink-muted">{formatDateTime(commission.earned_at)}</TD>
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
