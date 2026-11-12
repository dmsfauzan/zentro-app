"use client";

import Link from "next/link";
import { ArrowRight, Banknote, Package, PackageSearch, ReceiptText } from "lucide-react";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { EmptyState } from "@/components/ui/EmptyState";
import { useSellerDashboard, useSellerOrders } from "@/features/seller";
import { formatDateTime, formatNumber } from "@/lib/format";

export default function SellerDashboardPage() {
  const dashboard = useSellerDashboard();
  const pending = useSellerOrders({ status: "pending", per_page: 5 });

  if (dashboard.isPending) {
    return <div className="space-y-4"><Skeleton className="h-28 w-full" /><Skeleton className="h-56 w-full" /></div>;
  }

  if (!dashboard.data) {
    return <p className="text-sm text-danger">Gagal memuat ringkasan toko.</p>;
  }

  const stats = dashboard.data.stats;

  return (
    <div className="space-y-6">
      <PageHeader
        title={dashboard.data.store.store_name}
        description="Ringkasan operasional toko hari ini."
        eyebrow="Penjual"
        actions={<StatusBadge domain="seller" value={dashboard.data.store.status} />}
      />

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat label="Produk" value={formatNumber(stats.products)} href="/seller/produk" icon={Package} />
        <Stat label="Perlu diproses" value={formatNumber(stats.pending_orders)} href="/seller/pesanan" icon={ReceiptText} />
        <Stat label="Omzet bulan ini" value={`Rp ${formatNumber(stats.revenue_this_month)}`} icon={Banknote} />
        <Stat label="Stok tipis" value={formatNumber(stats.low_stock_variants)} href="/seller/inventori" icon={PackageSearch} />
      </div>

      <Card>
        <CardHeader
          title="Perlu ditangani"
          description="Pesanan yang menunggu konfirmasi toko."
          actions={
            <Link href="/seller/pesanan" className="inline-flex items-center gap-1 text-sm text-brand hover:underline">
              Semua pesanan
              <Icon as={ArrowRight} size="xs" />
            </Link>
          }
        />
        {pending.isPending ? (
          <div className="p-4"><Skeleton className="h-20 w-full" /></div>
        ) : (pending.data?.items.length ?? 0) === 0 ? (
          <div className="p-4">
            <EmptyState
              title="Tidak ada pesanan baru"
              description="Pesanan yang masuk akan tampil di sini untuk dikonfirmasi."
            />
          </div>
        ) : (
          <div className="divide-y divide-line">
            {pending.data?.items.map((order) => (
              <Link
                key={order.id}
                href={`/seller/pesanan/${order.id}`}
                className="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-paper/60"
              >
                <div className="min-w-0 flex-1">
                  <p className="num text-sm font-medium text-ink">{order.seller_order_number}</p>
                  <p className="text-xs text-ink-subtle">{formatDateTime(order.created_at ?? null)}</p>
                </div>
                <StatusBadge domain="sellerOrder" value={order.status} />
                <Money value={order.grand_total} className="text-sm font-semibold" />
              </Link>
            ))}
          </div>
        )}
      </Card>

      <Card>
        <CardHeader title="Dompet" description="Saldo dana penjualan." actions={
          <Link href="/seller/dompet" className="text-sm text-brand hover:underline">Lihat mutasi</Link>
        } />
        <CardBody className="grid grid-cols-3 gap-4">
          <div>
            <p className="text-xs text-ink-subtle">Menunggu settlement</p>
            <Money value={stats.wallet.pending} className="mt-1 block text-lg font-semibold" />
          </div>
          <div>
            <p className="text-xs text-ink-subtle">Tersedia</p>
            <Money value={stats.wallet.available} className="mt-1 block text-lg font-semibold" />
          </div>
          <div>
            <p className="text-xs text-ink-subtle">Sudah dibayar</p>
            <Money value={stats.wallet.paid} className="mt-1 block text-lg font-semibold" />
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

function Stat({
  label,
  value,
  href,
  icon,
}: {
  label: string;
  value: string;
  href?: string;
  icon: typeof Package;
}) {
  const content = (
    <div className="rounded-[8px] border border-line bg-canvas p-4">
      <div className="flex items-center gap-2 text-xs text-ink-subtle">
        <Icon as={icon} size="xs" />
        {label}
      </div>
      <p className="num mt-2 text-xl font-semibold text-ink">{value}</p>
    </div>
  );

  return href ? <Link href={href} className="block hover:border-brand/40">{content}</Link> : content;
}
