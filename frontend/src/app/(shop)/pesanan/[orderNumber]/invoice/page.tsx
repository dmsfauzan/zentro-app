"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { ArrowLeft, Printer } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Logo } from "@/components/brand/Logo";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { useInvoice } from "@/features/orders";
import { errorMessage } from "@/lib/api/client";
import { formatDateTime } from "@/lib/format";
import { cn } from "@/lib/cn";

function SummaryRow({
  label,
  value,
  tone = "muted",
  bold = false,
}: {
  label: string;
  value: number;
  tone?: "muted" | "success";
  bold?: boolean;
}) {
  return (
    <div
      className={cn(
        "flex justify-between gap-4",
        bold ? "font-semibold text-ink" : "text-ink-muted",
      )}
    >
      <span>{label}</span>
      <span className={cn(tone === "success" && "text-success")}>
        {tone === "success" ? "-" : ""}
        <Money value={value} />
      </span>
    </div>
  );
}

export default function InvoicePage() {
  const { orderNumber } = useParams<{ orderNumber: string }>();
  const invoice = useInvoice(orderNumber);

  if (invoice.isPending) {
    return (
      <div className="container-page py-8">
        <Skeleton className="mx-auto h-[560px] max-w-3xl" />
      </div>
    );
  }

  if (invoice.isError || !invoice.data) {
    return (
      <div className="container-page py-14">
        <Alert tone="danger" title="Invoice belum tersedia">
          {errorMessage(invoice.error)}
        </Alert>
        <div className="mt-4">
          <ButtonLink href={`/pesanan/${orderNumber}`} variant="secondary">
            Kembali ke pesanan
          </ButtonLink>
        </div>
      </div>
    );
  }

  const data = invoice.data;
  const address = data.shipping_address;

  return (
    <div className="container-page py-8">
      <div className="print-hide mx-auto mb-4 flex max-w-3xl flex-wrap items-center justify-between gap-2">
        <Link
          href={`/pesanan/${data.order_number}`}
          className="inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-ink"
        >
          <Icon as={ArrowLeft} size="xs" />
          Kembali ke pesanan
        </Link>
        <Button size="sm" onClick={() => window.print()}>
          <Icon as={Printer} size="xs" />
          Cetak / Simpan PDF
        </Button>
      </div>

      <article className="mx-auto max-w-3xl rounded-[8px] border border-line bg-canvas p-6 sm:p-8 print-plain">
        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-line pb-5">
          <div>
            <Logo heightClass="h-7" />
            <h1 className="mt-2 font-display text-2xl tracking-[-0.01em] text-ink">Invoice</h1>
            <p className="num mt-1 text-sm text-ink-muted">{data.invoice_number}</p>
          </div>
          <div className="text-right text-sm">
            <p className="text-ink-subtle">Terbit</p>
            <p className="text-ink">{formatDateTime(data.issued_at)}</p>
            <div className="mt-2 flex justify-end">
              <StatusBadge domain="order" value={data.status} />
            </div>
          </div>
        </div>

        <section className="grid gap-5 border-b border-line py-5 sm:grid-cols-2">
          <div>
            <p className="text-xs font-medium uppercase tracking-[0.12em] text-ink-subtle">
              Ditagihkan kepada
            </p>
            <p className="mt-1.5 text-sm font-medium text-ink">{data.buyer.name ?? "-"}</p>
            <p className="text-sm text-ink-muted">{data.buyer.email ?? "-"}</p>
          </div>
          <div>
            <p className="text-xs font-medium uppercase tracking-[0.12em] text-ink-subtle">
              Dikirim ke
            </p>
            {address ? (
              <>
                <p className="mt-1.5 text-sm font-medium text-ink">{address.recipient_name}</p>
                <p className="text-sm text-ink-muted">{address.phone}</p>
                <p className="text-sm leading-6 text-ink-muted">
                  {address.address_line}, {address.city}, {address.province} {address.postal_code}
                </p>
              </>
            ) : (
              <p className="mt-1.5 text-sm text-ink-muted">-</p>
            )}
          </div>
        </section>

        <section className="space-y-6 py-5">
          {data.groups.map((group) => (
            <div key={group.seller_order_number}>
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-1.5">
                <p className="text-sm font-medium text-ink">
                  {group.seller.store_name ?? "Penjual"}
                </p>
                <p className="num text-xs text-ink-subtle">{group.seller_order_number}</p>
              </div>

              <table className="mt-2 w-full border-collapse text-sm">
                <thead>
                  <tr className="text-left text-xs text-ink-subtle">
                    <th className="py-1 font-medium">Produk</th>
                    <th className="py-1 text-right font-medium">Qty</th>
                    <th className="py-1 text-right font-medium">Harga</th>
                    <th className="py-1 text-right font-medium">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {group.items.map((item, index) => (
                    <tr
                      key={`${group.seller_order_number}-${index}`}
                      className="border-t border-line/70 align-top"
                    >
                      <td className="py-2 pr-2">
                        <span className="text-ink">{item.product_name}</span>
                        {item.variant_name ? (
                          <span className="block text-xs text-ink-subtle">{item.variant_name}</span>
                        ) : null}
                        {item.sku ? (
                          <span className="block text-xs text-ink-subtle">SKU {item.sku}</span>
                        ) : null}
                      </td>
                      <td className="num py-2 text-right">{item.quantity}</td>
                      <td className="num py-2 text-right">
                        <Money value={item.unit_price} />
                      </td>
                      <td className="num py-2 text-right">
                        <Money value={item.line_total} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              <div className="mt-2 space-y-1 text-sm">
                <SummaryRow label="Subtotal" value={group.subtotal} />
                {group.discount_total > 0 ? (
                  <SummaryRow label="Diskon" value={group.discount_total} tone="success" />
                ) : null}
                <SummaryRow label="Ongkir" value={group.shipping_cost} />
                <SummaryRow label="Total penjual" value={group.grand_total} bold />
              </div>
            </div>
          ))}
        </section>

        <section className="ml-auto max-w-xs space-y-1 border-t border-line pt-4 text-sm">
          <SummaryRow label="Subtotal" value={data.summary.subtotal} />
          {data.summary.discount_total > 0 ? (
            <SummaryRow label="Diskon" value={data.summary.discount_total} tone="success" />
          ) : null}
          <SummaryRow label="Ongkir" value={data.summary.shipping_total} />
          <SummaryRow label="Total" value={data.summary.grand_total} bold />
        </section>

        <section className="mt-6 border-t border-line pt-4 text-sm">
          <p className="font-medium text-ink">Pembayaran</p>
          {data.payment ? (
            <p className="mt-1 text-ink-muted">
              {data.payment.provider}
              {data.payment.method ? ` · ${data.payment.method}` : ""} · {data.payment.status}
              {data.payment.paid_at ? ` · ${formatDateTime(data.payment.paid_at)}` : ""}
            </p>
          ) : (
            <p className="mt-1 text-ink-muted">-</p>
          )}
          <p className="mt-3 text-xs text-ink-subtle">
            Invoice ini dihasilkan otomatis oleh ZENTRO. Simpan sebagai bukti pembayaran.
          </p>
        </section>
      </article>
    </div>
  );
}
