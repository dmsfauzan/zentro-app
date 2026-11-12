"use client";

import { useState } from "react";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Money } from "@/components/ui/Money";
import { cn } from "@/lib/cn";
import type { CheckoutSellerGroup } from "@/lib/api/domain";

type Props = {
  group: CheckoutSellerGroup;
  images: Record<number, string | null>;
  selectedServiceCode: string | null;
  onSelectShipping: (sellerId: number, code: string) => void;
  onUseVoucher: () => void;
  voucherActive: boolean;
};

/**
 * Kartu per toko: rincian produk + ongkir + diskon (gaya referensi checkout).
 */
export function StoreGroupCard({
  group,
  images,
  selectedServiceCode,
  onSelectShipping,
  onUseVoucher,
  voucherActive,
}: Props) {
  const [shipOpen, setShipOpen] = useState(false);
  const selected =
    group.shipping_options.find((option) => option.code === selectedServiceCode) ?? null;
  const showShippingList = shipOpen || selected === null;

  return (
    <Card>
      <CardHeader
        title={
          <span className="flex items-center gap-2">
            <span aria-hidden="true" className="size-2 rounded-full bg-brand" />
            {group.seller.store_name}
          </span>
        }
        description="Produk dan pengiriman dari toko ini."
      />
      <CardBody className="space-y-4">
        <div>
          <div className="hidden border-b border-line pb-2 text-[11px] font-medium uppercase tracking-[0.1em] text-ink-subtle sm:flex">
            <span className="min-w-0 flex-1">Produk</span>
            <span className="w-28 text-right">Harga</span>
            <span className="w-14 text-right">Qty</span>
            <span className="w-28 text-right">Total</span>
          </div>

          <ul className="divide-y divide-line">
            {group.items.map((item) => {
              const image = images[item.variant_id] ?? null;

              return (
                <li key={item.variant_id} className="flex items-start gap-3 py-3">
                  {image ? (
                    /* eslint-disable-next-line @next/next/no-img-element */
                    <img
                      src={image}
                      alt={item.product_name}
                      className="size-12 shrink-0 rounded-[6px] border border-line bg-paper object-cover"
                    />
                  ) : (
                    <span
                      aria-hidden="true"
                      className="size-12 shrink-0 rounded-[6px] border border-line bg-paper"
                    />
                  )}

                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-ink">{item.product_name}</p>
                    <p className="truncate text-xs text-ink-subtle">
                      {[item.variant_name, item.sku].filter(Boolean).join(" · ")}
                    </p>
                    <p className="mt-1 text-xs text-ink-muted sm:hidden">
                      <Money value={item.price} /> · {item.quantity}×
                    </p>
                  </div>

                  <span className="hidden w-28 text-right text-sm text-ink-muted sm:block">
                    <Money value={item.price} />
                  </span>
                  <span className="hidden w-14 text-right text-sm text-ink-muted sm:block">
                    {item.quantity}
                  </span>
                  <span className="w-24 shrink-0 text-right text-sm font-medium text-ink sm:w-28">
                    <Money value={item.line_total} />
                  </span>
                </li>
              );
            })}
          </ul>
        </div>

        <dl className="space-y-1.5 border-t border-line pt-3 text-sm">
          <div className="flex items-center justify-between gap-4">
            <dt className="text-ink-muted">Subtotal Produk</dt>
            <dd className="font-medium text-ink">
              <Money value={group.subtotal} />
            </dd>
          </div>

          <div className="flex items-center justify-between gap-4">
            <dt className="text-ink-muted">Ongkir Pengiriman</dt>
            <dd className="flex items-center gap-2">
              {selected ? (
                <span className="font-medium text-ink">
                  <Money value={group.shipping_cost} />
                </span>
              ) : (
                <span className="text-ink-subtle">—</span>
              )}
              <button
                type="button"
                onClick={() => setShipOpen((open) => !open)}
                className="text-xs font-medium text-brand hover:underline"
              >
                {selected ? "Ubah Kurir" : "Pilih Kurir"}
              </button>
            </dd>
          </div>

          <div className="flex items-center justify-between gap-4">
            <dt className="text-ink-muted">Potongan Voucher</dt>
            <dd className="flex items-center gap-2">
              {group.discount > 0 ? (
                <span className="font-medium text-success">
                  -<Money value={group.discount} />
                </span>
              ) : (
                <span className="text-ink-subtle">Rp 0</span>
              )}
              <button
                type="button"
                onClick={onUseVoucher}
                className="text-xs font-medium text-brand hover:underline"
              >
                {voucherActive ? "Ubah Voucher" : "Gunakan Voucher"}
              </button>
            </dd>
          </div>
        </dl>

        {showShippingList ? (
          <div className="space-y-1.5 border-t border-line pt-3">
            <p className="text-xs font-medium uppercase tracking-[0.12em] text-ink-subtle">
              Pilih kurir
            </p>
            {group.shipping_options.length === 0 ? (
              <p className="text-sm text-ink-muted">
                Ongkir belum tersedia. Lengkapi alamat pengiriman terlebih dahulu.
              </p>
            ) : (
              group.shipping_options.map((option) => {
                const active = selectedServiceCode === option.code;

                return (
                  <label
                    key={option.code}
                    className={cn(
                      "flex cursor-pointer items-center justify-between gap-3 rounded-[8px] border px-3 py-2 text-sm",
                      active ? "border-brand bg-brand-soft" : "border-line hover:border-line-strong",
                    )}
                  >
                    <span className="flex items-center gap-2">
                      <input
                        type="radio"
                        name={`shipping-${group.seller.id}`}
                        checked={active}
                        onChange={() => {
                          onSelectShipping(group.seller.id, option.code);
                          setShipOpen(false);
                        }}
                        className="size-4 accent-[#0875E2]"
                      />
                      <span className="text-ink">
                        {option.name}
                        {option.etd ? (
                          <span className="text-xs text-ink-subtle"> · {option.etd}</span>
                        ) : null}
                      </span>
                    </span>
                    <Money value={option.cost} className="text-ink" />
                  </label>
                );
              })
            )}
          </div>
        ) : null}
      </CardBody>
    </Card>
  );
}
