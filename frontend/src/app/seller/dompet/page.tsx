"use client";

import Link from "next/link";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Select } from "@/components/ui/Input";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { PageHeader } from "@/components/ui/PageHeader";
import { Money } from "@/components/ui/Money";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useWallet, useWalletTransactions } from "@/features/wallet";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";

const typeLabels: Record<string, string> = {
  sale_pending: "Dana penjualan",
  settlement_release: "Settlement",
  commission: "Komisi",
  payout: "Pencairan",
  payout_hold: "Reservasi payout",
  payout_release: "Pelepasan payout",
  risk_hold: "Reserve risiko",
  risk_hold_release: "Pelepasan reserve",
  refund_debit: "Refund",
  debt_offset: "Pelunasan utang",
  adjustment: "Penyesuaian",
  reversal: "Pembalik",
};

export default function WalletPage() {
  const wallet = useWallet();
  const [filters, setFilters] = useUrlFilters({ bucket: "", type: "", page: "1" });
  const transactions = useWalletTransactions({
    bucket: filters.bucket || undefined,
    type: filters.type || undefined,
    page: filters.page,
    per_page: 20,
  });

  const items = transactions.data?.items ?? [];
  const pagination = transactions.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader
        title="Dompet"
        description="Dana penjualan mengikuti alur menunggu lalu tersedia lalu dibayar."
        eyebrow="Keuangan"
        actions={<Link href="/seller/payout" className="text-sm font-medium text-brand hover:underline">Ajukan payout</Link>}
      />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <Balance label="Menunggu settlement" value={wallet.data?.pending_balance ?? 0} loading={wallet.isPending} />
        <Balance label="Tersedia" value={wallet.data?.available_balance ?? 0} loading={wallet.isPending} highlight />
        <Balance label="Ditahan untuk payout" value={wallet.data?.payout_reserved_balance ?? 0} loading={wallet.isPending} />
        <Balance label="Ditahan risiko" value={wallet.data?.risk_hold_balance ?? 0} loading={wallet.isPending} />
        <Balance label="Utang" value={wallet.data?.debt_balance ?? 0} loading={wallet.isPending} />
        <Balance label="Sudah dibayar" value={wallet.data?.paid_balance ?? 0} loading={wallet.isPending} />
      </div>

      <Card>
        <CardHeader title="Mutasi" description="Ledger tidak dapat diubah. Koreksi berupa entri pembalik." />
        <div className="flex flex-wrap gap-2 border-b border-line px-4 py-3">
          <Select
            aria-label="Bucket"
            className="h-9 w-40"
            value={filters.bucket}
            onChange={(event) => setFilters({ bucket: event.target.value, page: "1" })}
          >
            <option value="">Semua bucket</option>
            <option value="pending">Pending</option>
            <option value="available">Available</option>
            <option value="payout_reserved">Payout reserved</option>
            <option value="risk_hold">Risk hold</option>
            <option value="debt">Utang</option>
            <option value="paid">Paid</option>
          </Select>
          <Select
            aria-label="Tipe"
            className="h-9 w-44"
            value={filters.type}
            onChange={(event) => setFilters({ type: event.target.value, page: "1" })}
          >
            <option value="">Semua tipe</option>
            {Object.entries(typeLabels).map(([value, label]) => (
              <option key={value} value={value}>{label}</option>
            ))}
          </Select>
        </div>

        {transactions.isPending ? (
          <div className="p-4"><Skeleton className="h-40 w-full" /></div>
        ) : items.length === 0 ? (
          <div className="p-4">
            <EmptyState title="Belum ada mutasi" description="Mutasi muncul setelah ada penjualan atau payout." />
          </div>
        ) : (
          <TableWrap>
            <THead>
              <TR>
                <TH>Tanggal</TH>
                <TH>Jenis</TH>
                <TH>Bucket</TH>
                <TH numeric>Jumlah</TH>
                <TH numeric>Saldo akhir</TH>
              </TR>
            </THead>
            <TBody>
              {items.map((transaction) => (
                <TR key={transaction.id}>
                  <TD className="text-ink-muted">{formatDateTime(transaction.created_at)}</TD>
                  <TD>
                    {typeLabels[transaction.type] ?? transaction.type}
                    {transaction.description ? (
                      <span className="block text-xs text-ink-subtle">{transaction.description}</span>
                    ) : null}
                  </TD>
                  <TD className="text-ink-muted">{transaction.balance_bucket}</TD>
                  <TD numeric className={transaction.amount < 0 ? "text-danger" : "text-success"}>
                    {transaction.amount > 0 ? "+" : ""}{new Intl.NumberFormat("id-ID").format(transaction.amount)}
                  </TD>
                  <TD numeric><Money value={transaction.balance_after} /></TD>
                </TR>
              ))}
            </TBody>
          </TableWrap>
        )}

        {pagination ? (
          <div className="px-4">
            <Pagination
              page={pagination.page}
              lastPage={pagination.last_page}
              onChange={(page) => setFilters({ page: String(page) })}
            />
          </div>
        ) : null}
      </Card>
    </div>
  );
}

function Balance({ label, value, loading, highlight }: { label: string; value: number; loading?: boolean; highlight?: boolean }) {
  return (
    <Card className={highlight ? "border-brand/40" : undefined}>
      <CardBody>
        <p className="text-xs text-ink-subtle">{label}</p>
        {loading ? <Skeleton className="mt-2 h-7 w-28" /> : <Money value={value} className="mt-1 block text-xl font-semibold text-ink" />}
      </CardBody>
    </Card>
  );
}
