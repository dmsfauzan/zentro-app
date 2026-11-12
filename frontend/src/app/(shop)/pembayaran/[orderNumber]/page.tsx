"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useState } from "react";
import { Check, Copy, ExternalLink, RefreshCw } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PaymentMethodLogo } from "@/components/payment/PaymentMethodLogo";
import { PaymentMethodPicker } from "@/components/payment/PaymentMethodPicker";
import {
  useOrder,
  usePayment,
  useRetryPayment,
  useSimulatePayment,
} from "@/features/orders";
import type { PaymentInstructions } from "@/lib/api/domain";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { ApiError, errorMessage } from "@/lib/api/client";
import { formatDateTime } from "@/lib/format";

function hasInstructions(payment: PaymentInstructions): boolean {
  return Boolean(
    payment.va_number ||
      payment.qr_code_url ||
      payment.qr_string ||
      payment.deeplink_url ||
      payment.biller_code ||
      payment.redirect_url ||
      payment.token,
  );
}

const WALLET_METHODS = ["qris", "gopay", "shopeepay"];

/**
 * Jenis instruksi berdasarkan metode terpilih, sehingga instruksi metode lama
 * (mis. nomor VA saat pembeli pindah ke QRIS) tidak ikut terender.
 *
 * Nilai `payment_type` asli Midtrans (`bank_transfer`, `echannel`) juga dikenali
 * agar baris lama tetap tampil benar.
 */
function instructionKind(method: string | null): "qr" | "va" | "bill" | "other" | "unknown" {
  const key = (method ?? "").toLowerCase();

  if (key === "") return "unknown";
  if (WALLET_METHODS.includes(key)) return "qr";
  if (key === "bank_transfer") return "va";
  if (key === "echannel" || key === "mandiri_va") return "bill";
  if (key.endsWith("_va")) return "va";

  return "other";
}

export default function PaymentPage() {
  const { orderNumber } = useParams<{ orderNumber: string }>();
  const router = useRouter();
  const { isDev } = useAuth();
  const toast = useToast();
  const [pickerOpen, setPickerOpen] = useState(false);
  const [copied, setCopied] = useState<string | null>(null);
  const order = useOrder(orderNumber);
  const payment = usePayment(orderNumber);
  const retry = useRetryPayment(orderNumber);
  const simulate = useSimulatePayment(orderNumber);

  if (order.isPending) {
    return <div className="container-page py-8"><Skeleton className="h-72 w-full" /></div>;
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
  const paid = data.status !== "pending_payment";
  const instructions = payment.data;
  const methods = instructions?.available_methods ?? [];
  const ready = Boolean(instructions && hasInstructions(instructions));
  const showPicker = !ready || pickerOpen;

  const kind = instructionKind(instructions?.method ?? null);
  const methodLabel =
    instructions?.available_methods?.find((method) => method.key === instructions.method)?.label ??
    instructions?.method ??
    instructions?.provider ??
    null;
  const showQr = kind === "qr" || kind === "unknown" || kind === "other";
  const showVa = kind === "va" || kind === "unknown" || kind === "other";
  const showBill = kind === "bill" || kind === "unknown" || kind === "other";

  const qrCodeUrl = showQr ? instructions?.qr_code_url ?? null : null;
  const qrString = showQr ? instructions?.qr_string ?? null : null;
  const vaNumber = showVa ? instructions?.va_number ?? null : null;
  const billerCode = showBill ? instructions?.biller_code ?? null : null;
  const billKey = showBill ? instructions?.bill_key ?? null : null;
  const showDeeplink = kind === "qr" || kind === "unknown" || kind === "other";
  const deeplinkUrl = showDeeplink ? instructions?.deeplink_url ?? null : null;
  const redirectUrl =
    instructions?.redirect_url && !qrCodeUrl && !qrString && !vaNumber && !billerCode
      ? instructions.redirect_url
      : null;

  const chooseMethod = (method: string, label: string) => {
    retry.mutate(method, {
      onSuccess: () => {
        setPickerOpen(false);
        toast.success(`${label} dipilih`, "Instruksi pembayaran sudah dibuat.");
      },
      onError: (caught) => toast.error("Gagal", errorMessage(caught)),
    });
  };

  const copyValue = async (value: string, key: string) => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(key);
      toast.success("Disalin", value);
      setTimeout(() => setCopied(null), 2000);
    } catch {
      toast.error("Gagal menyalin", "Salin manual ya.");
    }
  };

  return (
    <div className="container-page py-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-xs font-medium uppercase tracking-[0.12em] text-ink-subtle">
            Pembayaran
          </p>
          <h1 className="num mt-1 text-2xl font-semibold tracking-[-0.01em] text-ink">
            {data.order_number}
          </h1>
        </div>
        <StatusBadge domain="order" value={data.status} />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-12">
        <div className="space-y-4 lg:col-span-7">
          <Card>
            <CardHeader title="Instruksi pembayaran" />
            <CardBody className="space-y-4">
              {paid ? (
                <Alert tone="success" title="Pembayaran diterima">
                  Pesanan sudah dibayar. Lanjutkan ke detail pesanan untuk melihat status pengiriman.
                </Alert>
              ) : payment.isPending ? (
                <Skeleton className="h-28 w-full" />
              ) : payment.isError ? (
                <div className="space-y-3">
                  <Alert tone="warning" title="Pembayaran belum tersedia">
                    {payment.error instanceof ApiError && payment.error.code === "PAYMENT_NOT_FOUND"
                      ? "Pembayaran belum dibuat untuk pesanan ini."
                      : errorMessage(payment.error)}
                  </Alert>
                  <Button
                    variant="secondary"
                    onClick={() => {
                      retry.mutate(undefined, {
                        onSuccess: () => {
                          void payment.refetch();
                          toast.success("Percobaan pembayaran dibuat");
                        },
                        onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                      });
                    }}
                    loading={retry.isPending}
                  >
                    Buat percobaan pembayaran
                  </Button>
                </div>
              ) : instructions ? (
                <div className="space-y-4">
                  {showPicker && methods.length > 0 ? (
                    <div className="space-y-3">
                      <div className="flex items-center justify-between gap-3">
                        <p className="text-sm font-medium text-ink">
                          {ready ? "Ganti metode pembayaran" : "Pilih metode pembayaran untuk melanjutkan"}
                        </p>
                        {ready ? (
                          <Button variant="ghost" size="sm" onClick={() => setPickerOpen(false)}>
                            Batal
                          </Button>
                        ) : null}
                      </div>

                      <PaymentMethodPicker
                        methods={methods}
                        value={instructions.method}
                        onChange={(method) => {
                          const label = methods.find((item) => item.key === method)?.label ?? method;

                          chooseMethod(method, label);
                        }}
                        disabled={retry.isPending}
                      />

                      {retry.isPending ? (
                        <p className="text-xs text-ink-subtle">Menghubungi penyedia pembayaran…</p>
                      ) : null}
                    </div>
                  ) : null}

                  {ready ? (
                    <div className="space-y-3">
                      <div className="flex items-center justify-between gap-4 text-sm">
                        <span className="text-ink-muted">Metode</span>
                        <span className="flex items-center gap-2 font-medium text-ink">
                          <PaymentMethodLogo method={instructions.method} label={methodLabel} />
                          {methodLabel}
                        </span>
                      </div>
                      <div className="flex items-center justify-between gap-4 text-sm">
                        <span className="text-ink-muted">Status</span>
                        <StatusBadge domain="payment" value={instructions.status} />
                      </div>
                      <div className="flex items-center justify-between gap-4 text-sm">
                        <span className="text-ink-muted">Jumlah</span>
                        <Money value={instructions.amount} className="text-base font-semibold" />
                      </div>

                      {vaNumber ? (
                        <div className="rounded-[8px] border border-line bg-paper px-3 py-3 text-center">
                          <p className="text-xs text-ink-subtle">Nomor Virtual Account</p>
                          <p className="num mt-1 text-xl font-semibold tracking-[0.08em] text-ink">
                            {vaNumber}
                          </p>
                          <Button
                            variant="ghost"
                            size="sm"
                            className="mt-2"
                            onClick={() => void copyValue(vaNumber, "va_number")}
                          >
                            <Icon as={copied === "va_number" ? Check : Copy} size="xs" />
                            Salin
                          </Button>
                        </div>
                      ) : null}

                      {billerCode && billKey ? (
                        <div className="space-y-2 rounded-[8px] border border-line bg-paper px-3 py-3">
                          <div className="text-center">
                            <p className="text-xs text-ink-subtle">Kode Biller</p>
                            <p className="num mt-1 text-lg font-semibold tracking-[0.08em] text-ink">
                              {billerCode}
                            </p>
                          </div>
                          <div className="text-center">
                            <p className="text-xs text-ink-subtle">Nomor Pembayaran</p>
                            <p className="num mt-1 text-xl font-semibold tracking-[0.08em] text-ink">
                              {billKey}
                            </p>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="mt-2"
                              onClick={() => void copyValue(billKey, "bill_key")}
                            >
                              <Icon as={copied === "bill_key" ? Check : Copy} size="xs" />
                              Salin
                            </Button>
                          </div>
                        </div>
                      ) : null}

                      {qrCodeUrl ? (
                        <div className="rounded-[8px] border border-line bg-paper px-3 py-4 text-center">
                          <p className="text-xs text-ink-subtle">Pindai kode QR</p>
                          {/* eslint-disable-next-line @next/next/no-img-element */}
                          <img
                            src={qrCodeUrl}
                            alt="Kode QR pembayaran"
                            className="mx-auto mt-3 h-52 w-52 rounded-[6px] bg-white object-contain"
                          />
                        </div>
                      ) : qrString ? (
                        <div className="rounded-[8px] border border-line bg-paper px-3 py-3">
                          <p className="text-xs text-ink-subtle">String QR</p>
                          <p className="num mt-1 break-all text-xs text-ink">{qrString}</p>
                          <Button
                            variant="ghost"
                            size="sm"
                            className="mt-2"
                            onClick={() => void copyValue(qrString, "qr_string")}
                          >
                            <Icon as={copied === "qr_string" ? Check : Copy} size="xs" />
                            Salin
                          </Button>
                        </div>
                      ) : null}

                      {deeplinkUrl ? (
                        <a
                          href={deeplinkUrl}
                          className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-[8px] bg-brand text-sm font-medium text-white hover:bg-brand-strong"
                        >
                          Buka aplikasi pembayaran
                          <Icon as={ExternalLink} size="xs" />
                        </a>
                      ) : null}

                      {redirectUrl ? (
                        <a
                          href={redirectUrl}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-[8px] bg-brand text-sm font-medium text-white hover:bg-brand-strong"
                        >
                          Buka halaman pembayaran
                          <Icon as={ExternalLink} size="xs" />
                        </a>
                      ) : null}

                      {instructions.expires_at ? (
                        <p className="text-xs text-ink-subtle">
                          Bayar sebelum {formatDateTime(instructions.expires_at)}.
                        </p>
                      ) : null}

                      <div className="flex flex-wrap gap-2 border-t border-line pt-3">
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => {
                            void order.refetch();
                            void payment.refetch();
                          }}
                        >
                          <Icon as={RefreshCw} size="xs" />
                          Cek status
                        </Button>
                        {!paid && methods.length > 0 ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setPickerOpen(true)}
                          >
                            Ganti metode
                          </Button>
                        ) : null}
                      </div>
                    </div>
                  ) : null}
                </div>
              ) : null}

              {isDev && !paid && instructions?.provider === "fake" ? (
                <div className="rounded-[8px] border border-dashed border-line-strong p-3">
                  <p className="text-xs font-medium text-ink-muted">
                    Simulator lokal (driver pembayaran palsu)
                  </p>
                  <div className="mt-2 flex gap-2">
                    <Button
                      size="sm"
                      variant="secondary"
                      loading={simulate.isPending}
                      onClick={() => {
                        simulate.mutate("paid", {
                          onSuccess: () => {
                            void order.refetch();
                            void payment.refetch();
                            toast.success("Pembayaran disimulasikan");
                          },
                          onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                        });
                      }}
                    >
                      Tandai lunas
                    </Button>
                  </div>
                </div>
              ) : null}
            </CardBody>
          </Card>
        </div>

        <aside className="lg:col-span-5">
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
              <div className="space-y-2 pt-3">
                <ButtonLink href={`/pesanan/${data.order_number}`} variant="secondary" className="w-full">
                  Lihat detail pesanan
                </ButtonLink>
                {paid ? (
                  <ButtonLink href={`/pesanan/${data.order_number}/invoice`} className="w-full">
                    Lihat invoice
                  </ButtonLink>
                ) : null}
              </div>
              <p className="pt-1 text-xs text-ink-subtle">
                Status akan diperbarui setelah pembayaran dikonfirmasi.
              </p>
              <button
                type="button"
                onClick={() => router.push("/pesanan")}
                className="text-xs font-medium text-brand hover:underline"
              >
                Kembali ke daftar pesanan
              </button>
            </CardBody>
          </Card>
        </aside>
      </div>

      <p className="mt-4 text-xs text-ink-subtle">
        Butuh bantuan? <Link href="/akun" className="underline">Buka akun</Link>.
      </p>
    </div>
  );
}
