"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useState } from "react";
import { MapPin, Truck } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { DispatchModal } from "@/components/seller/DispatchModal";
import {
  useConfirmSellerOrder,
  useDispatchSellerOrder,
  useSellerOrder,
} from "@/features/seller";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { formatDateTime } from "@/lib/format";

export default function SellerOrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const orderId = Number(id);
  const order = useSellerOrder(orderId);
  const confirm = useConfirmSellerOrder(orderId);
  const dispatch = useDispatchSellerOrder(orderId);
  const toast = useToast();
  const [dispatchOpen, setDispatchOpen] = useState(false);

  if (order.isPending) {
    return <div className="space-y-4"><Skeleton className="h-24 w-full" /><Skeleton className="h-64 w-full" /></div>;
  }

  if (!order.data) {
    return <Alert tone="danger" title="Pesanan tidak ditemukan">Periksa daftar pesanan.</Alert>;
  }

  const data = order.data;
  const address = data.shipping_address;
  const canConfirm = data.status === "pending";
  const canDispatch = data.status === "confirmed" || data.status === "processing";

  return (
    <div className="space-y-5">
      <PageHeader
        title={data.seller_order_number}
        description={`Dibuat ${formatDateTime(data.created_at ?? null)}`}
        eyebrow="Pesanan"
        actions={<StatusBadge domain="sellerOrder" value={data.status} />}
      />

      <div className="flex flex-wrap gap-2">
        {canConfirm ? (
          <Button
            loading={confirm.isPending}
            onClick={() =>
              confirm.mutate(undefined, {
                onSuccess: () => toast.success("Pesanan dikonfirmasi"),
                onError: (caught) => toast.error("Gagal", errorMessage(caught)),
              })
            }
          >
            Konfirmasi pesanan
          </Button>
        ) : null}
        {canDispatch ? (
          <Button variant="secondary" onClick={() => setDispatchOpen(true)}>
            <Icon as={Truck} size="xs" />
            Kirim pesanan
          </Button>
        ) : null}
        <Link href="/seller/pesanan" className="inline-flex h-11 items-center text-sm text-ink-muted hover:text-ink">
          Kembali
        </Link>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader title="Item pesanan" />
          <CardBody>
            <ul className="space-y-2 text-sm">
              {(data.items ?? []).map((item) => (
                <li key={item.id} className="flex justify-between gap-4">
                  <span className="min-w-0 text-ink-muted">
                    {item.quantity}× {item.product_name}
                    {item.variant_name ? ` · ${item.variant_name}` : ""}
                    <span className="num block text-xs text-ink-subtle">{item.sku}</span>
                  </span>
                  <Money value={item.line_total} className="shrink-0 text-ink" />
                </li>
              ))}
            </ul>

            <dl className="mt-4 space-y-2 border-t border-line pt-3 text-sm">
              <div className="flex justify-between gap-4">
                <dt className="text-ink-muted">Subtotal</dt>
                <dd><Money value={data.subtotal} /></dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-ink-muted">Ongkir</dt>
                <dd><Money value={data.shipping_cost} /></dd>
              </div>
              <div className="flex justify-between gap-4 text-base font-semibold">
                <dt>Total</dt>
                <dd><Money value={data.grand_total} /></dd>
              </div>
            </dl>
          </CardBody>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardHeader title="Alamat pengiriman" />
            <CardBody className="text-sm">
              {address ? (
                <>
                  <p className="flex items-center gap-2 font-medium text-ink">
                    <Icon as={MapPin} size="xs" className="text-ink-subtle" />
                    {address.recipient_name}
                  </p>
                  <p className="mt-1 text-ink-muted">{address.phone}</p>
                  <p className="mt-1 leading-6 text-ink-muted">
                    {address.address_line}, {address.city}, {address.province}{" "}
                    {address.postal_code}
                  </p>
                </>
              ) : (
                <p className="text-ink-muted">Alamat tidak tersedia.</p>
              )}
            </CardBody>
          </Card>

          {data.shipment ? (
            <Card>
              <CardHeader title="Pengiriman" actions={<StatusBadge domain="shipment" value={data.shipment.status} />} />
              <CardBody className="space-y-1 text-sm">
                <p className="num text-ink">{data.shipment.tracking_number ?? "Belum ada resi"}</p>
                <p className="text-xs text-ink-subtle">
                  {data.shipment.provider} · {data.shipment.service_code ?? "-"}
                </p>
                {data.shipment.dispatched_at ? (
                  <p className="text-xs text-ink-subtle">Dikirim {formatDateTime(data.shipment.dispatched_at)}</p>
                ) : null}
              </CardBody>
            </Card>
          ) : null}
        </div>
      </div>

      <DispatchModal
        open={dispatchOpen}
        onClose={() => setDispatchOpen(false)}
        loading={dispatch.isPending}
        onSubmit={(payload) =>
          dispatch.mutate(payload, {
            onSuccess: () => { setDispatchOpen(false); toast.success("Pesanan dikirim"); },
            onError: (caught) => toast.error("Gagal", errorMessage(caught)),
          })
        }
      />
    </div>
  );
}
