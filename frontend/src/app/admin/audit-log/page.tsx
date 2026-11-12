"use client";

import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Input, Select } from "@/components/ui/Input";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useAdminAuditLogs } from "@/features/admin";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";

const entityTypes = [
  { value: "", label: "Semua entitas" },
  { value: "order", label: "Order" },
  { value: "seller_order", label: "Sub-pesanan" },
  { value: "payment", label: "Payment" },
  { value: "refund", label: "Refund" },
  { value: "seller_payout", label: "Payout" },
  { value: "product", label: "Produk" },
  { value: "seller", label: "Penjual" },
];

export default function AdminAuditLogPage() {
  const [filters, setFilters] = useUrlFilters({ action: "", entity_type: "", actor_id: "", page: "1" });
  const logs = useAdminAuditLogs({
    action: filters.action || undefined,
    entity_type: filters.entity_type || undefined,
    actor_id: filters.actor_id || undefined,
    page: filters.page,
    per_page: 20,
  });

  const items = logs.data?.items ?? [];
  const pagination = logs.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader
        title="Audit log"
        description="Jejak aksi penting platform (read-only)."
        eyebrow="Admin"
      />

      <div className="flex flex-wrap gap-2">
        <Input
          aria-label="Cari aksi"
          className="h-9 w-56"
          value={filters.action}
          placeholder="Cari aksi (mis. payout.paid)"
          onChange={(event) => setFilters({ action: event.target.value, page: "1" })}
        />
        <Select
          aria-label="Entitas"
          className="h-9 w-44"
          value={filters.entity_type}
          onChange={(event) => setFilters({ entity_type: event.target.value, page: "1" })}
        >
          {entityTypes.map((entity) => (
            <option key={entity.value} value={entity.value}>
              {entity.label}
            </option>
          ))}
        </Select>
        <Input
          aria-label="Actor ID"
          className="h-9 w-36"
          inputMode="numeric"
          value={filters.actor_id}
          placeholder="Actor ID"
          onChange={(event) => setFilters({ actor_id: event.target.value, page: "1" })}
        />
      </div>

      {logs.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Belum ada audit log" description="Tidak ada data pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Aksi</TH>
                <TH>Entitas</TH>
                <TH>Actor</TH>
                <TH>IP</TH>
                <TH>Waktu</TH>
              </TR>
            </THead>
            <TBody>
              {items.map((log) => (
                <TR key={log.id}>
                  <TD>
                    <span className="font-medium text-ink">{log.action}</span>
                  </TD>
                  <TD className="text-ink-muted">
                    {log.entity_type}
                    <span className="num"> #{log.entity_id}</span>
                  </TD>
                  <TD className="num text-ink-muted">{log.actor_id ?? "-"}</TD>
                  <TD className="num text-ink-subtle">{log.ip_address ?? "-"}</TD>
                  <TD className="text-ink-muted">{formatDateTime(log.created_at)}</TD>
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
