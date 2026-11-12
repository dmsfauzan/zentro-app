"use client";

import { useState } from "react";
import { ShoppingCart, Trash2 } from "lucide-react";
import { ButtonLink } from "@/components/ui/Button";
import { Card, CardBody } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { Skeleton } from "@/components/ui/Skeleton";
import { Alert } from "@/components/ui/Alert";
import { useCart, useRemoveCartItem, useUpdateCartItem } from "@/features/cart";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";

export default function CartPage() {
  const { token, loading } = useAuth();
  const toast = useToast();
  const cart = useCart();
  const update = useUpdateCartItem();
  const remove = useRemoveCartItem();
  const [busyId, setBusyId] = useState<number | null>(null);

  if (loading) {
    return <div className="container-page py-8"><Skeleton className="h-64 w-full" /></div>;
  }

  if (!token) {
    return (
      <div className="container-page py-14">
        <EmptyState
          icon={ShoppingCart}
          title="Masuk untuk melihat keranjang"
          description="Keranjang tersimpan di akunmu."
          action={<ButtonLink href="/login?next=/keranjang">Masuk</ButtonLink>}
        />
      </div>
    );
  }

  if (cart.isPending) {
    return (
      <div className="container-page space-y-4 py-8">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  const items = cart.data?.items ?? [];
  const summary = cart.data?.summary;

  if (items.length === 0) {
    return (
      <div className="container-page py-14">
        <EmptyState
          icon={ShoppingCart}
          title="Keranjang masih kosong"
          description="Mulai dari produk terbaru atau cari kebutuhanmu."
          action={<ButtonLink href="/produk">Lihat produk</ButtonLink>}
        />
      </div>
    );
  }

  const groups = new Map<string, typeof items>();

  for (const item of items) {
    const key = item.seller?.store_name ?? "Penjual";
    groups.set(key, [...(groups.get(key) ?? []), item]);
  }

  const changeQuantity = async (id: number, quantity: number) => {
    setBusyId(id);
    try {
      await update.mutateAsync({ id, quantity });
    } catch (caught) {
      toast.error("Gagal memperbarui", errorMessage(caught));
    } finally {
      setBusyId(null);
    }
  };

  const removeItem = async (id: number) => {
    setBusyId(id);
    try {
      await remove.mutateAsync(id);
      toast.success("Item dihapus");
    } catch (caught) {
      toast.error("Gagal menghapus", errorMessage(caught));
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="container-page py-8 lg:py-10">
      <nav className="mb-4 flex items-center gap-1.5 text-[13px] text-ink-subtle" aria-label="Breadcrumb">
        <span>Beranda</span>
        <span aria-hidden="true">/</span>
        <span className="text-ink">Keranjang</span>
      </nav>

      <h1 className="font-display text-3xl uppercase leading-none tracking-[0.01em] text-ink sm:text-4xl">
        Keranjang{" "}
        <span className="num text-ink-subtle">({summary?.item_count ?? 0})</span>
      </h1>

      {summary?.has_unavailable ? (
        <div className="mt-5">
          <Alert tone="warning" title="Ada item yang tidak tersedia">
            Periksa jumlah atau hapus item yang stoknya tidak mencukupi sebelum checkout.
          </Alert>
        </div>
      ) : null}

      <div className="mt-7 grid gap-6 lg:grid-cols-12">
        <div className="space-y-4 lg:col-span-7">
          {Array.from(groups.entries()).map(([seller, group]) => (
            <div
              key={seller}
              className="overflow-hidden rounded-[16px] border border-line bg-canvas"
            >
              <div className="border-b border-line px-5 py-3 text-sm">
                <span className="text-ink-subtle">Dijual oleh </span>
                <span className="font-medium text-ink">{seller}</span>
              </div>
              <div className="divide-y divide-line">
                {group.map((item) => (
                  <div key={item.id} className="flex gap-4 p-4 sm:p-5">
                    <div className="size-24 shrink-0 overflow-hidden rounded-[12px] bg-paper">
                      {item.primary_image ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                          src={item.primary_image}
                          alt={item.product_name ?? "Produk"}
                          className="size-full object-cover"
                          loading="lazy"
                        />
                      ) : (
                        <span className="flex size-full items-center justify-center font-display text-2xl text-ink-subtle">
                          {(item.product_name ?? "?").slice(0, 1).toUpperCase()}
                        </span>
                      )}
                    </div>

                    <div className="flex min-w-0 flex-1 flex-col">
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                          <p className="line-clamp-2 text-sm font-semibold text-ink">
                            {item.product_name}
                          </p>
                          <p className="mt-0.5 text-xs text-ink-subtle">
                            {item.variant_name ?? item.sku}
                          </p>
                          {!item.is_available ? (
                            <p className="mt-1 text-xs text-danger">
                              Stok tersisa {item.available_stock}
                            </p>
                          ) : null}
                        </div>
                        <button
                          type="button"
                          onClick={() => void removeItem(item.id)}
                          disabled={busyId === item.id}
                          className="shrink-0 rounded-full p-1.5 text-danger hover:bg-danger-soft disabled:opacity-40"
                          aria-label={`Hapus ${item.product_name ?? "item"}`}
                        >
                          <Icon as={Trash2} size="sm" />
                        </button>
                      </div>

                      <div className="mt-auto flex flex-wrap items-center justify-between gap-3 pt-3">
                        <QuantityStepper
                          value={item.quantity}
                          min={1}
                          max={Math.max(1, item.available_stock)}
                          disabled={busyId === item.id}
                          onChange={(quantity) => void changeQuantity(item.id, quantity)}
                        />
                        <Money
                          value={item.line_total}
                          className="text-base font-semibold text-ink"
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>

        <aside className="lg:col-span-5">
          <Card className="rounded-[16px] lg:sticky lg:top-32">
            <CardBody className="space-y-4">
              <h2 className="font-display text-xl uppercase tracking-[0.02em] text-ink">
                Ringkasan pesanan
              </h2>
              <dl className="space-y-2.5 text-sm">
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">Subtotal</dt>
                  <dd><Money value={summary?.subtotal ?? 0} /></dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">Jumlah toko</dt>
                  <dd className="num">{summary?.seller_count ?? 0}</dd>
                </div>
                <div className="flex justify-between gap-4 border-t border-line pt-3 text-base font-semibold">
                  <dt>Total sementara</dt>
                  <dd><Money value={summary?.subtotal ?? 0} /></dd>
                </div>
              </dl>
              <ButtonLink href="/checkout" size="md" className="w-full">
                Lanjut ke checkout
              </ButtonLink>
              <p className="text-xs text-ink-subtle">
                Ongkir dan voucher dihitung per penjual pada langkah checkout.
              </p>
            </CardBody>
          </Card>
        </aside>
      </div>
    </div>
  );
}
