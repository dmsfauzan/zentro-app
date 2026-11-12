"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { Suspense } from "react";
import { Truck } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Icon } from "@/components/ui/Icon";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { useOrder } from "@/features/orders";
import { useDevTrack, useTracking } from "@/features/shipping";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { formatDateTime } from "@/lib/format";
import type { Shipment } from "@/lib/api/domain";

export default function TrackingPage() {
  const { orderNumber } = useParams<{ orderNumber: string }>();
  const order = useOrder(orderNumber);

  if (order.isPending) {
    return <div className="container-page py-8"><Skeleton className="h-72 w-full" /></div>;
  }

  if (order.isError || !order.data) {
    return (
      <div className="container-page py-14">
        <Alert tone="danger" title="Pesanan tidak ditemukan">{errorMessage(order.error)}</Alert>
      </div>
    );
  }

  const shipments = (order.data.seller_orders ?? [])
    .map((sellerOrder) => ({
      store: sellerOrder.seller?.store_name ?? sellerOrder.seller_order_number,
      shipment: sellerOrder.shipment ?? null,
    }))
    .filter((entry) => entry.shipment !== null) as {
      store: string;
      shipment: Shipment;
    }[];

  return (
    <div className="container-page py-8">
      <nav className="mb-4 flex items-center gap-1 text-[13px] text-ink-subtle">
        <Link href="/pesanan" className="hover:text-brand">Pesanan</Link>
        <span aria-hidden="true">/</span>
        <Link href={`/pesanan/${orderNumber}`} className="num hover:text-brand">{orderNumber}</Link>
        <span aria-hidden="true">/</span>
        <span className="text-ink">Pelacakan</span>
      </nav>

      <h1 className="text-2xl font-semibold tracking-[-0.01em] text-ink">Pelacakan pengiriman</h1>

      {shipments.length === 0 ? (
        <div className="mt-5">
          <EmptyState
            icon={Truck}
            title="Belum ada pengiriman"
            description="Resi akan muncul setelah penjual mengirim pesanan."
          />
        </div>
      ) : (
        <div className="mt-5 space-y-4">
          {shipments.map((entry) => (
            <Suspense key={entry.shipment.id} fallback={<Skeleton className="h-40 w-full" />}>
              <TrackingCard store={entry.store} shipment={entry.shipment} />
            </Suspense>
          ))}
        </div>
      )}
    </div>
  );
}

function TrackingCard({ store, shipment }: { store: string; shipment: Shipment }) {
  const { isDev } = useAuth();
  const toast = useToast();
  const tracking = useTracking(shipment.id);
  const simulate = useDevTrack(shipment.id);
  const data = tracking.data ?? shipment;

  return (
    <Card>
      <CardHeader
        title={store}
        description={
          data.tracking_number ? `Resi ${data.tracking_number}` : "Resi belum tersedia"
        }
        actions={<StatusBadge domain="shipment" value={data.status} />}
      />
      <CardBody>
        {(data.timeline ?? []).length === 0 ? (
          <p className="text-sm text-ink-muted">Belum ada riwayat perjalanan.</p>
        ) : (
          <ol className="space-y-3">
            {(data.timeline ?? []).map((event, index) => (
              <li key={index} className="flex gap-3">
                <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-ink-subtle" aria-hidden="true" />
                <div className="min-w-0 text-sm">
                  <p className="text-ink">
                    {event.status}
                    {event.location ? ` · ${event.location}` : ""}
                  </p>
                  <p className="text-xs text-ink-subtle">
                    {formatDateTime(event.occurred_at)}
                    {event.description ? ` · ${event.description}` : ""}
                  </p>
                </div>
              </li>
            ))}
          </ol>
        )}

        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-3">
          <Button size="sm" variant="secondary" loading={tracking.isFetching} onClick={() => void tracking.refetch()}>
            Perbarui
          </Button>
          {isDev ? (
            <Button
              size="sm"
              variant="ghost"
              loading={simulate.isPending}
              onClick={() =>
                simulate.mutate("delivered", {
                  onSuccess: () => toast.success("Status pengiriman disimulasikan"),
                  onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                })
              }
            >
              Simulasikan diterima
            </Button>
          ) : null}
          <span className="ml-auto inline-flex items-center gap-1 text-xs text-ink-subtle">
            <Icon as={Truck} size="xs" />
            {data.provider}
          </span>
        </div>
      </CardBody>
    </Card>
  );
}
