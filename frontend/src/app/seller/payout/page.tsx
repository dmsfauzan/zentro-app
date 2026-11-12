"use client";

import { useRef, useState } from "react";
import { Banknote, Plus } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Field, Input, Select } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { EmptyState } from "@/components/ui/EmptyState";
import {
  useBankAccounts,
  useCancelPayout,
  useCreateBankAccount,
  usePayouts,
  useRequestPayout,
  useWallet,
} from "@/features/wallet";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { newIdempotencyKey } from "@/lib/idempotency";
import { formatDateTime } from "@/lib/format";

export default function PayoutPage() {
  const wallet = useWallet();
  const banks = useBankAccounts();
  const createBank = useCreateBankAccount();
  const payouts = usePayouts({ per_page: 20 });
  const request = useRequestPayout();
  const cancel = useCancelPayout();
  const toast = useToast();

  const [bankForm, setBankForm] = useState({ bank_name: "", account_number: "", account_name: "" });
  const [amount, setAmount] = useState("");
  const [bankId, setBankId] = useState("");
  const idempotencyKey = useRef(newIdempotencyKey("payout"));

  const available = wallet.data?.available_balance ?? 0;
  const debt = wallet.data?.debt_balance ?? 0;
  const activePayout = (payouts.data?.items ?? []).some((payout) =>
    ["requested", "approved", "processing"].includes(payout.status),
  );

  if (wallet.isPending) {
    return <div className="space-y-4"><Skeleton className="h-24 w-full" /><Skeleton className="h-64 w-full" /></div>;
  }

  return (
    <div className="space-y-5">
      <PageHeader title="Payout" description="Ajukan pencairan saldo tersedia ke rekening bank." eyebrow="Keuangan" />

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader title="Ajukan payout" description={`Saldo tersedia: ${new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(available)}`} />
          <CardBody className="space-y-4">
            {activePayout ? (
              <Alert tone="warning" title="Ada payout aktif">
                Selesaikan atau batalkan payout yang sedang diproses sebelum mengajukan yang baru.
              </Alert>
            ) : null}
            {debt > 0 ? (
              <Alert tone="danger" title="Masih ada utang">
                Payout diblokir sampai utang refund dilunasi dari penjualan berikutnya.
              </Alert>
            ) : null}
            {(banks.data?.length ?? 0) === 0 ? (
              <Alert tone="info" title="Tambahkan rekening dulu">Rekening diperlukan untuk pencairan dana.</Alert>
            ) : (
              <form
                className="grid gap-3 sm:grid-cols-2"
                onSubmit={(event) => {
                  event.preventDefault();
                  request.mutate(
                    {
                      amount: Number(amount),
                      bankAccountId: Number(bankId || banks.data?.[0]?.id),
                      idempotencyKey: idempotencyKey.current,
                    },
                    {
                      onSuccess: () => {
                        setAmount("");
                        idempotencyKey.current = newIdempotencyKey("payout");
                        toast.success("Permintaan payout dibuat");
                      },
                      onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                    },
                  );
                }}
              >
                <Field label="Jumlah (Rp)" htmlFor="amount" required>
                  <Input id="amount" inputMode="numeric" value={amount} onChange={(event) => setAmount(event.target.value)} required />
                </Field>
                <Field label="Rekening" htmlFor="bank">
                  <Select id="bank" value={bankId} onChange={(event) => setBankId(event.target.value)}>
                    {(banks.data ?? []).map((bank) => (
                      <option key={bank.id} value={bank.id}>
                        {bank.bank_name} · {bank.account_number}
                      </option>
                    ))}
                  </Select>
                </Field>
                <div className="sm:col-span-2">
                  <Button type="submit" loading={request.isPending} disabled={activePayout || debt > 0}>
                    <Icon as={Banknote} size="xs" />
                    Ajukan payout
                  </Button>
                </div>
              </form>
            )}

            <div className="border-t border-line pt-4">
              <p className="mb-2 text-sm font-medium text-ink">Tambah rekening</p>
              <form
                className="grid gap-3 sm:grid-cols-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  createBank.mutate(bankForm, {
                    onSuccess: () => {
                      setBankForm({ bank_name: "", account_number: "", account_name: "" });
                      toast.success("Rekening ditambahkan");
                    },
                    onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                  });
                }}
              >
                <Field label="Bank" htmlFor="bank_name" required>
                  <Input id="bank_name" value={bankForm.bank_name} onChange={(event) => setBankForm({ ...bankForm, bank_name: event.target.value })} required />
                </Field>
                <Field label="Nomor rekening" htmlFor="account_number" required>
                  <Input id="account_number" value={bankForm.account_number} onChange={(event) => setBankForm({ ...bankForm, account_number: event.target.value })} required />
                </Field>
                <Field label="Nama pemilik" htmlFor="account_name" required>
                  <Input id="account_name" value={bankForm.account_name} onChange={(event) => setBankForm({ ...bankForm, account_name: event.target.value })} required />
                </Field>
                <div className="sm:col-span-3">
                  <Button type="submit" variant="secondary" loading={createBank.isPending}>
                    <Icon as={Plus} size="xs" />
                    Simpan rekening
                  </Button>
                </div>
              </form>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Rekening tersimpan" />
          <CardBody className="space-y-2 text-sm">
            {(banks.data ?? []).map((bank) => (
              <div key={bank.id} className="rounded-[6px] border border-line px-3 py-2">
                <p className="font-medium text-ink">{bank.bank_name}</p>
                <p className="num text-xs text-ink-muted">{bank.account_number}</p>
                <p className="text-xs text-ink-subtle">{bank.account_name}</p>
              </div>
            ))}
            {(banks.data?.length ?? 0) === 0 ? <p className="text-ink-muted">Belum ada rekening.</p> : null}
          </CardBody>
        </Card>
      </div>

      <Card>
        <CardHeader title="Riwayat payout" />
        {payouts.isPending ? (
          <div className="p-4"><Skeleton className="h-32 w-full" /></div>
        ) : (payouts.data?.items.length ?? 0) === 0 ? (
          <div className="p-4"><EmptyState title="Belum ada payout" description="Riwayat pencairan akan tampil di sini." /></div>
        ) : (
          <TableWrap>
            <THead>
              <TR>
                <TH>Nomor</TH>
                <TH>Status</TH>
                <TH numeric>Jumlah</TH>
                <TH>Diajukan</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {payouts.data?.items.map((payout) => (
                <TR key={payout.id}>
                  <TD><span className="num text-sm">{payout.payout_number}</span></TD>
                  <TD><StatusBadge domain="payout" value={payout.status} /></TD>
                  <TD numeric><Money value={payout.amount} /></TD>
                  <TD className="text-ink-muted">{formatDateTime(payout.requested_at)}</TD>
                  <TD>
                    {payout.status === "requested" ? (
                      <Button
                        size="xs"
                        variant="ghost"
                        loading={cancel.isPending}
                        onClick={() =>
                          cancel.mutate(payout.payout_number, {
                            onSuccess: () => toast.success("Payout dibatalkan"),
                            onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                          })
                        }
                      >
                        Batalkan
                      </Button>
                    ) : null}
                  </TD>
                </TR>
              ))}
            </TBody>
          </TableWrap>
        )}
      </Card>
    </div>
  );
}
