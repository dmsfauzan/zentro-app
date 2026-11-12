"use client";

import Link from "next/link";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Select } from "@/components/ui/Input";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { Money } from "@/components/ui/Money";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useConfirmSellerOrder, useSellerOrders } from "@/features/seller";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "pending", label: "Menunggu konfirmasi" },
  { value: "confirmed", label: "Dikonfirmasi" },
  { value: "shipped", label: "Dikirim" },
  { value: "delivered", label: "Diterima" },
  { value: "completed", label: "Selesai" },
];

export default function SellerOrdersPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", page: "1" });
  const orders = useSellerOrders({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });

  const items = orders.data?.items ?? [];
  const pagination = orders.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader title="Pesanan" description="Konfirmasi dan proses pesanan yang masuk." eyebrow="Penjual" />

      <div>
        <Select
          aria-label="Filter status"
          className="h-9 w-52"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>{status.label}</option>
          ))}
        </Select>
      </div>

      {orders.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Belum ada pesanan" description="Pesanan baru akan muncul di sini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Sub-pesanan</TH>
                <TH>Tanggal</TH>
                <TH>Status</TH>
                <TH numeric>Total</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((order) => (
                <TR key={order.id}>
                  <TD><span className="num text-sm">{order.seller_order_number}</span></TD>
                  <TD className="text-ink-muted">{formatDateTime(order.created_at ?? null)}</TD>
                  <TD><StatusBadge domain="sellerOrder" value={order.status} /></TD>
                  <TD numeric><Money value={order.grand_total} /></TD>
                  <TD>
                    <div className="flex items-center justify-end gap-2">
                      {order.status === "pending" ? <ConfirmButton id={order.id} /> : null}
                      <Link href={`/seller/pesanan/${order.id}`} className="text-sm font-medium text-brand hover:underline">
                        Detail
                      </Link>
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

function ConfirmButton({ id }: { id: number }) {
  const confirm = useConfirmSellerOrder(id);
  const toast = useToast();

  return (
    <Button
      size="xs"
      loading={confirm.isPending}
      onClick={() =>
        confirm.mutate(undefined, {
          onSuccess: () => toast.success("Pesanan dikonfirmasi"),
          onError: (caught) => toast.error("Gagal", errorMessage(caught)),
        })
      }
    >
      Konfirmasi
    </Button>
  );
}
