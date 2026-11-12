"use client";

import { useState } from "react";
import { Plus, Search } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { Money } from "@/components/ui/Money";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { Icon } from "@/components/ui/Icon";
import {
  useAdminCreateRefund,
  useAdminRefunds,
  useApproveRefund,
  useRejectRefund,
} from "@/features/admin";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { apiFetch, errorMessage } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { OrderDetail } from "@/lib/api/domain";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "requested", label: "Diajukan" },
  { value: "processing", label: "Diproses" },
  { value: "succeeded", label: "Berhasil" },
  { value: "rejected", label: "Ditolak" },
  { value: "failed", label: "Gagal" },
];

export default function AdminRefundsPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", page: "1" });
  const refunds = useAdminRefunds({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });
  const approve = useApproveRefund();
  const reject = useRejectRefund();
  const create = useAdminCreateRefund();
  const { token } = useAuth();
  const toast = useToast();

  const [createOpen, setCreateOpen] = useState(false);
  const [lookup, setLookup] = useState("");
  const [order, setOrder] = useState<OrderDetail | null>(null);
  const [sellerOrderId, setSellerOrderId] = useState("");
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");
  const [rejectTarget, setRejectTarget] = useState<number | null>(null);
  const [rejectReason, setRejectReason] = useState("");

  const items = refunds.data?.items ?? [];
  const pagination = refunds.data?.pagination;

  const lookupOrder = async () => {
    try {
      const detail = await apiFetch<OrderDetail>(endpoints.orders.detail(lookup.trim()), { token });
      setOrder(detail);
      setSellerOrderId(String(detail.seller_orders?.[0]?.id ?? ""));
      setAmount(String(detail.seller_orders?.[0]?.grand_total ?? ""));
    } catch (caught) {
      toast.error("Pesanan tidak ditemukan", errorMessage(caught));
    }
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Refund"
        description="Ajukan, setujui, atau tolak refund per sub-pesanan."
        eyebrow="Admin"
        actions={
          <Button size="sm" onClick={() => setCreateOpen(true)}>
            <Icon as={Plus} size="xs" />
            Refund baru
          </Button>
        }
      />

      <div>
        <Select
          aria-label="Status"
          className="h-9 w-44"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>{status.label}</option>
          ))}
        </Select>
      </div>

      {refunds.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Tidak ada refund" description="Tidak ada data pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Nomor</TH>
                <TH>Sub-pesanan</TH>
                <TH>Status</TH>
                <TH numeric>Jumlah</TH>
                <TH>Diajukan</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((refund) => (
                <TR key={refund.id}>
                  <TD><span className="num text-sm">{refund.refund_number}</span></TD>
                  <TD className="num text-ink-muted">#{refund.seller_order_id ?? "-"}</TD>
                  <TD><StatusBadge domain="refund" value={refund.status} /></TD>
                  <TD numeric><Money value={refund.amount} /></TD>
                  <TD className="text-ink-muted">{formatDateTime(refund.created_at)}</TD>
                  <TD>
                    <div className="flex flex-wrap justify-end gap-1.5">
                      {["requested", "approved", "processing"].includes(refund.status) ? (
                        <>
                          <Button
                            size="xs"
                            loading={approve.isPending}
                            onClick={() =>
                              approve.mutate(refund.id, {
                                onSuccess: () => toast.success("Refund diproses"),
                                onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                              })
                            }
                          >
                            Setujui
                          </Button>
                          <Button size="xs" variant="ghost" onClick={() => { setRejectTarget(refund.id); setRejectReason(""); }}>
                            Tolak
                          </Button>
                        </>
                      ) : null}
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

      <Modal
        open={createOpen}
        onOpenChange={setCreateOpen}
        title="Refund baru"
        description="Cari pesanan lalu pilih sub-pesanan yang direfund."
        footer={
          <>
            <Button variant="ghost" onClick={() => setCreateOpen(false)}>Batal</Button>
            <Button
              loading={create.isPending}
              disabled={!order || !sellerOrderId || !amount || !reason.trim()}
              onClick={() => {
                if (!order) return;
                create.mutate(
                  {
                    order_number: order.order_number,
                    seller_order_id: Number(sellerOrderId),
                    amount: Number(amount),
                    reason,
                  },
                  {
                    onSuccess: () => {
                      setCreateOpen(false);
                      setOrder(null);
                      setLookup("");
                      setReason("");
                      toast.success("Permintaan refund dibuat");
                    },
                    onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                  },
                );
              }}
            >
              Buat refund
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          <div className="flex items-end gap-2">
            <Field label="Nomor pesanan" htmlFor="lookup" className="flex-1">
              <Input id="lookup" value={lookup} onChange={(event) => setLookup(event.target.value)} placeholder="ZTR-20261110-000001" />
            </Field>
            <Button type="button" variant="secondary" size="sm" onClick={() => void lookupOrder()}>
              <Icon as={Search} size="xs" />
              Cari
            </Button>
          </div>

          {order ? (
            <>
              <Alert tone="info" title={`Pesanan ${order.order_number}`}>
                Total {new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(order.grand_total)}
              </Alert>
              <Field label="Sub-pesanan" htmlFor="seller_order">
                <Select
                  id="seller_order"
                  value={sellerOrderId}
                  onChange={(event) => {
                    setSellerOrderId(event.target.value);
                    const selected = order.seller_orders?.find((item) => item.id === Number(event.target.value));
                    setAmount(String(selected?.grand_total ?? ""));
                  }}
                >
                  {(order.seller_orders ?? []).map((sellerOrder) => (
                    <option key={sellerOrder.id} value={sellerOrder.id}>
                      {sellerOrder.seller_order_number} · {sellerOrder.status} · Rp {sellerOrder.grand_total.toLocaleString("id-ID")}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label="Jumlah refund (Rp)" htmlFor="amount" required>
                <Input id="amount" inputMode="numeric" value={amount} onChange={(event) => setAmount(event.target.value)} />
              </Field>
              <Field label="Alasan" htmlFor="reason" required>
                <Textarea id="reason" value={reason} onChange={(event) => setReason(event.target.value)} />
              </Field>
            </>
          ) : null}
        </div>
      </Modal>

      <Modal
        open={rejectTarget !== null}
        onOpenChange={(open) => { if (!open) setRejectTarget(null); }}
        title="Tolak refund"
        footer={
          <>
            <Button variant="ghost" onClick={() => setRejectTarget(null)}>Batal</Button>
            <Button
              variant="danger"
              loading={reject.isPending}
              disabled={!rejectReason.trim()}
              onClick={() => {
                if (rejectTarget === null) return;
                reject.mutate(
                  { id: rejectTarget, reason: rejectReason },
                  {
                    onSuccess: () => { setRejectTarget(null); toast.success("Refund ditolak"); },
                    onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                  },
                );
              }}
            >
              Tolak refund
            </Button>
          </>
        }
      >
        <Field label="Alasan" htmlFor="reject_reason" required>
          <Textarea id="reject_reason" value={rejectReason} onChange={(event) => setRejectReason(event.target.value)} />
        </Field>
      </Modal>
    </div>
  );
}
