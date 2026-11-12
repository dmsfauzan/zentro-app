"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Select, Textarea } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { Money } from "@/components/ui/Money";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useAdminProducts, useModerateProduct } from "@/features/admin";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import type { ProductSummary } from "@/lib/api/domain";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "active", label: "Tayang" },
  { value: "inactive", label: "Nonaktif" },
  { value: "rejected", label: "Ditolak" },
  { value: "draft", label: "Draf" },
];

export default function AdminProductsPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", page: "1" });
  const products = useAdminProducts({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });
  const moderate = useModerateProduct();
  const toast = useToast();

  const [modal, setModal] = useState<{ product: ProductSummary; status: "active" | "inactive" | "rejected" } | null>(null);
  const [reason, setReason] = useState("");

  const items = products.data?.items ?? [];
  const pagination = products.data?.pagination;

  const apply = (product: ProductSummary, status: "active" | "inactive" | "rejected", why?: string) =>
    moderate.mutate(
      { id: product.id, status, reason: why },
      {
        onSuccess: () => { toast.success("Status produk diperbarui"); setModal(null); setReason(""); },
        onError: (caught) => toast.error("Gagal", errorMessage(caught)),
      },
    );

  return (
    <div className="space-y-5">
      <PageHeader title="Moderasi produk" description="Tayangkan atau nonaktifkan produk yang melanggar." eyebrow="Admin" />

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

      {products.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Tidak ada produk" description="Tidak ada data pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Produk</TH>
                <TH>Penjual</TH>
                <TH>Status</TH>
                <TH numeric>Harga</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((product) => (
                <TR key={product.id}>
                  <TD>
                    <p className="line-clamp-1 text-sm font-medium text-ink">{product.name}</p>
                    <p className="text-xs text-ink-subtle">{product.category?.name ?? "-"}</p>
                  </TD>
                  <TD className="text-ink-muted">{product.seller?.store_name ?? "-"}</TD>
                  <TD><StatusBadge domain="product" value={product.status} /></TD>
                  <TD numeric><Money value={product.min_price} /></TD>
                  <TD>
                    <div className="flex flex-wrap justify-end gap-1.5">
                      {product.status !== "active" ? (
                        <Button size="xs" loading={moderate.isPending} onClick={() => apply(product, "active")}>
                          Tayangkan
                        </Button>
                      ) : (
                        <Button size="xs" variant="ghost" loading={moderate.isPending} onClick={() => apply(product, "inactive")}>
                          Nonaktifkan
                        </Button>
                      )}
                      <Button
                        size="xs"
                        variant="ghost"
                        onClick={() => { setModal({ product, status: "rejected" }); setReason(""); }}
                      >
                        Tolak
                      </Button>
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
        title="Tolak produk"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModal(null)}>Batal</Button>
            <Button
              variant="danger"
              loading={moderate.isPending}
              disabled={!reason.trim()}
              onClick={() => { if (modal) apply(modal.product, "rejected", reason); }}
            >
              Tolak produk
            </Button>
          </>
        }
      >
        <Field label="Alasan" htmlFor="reason" required>
          <Textarea id="reason" value={reason} onChange={(event) => setReason(event.target.value)} />
        </Field>
      </Modal>
    </div>
  );
}
