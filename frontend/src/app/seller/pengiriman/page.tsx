"use client";

import { useState } from "react";
import { Truck } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Select } from "@/components/ui/Input";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { DispatchModal } from "@/components/seller/DispatchModal";
import {
  useDispatchShipment,
  useSellerShipments,
} from "@/features/seller";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "pending", label: "Siap dikemas" },
  { value: "dispatched", label: "Dikirim" },
  { value: "in_transit", label: "Dalam perjalanan" },
  { value: "delivered", label: "Diterima" },
];

export default function ShipmentsPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", page: "1" });
  const shipments = useSellerShipments({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });

  const items = shipments.data?.items ?? [];
  const pagination = shipments.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader title="Pengiriman" description="Kelola resi dan status kirim." eyebrow="Penjual" />

      <div>
        <Select
          aria-label="Filter status"
          className="h-9 w-48"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>{status.label}</option>
          ))}
        </Select>
      </div>

      {shipments.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState icon={Truck} title="Belum ada pengiriman" description="Pengiriman muncul setelah pesanan dibayar." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>ID</TH>
                <TH>Resi</TH>
                <TH>Status</TH>
                <TH>Dikirim</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((shipment) => (
                <TR key={shipment.id}>
                  <TD><span className="num text-xs text-ink-muted">#{shipment.id}</span></TD>
                  <TD>
                    <span className="num text-sm">{shipment.tracking_number ?? "-"}</span>
                    <span className="block text-xs text-ink-subtle">
                      {shipment.provider} · {shipment.service_code ?? "-"}
                    </span>
                  </TD>
                  <TD><StatusBadge domain="shipment" value={shipment.status} /></TD>
                  <TD className="text-ink-muted">{formatDateTime(shipment.dispatched_at)}</TD>
                  <TD>
                    <div className="flex justify-end">
                      {shipment.status === "pending" ? <DispatchButton id={shipment.id} /> : null}
                    </div>
                  </TD>
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

function DispatchButton({ id }: { id: number }) {
  const [open, setOpen] = useState(false);
  const dispatch = useDispatchShipment(id);
  const toast = useToast();

  return (
    <>
      <Button size="xs" onClick={() => setOpen(true)}>Kirim</Button>
      <DispatchModal
        open={open}
        onClose={() => setOpen(false)}
        loading={dispatch.isPending}
        title="Kirim pengiriman"
        onSubmit={(payload) =>
          dispatch.mutate(payload, {
            onSuccess: () => { setOpen(false); toast.success("Pengiriman dikirim"); },
            onError: (caught) => toast.error("Gagal", errorMessage(caught)),
          })
        }
      />
    </>
  );
}
