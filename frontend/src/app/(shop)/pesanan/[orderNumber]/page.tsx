"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useState } from "react";
import { MapPin, RefreshCw, Star, Truck } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { Money } from "@/components/ui/Money";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { Textarea } from "@/components/ui/Input";
import { ReviewDialog } from "@/components/shop/ReviewDialog";
import {
  useCancelOrder,
  useConfirmReceived,
  useOrder,
  useOrderRefunds,
  usePaymentSync,
  useRequestRefund,
} from "@/features/orders";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import type { OrderItem } from "@/lib/api/domain";
import { formatDateTime } from "@/lib/format";

export default function OrderDetailPage() {
  const { orderNumber } = useParams<{ orderNumber: string }>();
  const { token } = useAuth();
  const toast = useToast();
  const order = useOrder(orderNumber);
  const cancel = useCancelOrder(orderNumber);
  const confirmReceived = useConfirmReceived(orderNumber);
  const refunds = useOrderRefunds(orderNumber);
  const requestRefund = useRequestRefund(orderNumber);
  // Selama menunggu bayar, status ditarik dari provider secara berkala.
  const autoSync = usePaymentSync(orderNumber, order.data?.status === "pending_payment");
  const [cancelOpen, setCancelOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [refundTarget, setRefundTarget] = useState<{ id: number; label: string } | null>(null);
  const [refundReason, setRefundReason] = useState("");
  const [reviewItem, setReviewItem] = useState<OrderItem | null>(null);
  const [reviewedIds, setReviewedIds] = useState<number[]>([]);

  const isReviewed = (item: OrderItem) =>
    item.reviewed === true || reviewedIds.includes(item.id);

  const refundFor = (sellerOrderId: number) =>
    (refunds.data ?? []).find((refund) => refund.seller_order_id === sellerOrderId);

  if (order.isPending) {
    return <div className="container-page py-8"><Skeleton className="h-96 w-full" /></div>;
  }

  if (order.isError || !order.data) {
    return (
      <div className="container-page py-14">
        <Alert tone="danger" title="Pesanan tidak ditemukan">
          {errorMessage(order.error)}
        </Alert>
      </div>
    );
  }

  const data = order.data;
  const address = data.addresses?.find((item) => item.type === "shipping") ?? data.addresses?.[0];
  const canPay = data.status === "pending_payment";

  return (
    <div className="container-page py-8">
      <nav className="mb-4 flex items-center gap-1 text-[13px] text-ink-subtle" aria-label="Breadcrumb">
        <Link href="/pesanan" className="hover:text-brand">Pesanan</Link>
        <span aria-hidden="true">/</span>
        <span className="num text-ink">{data.order_number}</span>
      </nav>

      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="num text-2xl font-semibold tracking-[-0.01em] text-ink">
            {data.order_number}
          </h1>
          <p className="mt-1 text-sm text-ink-muted">
            Dibuat {formatDateTime(data.created_at)}
          </p>
        </div>
        <StatusBadge domain="order" value={data.status} />
      </div>

      <div className="mt-5 flex flex-wrap gap-2">
        {canPay ? (
          <>
            <ButtonLink href={`/pembayaran/${data.order_number}`}>Bayar sekarang</ButtonLink>
            <Button variant="secondary" onClick={() => setCancelOpen(true)}>
              Batalkan
            </Button>
          </>
        ) : null}
        {data.paid_at ? (
          <>
            <span className="inline-flex h-11 items-center text-sm text-ink-muted">
              Dibayar {formatDateTime(data.paid_at)}
            </span>
            <ButtonLink
              href={`/pesanan/${data.order_number}/invoice`}
              variant="secondary"
            >
              Lihat invoice
            </ButtonLink>
          </>
        ) : null}
      </div>

      {canPay && autoSync.dataUpdatedAt > 0 ? (
        <p className="mt-2 flex items-center gap-1.5 text-xs text-ink-subtle" aria-live="polite">
          <Icon as={RefreshCw} size="xs" className={autoSync.isFetching ? "animate-spin" : ""} />
          {autoSync.isFetching ? "Memeriksa status pembayaran..." : "Status pembayaran diperiksa otomatis"}
        </p>
      ) : null}

      <div className="mt-6 grid gap-6 lg:grid-cols-12">
        <div className="space-y-4 lg:col-span-8">
          {(data.seller_orders ?? []).map((sellerOrder) => (
            <Card key={sellerOrder.id}>
              <CardHeader
                title={sellerOrder.seller?.store_name ?? sellerOrder.seller_order_number}
                actions={<StatusBadge domain="sellerOrder" value={sellerOrder.status} />}
              />
              <CardBody className="space-y-3">
                <ul className="space-y-2 text-sm">
                  {(sellerOrder.items ?? []).map((item) => {
                    const reviewed = isReviewed(item);

                    return (
                      <li key={item.id} className="flex flex-wrap items-center justify-between gap-2">
                        <span className="min-w-0 text-ink-muted">
                          {item.quantity}× {item.product_name}
                          {item.variant_name ? ` · ${item.variant_name}` : ""}
                        </span>
                        <div className="flex items-center gap-3">
                          <Money value={item.line_total} className="shrink-0 text-ink" />
                          {data.status === "completed" ? (
                            reviewed ? (
                              <span className="inline-flex items-center gap-1 text-xs text-ink-subtle">
                                <Icon as={Star} size="xs" fill />
                                Sudah diulas
                              </span>
                            ) : (
                              <Button size="xs" variant="secondary" onClick={() => setReviewItem(item)}>
                                <Icon as={Star} size="xs" />
                                Beri ulasan
                              </Button>
                            )
                          ) : null}
                        </div>
                      </li>
                    );
                  })}
                </ul>

                <div className="flex justify-between gap-4 border-t border-line pt-3 text-sm">
                  <span className="text-ink-muted">Ongkir</span>
                  <Money value={sellerOrder.shipping_cost} />
                </div>
                <div className="flex justify-between gap-4 text-sm font-semibold">
                  <span>Total penjual</span>
                  <Money value={sellerOrder.grand_total} />
                </div>

                {sellerOrder.shipment ? (
                  <div className="flex flex-wrap items-center justify-between gap-2 rounded-[8px] border border-line bg-paper px-3 py-2.5 text-sm">
                    <span className="inline-flex items-center gap-2 text-ink-muted">
                      <Icon as={Truck} size="xs" />
                      {sellerOrder.shipment.tracking_number ?? "Belum ada resi"}
                    </span>
                    <div className="flex items-center gap-2">
                      <StatusBadge domain="shipment" value={sellerOrder.shipment.status} />
                      <Link
                        href={`/pesanan/${data.order_number}/lacak`}
                        className="text-xs font-medium text-brand hover:underline"
                      >
                        Lacak
                      </Link>
                    </div>
                  </div>
                ) : null}

                {sellerOrder.status === "delivered" ? (
                  <Button
                    size="sm"
                    variant="secondary"
                    loading={confirmReceived.isPending}
                    onClick={() =>
                      confirmReceived.mutate(sellerOrder.id, {
                        onSuccess: () => toast.success("Pesanan diterima"),
                        onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                      })
                    }
                  >
                    Pesanan diterima
                  </Button>
                ) : null}

                {refundFor(sellerOrder.id) ? (
                  <div className="flex flex-wrap items-center justify-between gap-2 rounded-[8px] border border-line bg-paper px-3 py-2 text-sm">
                    <span className="text-ink-muted">
                      Refund {refundFor(sellerOrder.id)?.refund_number}
                    </span>
                    <StatusBadge domain="refund" value={refundFor(sellerOrder.id)?.status} />
                  </div>
                ) : data.paid_at && !["refunded", "cancelled"].includes(sellerOrder.status) ? (
                  <div className="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3 text-sm">
                    <span className="text-ink-muted">Ada masalah dengan pesanan ini?</span>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => {
                        setRefundTarget({
                          id: sellerOrder.id,
                          label: sellerOrder.seller_order_number,
                        });
                        setRefundReason("");
                      }}
                    >
                      Ajukan refund
                    </Button>
                  </div>
                ) : null}
              </CardBody>
            </Card>
          ))}

          {address ? (
            <Card>
              <CardHeader title="Alamat pengiriman" />
              <CardBody className="text-sm">
                <p className="flex items-center gap-2 font-medium text-ink">
                  <Icon as={MapPin} size="xs" className="text-ink-subtle" />
                  {address.recipient_name}
                </p>
                <p className="mt-1 text-ink-muted">{address.phone}</p>
                <p className="mt-1 leading-6 text-ink-muted">
                  {address.address_line}, {address.city}, {address.province}{" "}
                  {address.postal_code}
                </p>
              </CardBody>
            </Card>
          ) : null}
        </div>

        <aside className="space-y-4 lg:col-span-4">
          <Card>
            <CardHeader title="Ringkasan" />
            <CardBody className="space-y-2 text-sm">
              <div className="flex justify-between gap-4">
                <span className="text-ink-muted">Subtotal</span>
                <Money value={data.subtotal} />
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-ink-muted">Ongkir</span>
                <Money value={data.shipping_total} />
              </div>
              <div className="flex justify-between gap-4 border-t border-line pt-2 text-base font-semibold">
                <span>Total</span>
                <Money value={data.grand_total} />
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardHeader title="Riwayat status" />
            <CardBody>
              <ol className="space-y-3">
                {(data.status_histories ?? []).slice().reverse().map((history, index) => (
                  <li key={index} className="text-sm">
                    <p className="flex items-center gap-2">
                      <StatusBadge domain="order" value={history.to_status} />
                    </p>
                    <p className="mt-1 text-xs text-ink-subtle">
                      {formatDateTime(history.created_at)}
                      {history.reason ? ` · ${history.reason}` : ""}
                    </p>
                  </li>
                ))}
              </ol>
            </CardBody>
          </Card>
        </aside>
      </div>

      <Modal
        open={cancelOpen}
        onOpenChange={setCancelOpen}
        title="Batalkan pesanan?"
        description="Tindakan ini tidak bisa dibatalkan. Stok yang direservasi akan dilepas."
        footer={
          <>
            <Button variant="ghost" onClick={() => setCancelOpen(false)}>Tutup</Button>
            <Button
              variant="danger"
              loading={cancel.isPending}
              onClick={() =>
                cancel.mutate(reason, {
                  onSuccess: () => {
                    setCancelOpen(false);
                    toast.success("Pesanan dibatalkan");
                  },
                  onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                })
              }
            >
              Batalkan pesanan
            </Button>
          </>
        }
      >
        <label className="text-[13px] font-medium text-ink" htmlFor="cancel-reason">
          Alasan
        </label>
        <div className="mt-1.5">
          <Textarea
            id="cancel-reason"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            placeholder="Contoh: salah pilih produk"
            required
          />
        </div>
      </Modal>

      <Modal
        open={refundTarget !== null}
        onOpenChange={(open) => {
          if (!open) setRefundTarget(null);
        }}
        title="Ajukan refund"
        description={
          refundTarget
            ? `Sub-pesanan ${refundTarget.label}. Permintaan ditinjau admin.`
            : undefined
        }
        footer={
          <>
            <Button variant="ghost" onClick={() => setRefundTarget(null)}>
              Tutup
            </Button>
            <Button
              variant="danger"
              loading={requestRefund.isPending}
              disabled={refundReason.trim().length < 5}
              onClick={() => {
                if (!refundTarget) return;

                requestRefund.mutate(
                  { seller_order_id: refundTarget.id, reason: refundReason.trim() },
                  {
                    onSuccess: () => {
                      setRefundTarget(null);
                      toast.success("Permintaan refund dikirim");
                    },
                    onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                  },
                );
              }}
            >
              Kirim permintaan
            </Button>
          </>
        }
      >
        <label className="text-[13px] font-medium text-ink" htmlFor="refund-reason">
          Alasan
        </label>
        <div className="mt-1.5">
          <Textarea
            id="refund-reason"
            value={refundReason}
            onChange={(event) => setRefundReason(event.target.value)}
            placeholder="Contoh: barang rusak saat diterima"
            required
          />
        </div>
        <p className="mt-2 text-xs text-ink-subtle">
          Minimal 5 karakter. Dana dikembalikan sesuai metode pembayaran setelah disetujui.
        </p>
      </Modal>

      <ReviewDialog
        orderNumber={data.order_number}
        item={reviewItem}
        open={reviewItem !== null}
        onClose={() => setReviewItem(null)}
        onDone={(itemId) => setReviewedIds((current) => [...current, itemId])}
      />

      {!token ? (
        <p className="mt-6 text-sm text-ink-muted">
          <Link href="/login" className="text-brand underline">Masuk</Link> untuk mengelola pesanan.
        </p>
      ) : null}
    </div>
  );
}
