"use client";

import { useState } from "react";
import { Download, Search } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import {
  downloadSellerDocument,
  useAdminSellers,
  useApproveSeller,
  useReactivateSeller,
  useRejectSeller,
  useSuspendSeller,
} from "@/features/admin";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";
import type { SellerProfile } from "@/lib/api/domain";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "pending", label: "Menunggu dokumen" },
  { value: "under_review", label: "Ditinjau" },
  { value: "active", label: "Aktif" },
  { value: "rejected", label: "Ditolak" },
  { value: "suspended", label: "Ditangguhkan" },
];

type Action = "reject" | "suspend" | null;

export default function AdminSellersPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", q: "", page: "1" });
  const sellers = useAdminSellers({
    status: filters.status || undefined,
    q: filters.q || undefined,
    page: filters.page,
    per_page: 20,
  });
  const approve = useApproveSeller();
  const reactivate = useReactivateSeller();
  const reject = useRejectSeller();
  const suspend = useSuspendSeller();
  const { token } = useAuth();
  const toast = useToast();

  const [modal, setModal] = useState<{ action: Action; seller: SellerProfile } | null>(null);
  const [reason, setReason] = useState("");

  const items = sellers.data?.items ?? [];
  const pagination = sellers.data?.pagination;

  const run = (mutation: ReturnType<typeof useApproveSeller>, seller: SellerProfile, why?: string) =>
    mutation.mutate(
      { id: seller.id, reason: why },
      {
        onSuccess: () => {
          toast.success("Aksi berhasil");
          setModal(null);
          setReason("");
        },
        onError: (caught) => toast.error("Gagal", errorMessage(caught)),
      },
    );

  return (
    <div className="space-y-5">
      <PageHeader title="Penjual" description="Tinjau aplikasi toko dan kelola status." eyebrow="Admin" />

      <div className="flex flex-wrap gap-2">
        <Select
          aria-label="Status"
          className="h-9 w-48"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>{status.label}</option>
          ))}
        </Select>
        <form
          className="flex items-center gap-2"
          onSubmit={(event) => {
            event.preventDefault();
            const data = new FormData(event.currentTarget);
            setFilters({ q: String(data.get("q") ?? ""), page: "1" });
          }}
        >
          <Input name="q" defaultValue={filters.q} placeholder="Cari nama toko" className="h-9 w-56" />
          <Button type="submit" size="sm" variant="secondary">
            <Icon as={Search} size="xs" />
            Cari
          </Button>
        </form>
      </div>

      {sellers.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Tidak ada penjual" description="Tidak ada data pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Toko</TH>
                <TH>Pemilik</TH>
                <TH>Status</TH>
                <TH>Diajukan</TH>
                <TH>Dokumen</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((seller) => (
                <TR key={seller.id}>
                  <TD>
                    <p className="text-sm font-medium text-ink">{seller.store_name}</p>
                    <p className="text-xs text-ink-subtle">{seller.slug}</p>
                  </TD>
                  <TD>
                    <p className="text-sm text-ink">{seller.owner?.name ?? "-"}</p>
                    <p className="text-xs text-ink-subtle">{seller.owner?.email ?? ""}</p>
                  </TD>
                  <TD><StatusBadge domain="seller" value={seller.status} /></TD>
                  <TD className="text-ink-muted">{formatDateTime(seller.created_at)}</TD>
                  <TD>
                    <div className="flex flex-wrap gap-2">
                      {(seller.documents ?? []).map((document) => (
                        <button
                          key={document.id}
                          type="button"
                          onClick={() =>
                            void downloadSellerDocument(
                              document.id,
                              token,
                              `${seller.slug}-${document.type}.bin`,
                            ).catch((caught) => toast.error("Gagal", errorMessage(caught)))
                          }
                          className="inline-flex items-center gap-1 text-xs text-brand hover:underline"
                        >
                          <Icon as={Download} size="xs" />
                          {document.type.toUpperCase()}
                        </button>
                      ))}
                      {(seller.documents ?? []).length === 0 ? (
                        <span className="text-xs text-ink-subtle">-</span>
                      ) : null}
                    </div>
                  </TD>
                  <TD>
                    <div className="flex flex-wrap justify-end gap-1.5">
                      {seller.status === "under_review" ? (
                        <Button size="xs" loading={approve.isPending} onClick={() => run(approve, seller)}>
                          Setujui
                        </Button>
                      ) : null}
                      {seller.status === "suspended" ? (
                        <Button size="xs" loading={reactivate.isPending} onClick={() => run(reactivate, seller)}>
                          Aktifkan
                        </Button>
                      ) : null}
                      {["pending", "under_review"].includes(seller.status) ? (
                        <Button size="xs" variant="ghost" onClick={() => { setModal({ action: "reject", seller }); setReason(""); }}>
                          Tolak
                        </Button>
                      ) : null}
                      {seller.status === "active" ? (
                        <Button size="xs" variant="ghost" onClick={() => { setModal({ action: "suspend", seller }); setReason(""); }}>
                          Tangguhkan
                        </Button>
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
        open={modal !== null}
        onOpenChange={(open) => { if (!open) setModal(null); }}
        title={modal?.action === "reject" ? "Tolak aplikasi" : "Tangguhkan toko"}
        footer={
          <>
            <Button variant="ghost" onClick={() => setModal(null)}>Batal</Button>
            <Button
              variant="danger"
              loading={reject.isPending || suspend.isPending}
              disabled={!reason.trim()}
              onClick={() => {
                if (!modal) return;
                if (modal.action === "reject") run(reject, modal.seller, reason);
                if (modal.action === "suspend") run(suspend, modal.seller, reason);
              }}
            >
              Simpan
            </Button>
          </>
        }
      >
        <Field label="Alasan" htmlFor="reason" required>
          <Textarea id="reason" value={reason} onChange={(event) => setReason(event.target.value)} />
        </Field>
        <div className="mt-3">
          <Alert tone="warning" title="Tindakan tercatat">
            Alasan disimpan pada audit log dan ditampilkan ke penjual.
          </Alert>
        </div>
      </Modal>
    </div>
  );
}
