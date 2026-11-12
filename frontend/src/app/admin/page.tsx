"use client";

import Link from "next/link";
import { Banknote, ReceiptText, RotateCcw, Store } from "lucide-react";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Icon } from "@/components/ui/Icon";
import { Skeleton } from "@/components/ui/Skeleton";
import { PageHeader } from "@/components/ui/PageHeader";
import {
  useAdminCommissions,
  useAdminPayouts,
  useAdminRefunds,
  useAdminSellers,
} from "@/features/admin";

export default function AdminDashboardPage() {
  const sellers = useAdminSellers({ status: "under_review", per_page: 1 });
  const payouts = useAdminPayouts({ status: "requested", per_page: 1 });
  const refunds = useAdminRefunds({ status: "requested", per_page: 1 });
  const commissions = useAdminCommissions({ status: "pending", per_page: 1 });

  const queues = [
    {
      label: "Penjual menunggu review",
      value: sellers.data?.pagination.total,
      href: "/admin/seller?status=under_review",
      icon: Store,
      loading: sellers.isPending,
    },
    {
      label: "Payout menunggu persetujuan",
      value: payouts.data?.pagination.total,
      href: "/admin/payout?status=requested",
      icon: Banknote,
      loading: payouts.isPending,
    },
    {
      label: "Refund menunggu",
      value: refunds.data?.pagination.total,
      href: "/admin/refund?status=requested",
      icon: RotateCcw,
      loading: refunds.isPending,
    },
    {
      label: "Komisi menunggu settlement",
      value: commissions.data?.pagination.total,
      href: "/admin/komisi?status=pending",
      icon: ReceiptText,
      loading: commissions.isPending,
    },
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Ringkasan admin"
        description="Antrian operasional yang membutuhkan tindakan."
        eyebrow="Admin"
      />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {queues.map((queue) => (
          <Link key={queue.label} href={queue.href} className="block">
            <Card className="h-full hover:border-brand/40">
              <CardBody>
                <div className="flex items-center gap-2 text-xs text-ink-subtle">
                  <Icon as={queue.icon} size="xs" />
                  {queue.label}
                </div>
                {queue.loading ? (
                  <Skeleton className="mt-3 h-7 w-12" />
                ) : (
                  <p className="num mt-2 text-2xl font-semibold text-ink">{queue.value ?? 0}</p>
                )}
              </CardBody>
            </Card>
          </Link>
        ))}
      </div>

      <Card>
        <CardHeader title="Pintasan" description="Akses cepat ke modul yang tersedia." />
        <CardBody className="grid gap-2 sm:grid-cols-2">
          <Link href="/admin/seller" className="rounded-[6px] border border-line px-3 py-2 text-sm text-ink hover:border-brand/40">
            Persetujuan penjual
          </Link>
          <Link href="/admin/produk" className="rounded-[6px] border border-line px-3 py-2 text-sm text-ink hover:border-brand/40">
            Moderasi produk
          </Link>
          <Link href="/admin/refund" className="rounded-[6px] border border-line px-3 py-2 text-sm text-ink hover:border-brand/40">
            Refund
          </Link>
          <Link href="/admin/payout" className="rounded-[6px] border border-line px-3 py-2 text-sm text-ink hover:border-brand/40">
            Payout
          </Link>
        </CardBody>
      </Card>
    </div>
  );
}
