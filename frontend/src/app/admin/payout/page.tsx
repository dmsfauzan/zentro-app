"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { Money } from "@/components/ui/Money";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useAdminPayouts, useProcessPayout } from "@/features/admin";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";
import type { Payout } from "@/lib/api/domain";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "requested", label: "Diajukan" },
  { value: "approved", label: "Disetujui" },
  { value: "processing", label: "Diproses" },
  { value: "paid", label: "Dibayar" },
  { value: "failed", label: "Gagal" },
  { value: "rejected", label: "Ditolak" },
  { value: "cancelled", label: "Dibatalkan" },
];

export default function AdminPayoutsPage() {
  const [filters, setFilters] = useUrlFilters({ status: "requested", page: "1" });
  const payouts = useAdminPayouts({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });
  const process = useProcessPayout();
  const toast = useToast();

  const [target, setTarget] = useState<{
    payout: Payout;
    action: "approve" | "start" | "mark_paid" | "fail" | "retry" | "reject";
  } | null>(null);
  const [reference, setReference] = useState("");
  const [reason, setReason] = useState("");

  const items = payouts.data?.items ?? [];
  const pagination = payouts.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader title="Payout" description="Setujui dan konfirmasi pencairan dana penjual." eyebrow="Admin" />

      <div>
        <Select
          aria-label="Status"
          className="h-9 w-44"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>{status.label}</option>
          ))}
        </Select>
      </div>

      {payouts.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Tidak ada payout" description="Tidak ada data pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Nomor</TH>
                <TH>Rekening</TH>
                <TH numeric>Jumlah</TH>
                <TH>Status</TH>
                <TH>Diajukan</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((payout) => (
                <TR key={payout.id}>
                  <TD><span className="num text-sm">{payout.payout_number}</span></TD>
                  <TD>
                    <p className="text-sm text-ink">{payout.bank.bank_name}</p>
                    <p className="num text-xs text-ink-subtle">{payout.bank.account_number}</p>
                  </TD>
                  <TD numeric><Money value={payout.amount} /></TD>
                  <TD><StatusBadge domain="payout" value={payout.status} /></TD>
                  <TD className="text-ink-muted">{formatDateTime(payout.requested_at)}</TD>
                  <TD>
                    <div className="flex flex-wrap justify-end gap-1.5">
                      {payout.status === "requested" ? (
                        <>
                          <Button size="xs" onClick={() => setTarget({ payout, action: "approve" })}>Setujui</Button>
                          <Button size="xs" variant="ghost" onClick={() => { setTarget({ payout, action: "reject" }); setReason(""); }}>Tolak</Button>
                        </>
                      ) : null}
                      {payout.status === "approved" ? (
                        <>
                          <Button size="xs" variant="secondary" onClick={() => setTarget({ payout, action: "start" })}>Mulai proses</Button>
                          <Button size="xs" variant="secondary" onClick={() => { setTarget({ payout, action: "mark_paid" }); setReference(""); }}>Tandai dibayar</Button>
                          <Button size="xs" variant="ghost" onClick={() => { setTarget({ payout, action: "reject" }); setReason(""); }}>Tolak</Button>
                        </>
                      ) : null}
                      {payout.status === "processing" ? (
                        <>
                          <Button size="xs" variant="secondary" onClick={() => { setTarget({ payout, action: "mark_paid" }); setReference(""); }}>Tandai dibayar</Button>
                          <Button size="xs" variant="ghost" onClick={() => { setTarget({ payout, action: "fail" }); setReason(""); }}>Gagal</Button>
                        </>
                      ) : null}
                      {payout.status === "failed" ? (
                        <Button size="xs" onClick={() => setTarget({ payout, action: "retry" })}>Coba lagi</Button>
                      ) : null}
                    </div>
                  </TD>
                </TR>
              ))}
            </TBody>
          </TableWrap>
        </Card>
      )}

      {pagination ? (
        <Pagination
          page={pagination.page}
          lastPage={pagination.last_page}
          onChange={(page) => setFilters({ page: String(page) })}
        />
      ) : null}

      <Modal
        open={target !== null}
        onOpenChange={(open) => { if (!open) setTarget(null); }}
        title={
          target?.action === "approve"
            ? "Setujui payout"
            : target?.action === "start"
              ? "Mulai proses payout"
              : target?.action === "retry"
                ? "Coba lagi payout"
                : target?.action === "fail"
                  ? "Tandai payout gagal"
                  : target?.action === "reject"
                    ? "Tolak payout"
                    : "Tandai payout dibayar"
        }
        description={target ? `${target.payout.payout_number} · Rp ${target.payout.amount.toLocaleString("id-ID")}` : undefined}
        footer={
          <>
            <Button variant="ghost" onClick={() => setTarget(null)}>Batal</Button>
            <Button
              variant={target?.action === "reject" || target?.action === "fail" ? "danger" : "primary"}
              loading={process.isPending}
              disabled={(target?.action === "reject" || target?.action === "fail") && !reason.trim()}
              onClick={() => {
                if (!target) return;
                process.mutate(
                  {
                    id: target.payout.id,
                    action: target.action,
                    providerReference: target.action === "mark_paid" ? reference || undefined : undefined,
                    reason: target.action === "reject" || target.action === "fail" ? reason : undefined,
                  },
                  {
                    onSuccess: () => {
                      setTarget(null);
                      toast.success("Payout diproses");
                    },
                    onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                  },
                );
              }}
            >
              Lanjutkan
            </Button>
          </>
        }
      >
        {target?.action === "mark_paid" ? (
          <Field label="Referensi transfer" htmlFor="reference" hint="Nomor transaksi bank/disbursement (opsional).">
            <Input id="reference" value={reference} onChange={(event) => setReference(event.target.value)} />
          </Field>
        ) : target?.action === "reject" || target?.action === "fail" ? (
          <Field label="Alasan" htmlFor="reason" required>
            <Textarea id="reason" value={reason} onChange={(event) => setReason(event.target.value)} />
          </Field>
        ) : target?.action === "start" || target?.action === "retry" ? (
          <p className="text-sm text-ink-muted">
            Dana akan dialokasikan dari saldo tersedia dan dikunci sampai transfer selesai.
          </p>
        ) : (
          <p className="text-sm text-ink-muted">
            Pastikan dana payout sudah direservasi. Aksi tercatat pada audit log.
          </p>
        )}
      </Modal>
    </div>
  );
}
