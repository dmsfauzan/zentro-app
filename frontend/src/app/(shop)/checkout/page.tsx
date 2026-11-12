"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMemo, useRef, useState } from "react";
import { MapPin, TicketPercent } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Money } from "@/components/ui/Money";
import { Skeleton } from "@/components/ui/Skeleton";
import { PaymentMethodPicker } from "@/components/payment/PaymentMethodPicker";
import { StoreGroupCard } from "@/components/checkout/StoreGroupCard";
import { useAddresses } from "@/features/account";
import { useCart } from "@/features/cart";
import {
  useCheckoutPreview,
  usePlaceOrder,
  type ShippingSelection,
} from "@/features/checkout";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { apiFetch, errorMessage } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import { newIdempotencyKey } from "@/lib/idempotency";
import { formatRupiah } from "@/lib/format";
import { cn } from "@/lib/cn";

export default function CheckoutPage() {
  const { token, user, loading } = useAuth();
  const router = useRouter();
  const toast = useToast();
  const addresses = useAddresses();
  const cart = useCart();
  const placeOrder = usePlaceOrder();
  const idempotencyKey = useRef(newIdempotencyKey("checkout"));

  const [addressId, setAddressId] = useState<number | null>(null);
  const [addressPanelOpen, setAddressPanelOpen] = useState(false);
  const [selections, setSelections] = useState<Record<number, string>>({});
  const [voucherCode, setVoucherCode] = useState<string | null>(null);
  const [voucherInput, setVoucherInput] = useState("");
  const [voucherPanelOpen, setVoucherPanelOpen] = useState(false);
  const [methodChoice, setMethodChoice] = useState<string | null>(null);

  const items = useMemo(
    () =>
      (cart.data?.items ?? []).map((item) => ({
        variant_id: item.variant_id,
        quantity: item.quantity,
      })),
    [cart.data],
  );

  const itemImages = useMemo(() => {
    const map: Record<number, string | null> = {};

    for (const item of cart.data?.items ?? []) {
      map[item.variant_id] = item.primary_image;
    }

    return map;
  }, [cart.data]);

  const selectedAddress =
    addressId ?? addresses.data?.find((address) => address.is_default)?.id ?? addresses.data?.[0]?.id ?? null;

  const shippingSelections: ShippingSelection[] = Object.entries(selections).map(
    ([sellerId, serviceCode]) => ({
      seller_id: Number(sellerId),
      service_code: serviceCode,
    }),
  );

  const preview = useCheckoutPreview(
    {
      items,
      address_id: selectedAddress,
      shipping_selections: shippingSelections,
      voucher_code: voucherCode,
    },
    Boolean(token) && items.length > 0 && Boolean(selectedAddress),
  );

  const availableVouchers = preview.data?.available_vouchers ?? [];
  const availableMethods = preview.data?.available_methods ?? [];
  const selectedMethod = methodChoice ?? availableMethods[0]?.key ?? null;
  const summary = preview.data?.summary;

  if (loading) {
    return <div className="container-page py-8"><Skeleton className="h-72 w-full" /></div>;
  }

  if (!token) {
    return (
      <div className="container-page py-14">
        <EmptyState
          title="Masuk untuk checkout"
          action={<ButtonLink href="/login?next=/checkout">Masuk</ButtonLink>}
        />
      </div>
    );
  }

  if (cart.isPending) {
    return (
      <div className="container-page space-y-4 py-8">
        <Skeleton className="h-8 w-40" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  if ((cart.data?.items.length ?? 0) === 0) {
    return (
      <div className="container-page py-14">
        <EmptyState
          title="Keranjang kosong"
          description="Tambahkan produk sebelum checkout."
          action={<ButtonLink href="/produk">Lihat produk</ButtonLink>}
        />
      </div>
    );
  }

  const submit = async () => {
    if (!selectedAddress) {
      toast.error("Pilih alamat pengiriman dulu");
      return;
    }

    try {
      const result = await placeOrder.mutateAsync({
        payload: {
          items,
          address_id: selectedAddress,
          shipping_selections: shippingSelections,
          voucher_code: voucherCode,
        },
        idempotencyKey: idempotencyKey.current,
      });

      // Transaksi langsung dibuat dengan metode yang dipilih di checkout.
      // Kegagalan di sini tidak membatalkan order: pembeli masih bisa memilih
      // metode di halaman pembayaran.
      if (selectedMethod !== null && availableMethods.length > 0) {
        try {
          await apiFetch(endpoints.payment.retry(result.order_number), {
            method: "POST",
            token,
            json: { method: selectedMethod },
          });
        } catch {
          // diabaikan
        }
      }

      toast.success("Pesanan dibuat");
      router.push(`/pembayaran/${result.order_number}`);
    } catch (caught) {
      toast.error("Checkout gagal", errorMessage(caught));
    }
  };

  return (
    <div className="container-page py-8">
      <h1 className="font-display text-3xl uppercase leading-none tracking-[0.01em] text-ink sm:text-4xl">
        Checkout
      </h1>

      <ol className="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-subtle">
        <li className="font-medium text-ink">1. Alamat</li>
        <li aria-hidden="true">/</li>
        <li className="font-medium text-ink">2. Pengiriman</li>
        <li aria-hidden="true">/</li>
        <li className="font-medium text-ink">3. Pembayaran</li>
      </ol>

      <div className="mt-6 grid gap-6 lg:grid-cols-12">
        <div className="space-y-4 lg:col-span-8">
          <Card className="border-l-4 border-l-brand">
            <CardHeader
              title={
                <span className="flex items-center gap-2">
                  <Icon as={MapPin} size="sm" className="text-brand" />
                  Alamat Pengiriman
                </span>
              }
              actions={
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => setAddressPanelOpen((open) => !open)}
                >
                  {addressPanelOpen ? "Tutup" : "Ubah Alamat"}
                </Button>
              }
            />
            <CardBody className="space-y-3">
              {addresses.isPending ? (
                <Skeleton className="h-20 w-full" />
              ) : (addresses.data?.length ?? 0) === 0 ? (
                <Alert tone="warning" title="Belum ada alamat">
                  Tambahkan alamat pengiriman terlebih dahulu di{" "}
                  <Link href="/akun/alamat" className="font-medium underline">
                    halaman alamat
                  </Link>.
                </Alert>
              ) : (
                (() => {
                  const address =
                    addresses.data?.find((item) => item.id === selectedAddress) ??
                    addresses.data?.[0];

                  if (!address) return null;

                  return (
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                          {address.recipient_name}
                          {address.is_default ? (
                            <span className="rounded-[4px] border border-brand/30 bg-brand-soft px-1.5 py-0.5 text-[10px] font-medium text-brand">
                              Alamat Utama
                            </span>
                          ) : null}
                        </p>
                        <p className="mt-1 text-xs text-ink-muted">
                          {[address.phone, user?.email].filter(Boolean).join(" · ")}
                        </p>
                        <p className="mt-1 text-sm leading-6 text-ink-muted">
                          {address.address_line}, {address.city}, {address.province}{" "}
                          {address.postal_code}
                        </p>
                      </div>
                      <Link
                        href="/akun/alamat"
                        className="text-xs font-medium text-brand hover:underline"
                      >
                        Kelola alamat
                      </Link>
                    </div>
                  );
                })()
              )}

              {addressPanelOpen && (addresses.data?.length ?? 0) > 0 ? (
                <div className="space-y-2 border-t border-line pt-3">
                  {addresses.data?.map((address) => {
                    const active = address.id === selectedAddress;

                    return (
                      <button
                        key={address.id}
                        type="button"
                        onClick={() => {
                          setAddressId(address.id);
                          setAddressPanelOpen(false);
                        }}
                        aria-pressed={active}
                        className={cn(
                          "w-full rounded-[8px] border px-3 py-2.5 text-left text-sm",
                          active ? "border-brand bg-brand-soft" : "border-line hover:border-line-strong",
                        )}
                      >
                        <span className="flex items-center gap-2 font-medium text-ink">
                          {address.recipient_name}
                          {address.is_default ? (
                            <span className="rounded-[4px] bg-canvas px-1.5 py-0.5 text-[10px] text-ink-subtle">
                              Utama
                            </span>
                          ) : null}
                        </span>
                        <span className="mt-0.5 block truncate text-xs text-ink-muted">
                          {address.phone} · {address.address_line}, {address.city}
                        </span>
                      </button>
                    );
                  })}
                </div>
              ) : null}
            </CardBody>
          </Card>

          {preview.isPending ? (
            <Skeleton className="h-64 w-full" />
          ) : preview.isError ? (
            <Alert tone="danger" title="Gagal menghitung checkout">
              {errorMessage(preview.error)}
            </Alert>
          ) : (
            (preview.data?.seller_groups ?? []).map((group) => (
              <StoreGroupCard
                key={group.seller.id}
                group={group}
                images={itemImages}
                selectedServiceCode={selections[group.seller.id] ?? group.selected_service_code}
                onSelectShipping={(sellerId, code) =>
                  setSelections((current) => ({ ...current, [sellerId]: code }))
                }
                onUseVoucher={() => setVoucherPanelOpen(true)}
                voucherActive={Boolean(voucherCode)}
              />
            ))
          )}

          {voucherPanelOpen ? (
            <Card className="border-l-4 border-l-brand">
              <CardHeader
                title={
                  <span className="flex items-center gap-2">
                    <Icon as={TicketPercent} size="sm" className="text-brand" />
                    Voucher
                  </span>
                }
                actions={
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setVoucherPanelOpen(false)}
                  >
                    Tutup
                  </Button>
                }
              />
              <CardBody className="space-y-3">
                <div className="flex flex-wrap gap-2">
                  <Input
                    aria-label="Kode voucher"
                    placeholder="Kode voucher"
                    className="h-10 w-48 uppercase"
                    value={voucherInput}
                    onChange={(event) => setVoucherInput(event.target.value)}
                  />
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() =>
                      setVoucherCode(voucherInput.trim() ? voucherInput.trim().toUpperCase() : null)
                    }
                  >
                    Terapkan
                  </Button>
                  {voucherCode ? (
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={() => {
                        setVoucherCode(null);
                        setVoucherInput("");
                      }}
                    >
                      Hapus
                    </Button>
                  ) : null}
                </div>

                {availableVouchers.length > 0 ? (
                  <div className="space-y-1.5">
                    {availableVouchers.map((voucher) => {
                      const checked = voucherCode === voucher.code;

                      return (
                        <label
                          key={voucher.code}
                          className={cn(
                            "flex cursor-pointer items-start justify-between gap-3 rounded-[8px] border px-3 py-2 text-sm",
                            checked ? "border-brand bg-brand-soft" : "border-line hover:border-line-strong",
                          )}
                        >
                          <span className="flex items-start gap-2">
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={() => {
                                setVoucherCode(checked ? null : voucher.code);

                                if (!checked) setVoucherInput(voucher.code);
                              }}
                              className="mt-0.5 size-4 accent-[#0875E2]"
                            />
                            <span className="text-ink">
                              {voucher.name}
                              <span className="block text-xs text-ink-subtle">
                                {voucher.code}
                                {voucher.scope === "seller" ? " · Voucher toko" : ""}
                              </span>
                              {voucher.min_purchase > 0 ? (
                                <span className="block text-xs text-ink-subtle">
                                  Min. belanja {formatRupiah(voucher.min_purchase)}
                                </span>
                              ) : null}
                            </span>
                          </span>
                          <span className="shrink-0 font-medium text-success">
                            -<Money value={voucher.discount} />
                          </span>
                        </label>
                      );
                    })}
                  </div>
                ) : null}

                {preview.isError && voucherCode ? (
                  <p className="text-xs text-danger">{errorMessage(preview.error)}</p>
                ) : null}
              </CardBody>
            </Card>
          ) : null}

          <Card className="border-l-4 border-l-brand">
            <CardHeader
              title="Metode Pembayaran"
              description="Pilih metode yang ingin kamu gunakan."
            />
            <CardBody>
              <PaymentMethodPicker
                methods={availableMethods}
                value={selectedMethod}
                onChange={setMethodChoice}
                disabled={preview.isPending}
              />
            </CardBody>
          </Card>
        </div>

        <aside className="lg:col-span-4">
          <Card className="lg:sticky lg:top-24">
            <CardBody className="space-y-4">
              <h2 className="text-base font-semibold text-ink">Ringkasan Belanja</h2>

              <dl className="space-y-2 text-sm">
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">Subtotal Produk</dt>
                  <dd className="text-ink"><Money value={summary?.subtotal ?? 0} /></dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">Total Ongkos Kirim</dt>
                  <dd className="text-ink"><Money value={summary?.shipping_total ?? 0} /></dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">Total Potongan Diskon</dt>
                  <dd className={summary?.discount_total ? "text-success" : "text-ink"}>
                    {summary?.discount_total ? "-" : ""}
                    <Money value={summary?.discount_total ?? 0} />
                  </dd>
                </div>
                <div className="flex items-baseline justify-between gap-4 border-t border-line pt-3">
                  <dt className="font-semibold text-ink">Total Pembayaran</dt>
                  <dd className="text-lg font-semibold text-ink">
                    <Money value={summary?.grand_total ?? 0} />
                  </dd>
                </div>
              </dl>

              <Button
                className="w-full"
                onClick={() => void submit()}
                loading={placeOrder.isPending}
                disabled={!selectedAddress || !preview.data || preview.isError}
              >
                Lanjutkan Pembayaran
              </Button>
              <p className="text-xs text-ink-subtle">
                Dengan membuat pesanan, kamu menyetujui harga dan ongkir yang dihitung server.
              </p>
            </CardBody>
          </Card>
        </aside>
      </div>
    </div>
  );
}
