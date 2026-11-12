"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Badge } from "@/components/ui/Badge";
import { Field, Input, Select } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { Modal } from "@/components/ui/Modal";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import {
  useAdminVouchers,
  useCreateVoucher,
  useUpdateVoucher,
  type VoucherPayload,
} from "@/features/promotion";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import type { Voucher } from "@/lib/api/domain";
import { formatDateTime } from "@/lib/format";

interface FormState {
  code: string;
  name: string;
  type: "fixed" | "percent";
  value: string;
  min_purchase: string;
  max_discount: string;
  scope: "platform" | "seller";
  seller_id: string;
  usage_limit: string;
  usage_limit_per_user: string;
  starts_at: string;
  expires_at: string;
  is_active: boolean;
}

const emptyForm: FormState = {
  code: "",
  name: "",
  type: "fixed",
  value: "",
  min_purchase: "0",
  max_discount: "",
  scope: "platform",
  seller_id: "",
  usage_limit: "",
  usage_limit_per_user: "",
  starts_at: new Date().toISOString().slice(0, 16),
  expires_at: "",
  is_active: true,
};

function toForm(voucher: Voucher): FormState {
  return {
    code: voucher.code,
    name: voucher.name,
    type: voucher.type,
    value: String(voucher.value),
    min_purchase: String(voucher.min_purchase),
    max_discount: voucher.max_discount !== null ? String(voucher.max_discount) : "",
    scope: voucher.scope,
    seller_id: voucher.seller_id !== null ? String(voucher.seller_id) : "",
    usage_limit: voucher.usage_limit !== null ? String(voucher.usage_limit) : "",
    usage_limit_per_user:
      voucher.usage_limit_per_user !== null ? String(voucher.usage_limit_per_user) : "",
    starts_at: voucher.starts_at ? voucher.starts_at.slice(0, 16) : "",
    expires_at: voucher.expires_at ? voucher.expires_at.slice(0, 16) : "",
    is_active: voucher.is_active,
  };
}

export default function AdminVoucherPage() {
  const [filters, setFilters] = useUrlFilters({ scope: "", q: "", page: "1" });
  const vouchers = useAdminVouchers({
    scope: filters.scope || undefined,
    q: filters.q || undefined,
    page: filters.page,
    per_page: 20,
  });
  const create = useCreateVoucher();
  const update = useUpdateVoucher();
  const toast = useToast();

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Voucher | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);

  const items = vouchers.data?.items ?? [];
  const pagination = vouchers.data?.pagination;

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setOpen(true);
  };

  const openEdit = (voucher: Voucher) => {
    setEditing(voucher);
    setForm(toForm(voucher));
    setOpen(true);
  };

  const payload = (): VoucherPayload => ({
    code: form.code.trim().toUpperCase(),
    name: form.name.trim(),
    type: form.type,
    value: Number(form.value || 0),
    min_purchase: Number(form.min_purchase || 0),
    max_discount: form.max_discount === "" ? null : Number(form.max_discount),
    scope: form.scope,
    seller_id: form.scope === "seller" ? Number(form.seller_id || 0) : null,
    usage_limit: form.usage_limit === "" ? null : Number(form.usage_limit),
    usage_limit_per_user:
      form.usage_limit_per_user === "" ? null : Number(form.usage_limit_per_user),
    starts_at: form.starts_at,
    expires_at: form.expires_at === "" ? null : form.expires_at,
    is_active: form.is_active,
  });

  const submit = () => {
    const data = payload();

    if (editing) {
      update.mutate(
        { id: editing.id, payload: data },
        {
          onSuccess: () => {
            setOpen(false);
            toast.success("Voucher diperbarui");
          },
          onError: (caught) => toast.error("Gagal", errorMessage(caught)),
        },
      );

      return;
    }

    create.mutate(data, {
      onSuccess: () => {
        setOpen(false);
        toast.success("Voucher dibuat");
      },
      onError: (caught) => toast.error("Gagal", errorMessage(caught)),
    });
  };

  const valid =
    form.code.trim() !== "" &&
    form.name.trim() !== "" &&
    Number(form.value) > 0 &&
    (form.scope !== "seller" || Number(form.seller_id) > 0);

  return (
    <div className="space-y-5">
      <PageHeader
        title="Voucher"
        description="Kelola kode diskon platform dan toko."
        eyebrow="Admin"
        actions={
          <Button size="sm" onClick={openCreate}>
            <Icon as={Plus} size="xs" />
            Voucher baru
          </Button>
        }
      />

      <div className="flex flex-wrap gap-2">
        <Select
          aria-label="Scope"
          className="h-9 w-44"
          value={filters.scope}
          onChange={(event) => setFilters({ scope: event.target.value, page: "1" })}
        >
          <option value="">Semua scope</option>
          <option value="platform">Platform</option>
          <option value="seller">Toko</option>
        </Select>
        <Input
          aria-label="Cari voucher"
          className="h-9 w-56"
          value={filters.q}
          placeholder="Cari kode atau nama"
          onChange={(event) => setFilters({ q: event.target.value, page: "1" })}
        />
      </div>

      {vouchers.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Belum ada voucher" description="Buat voucher untuk mulai memberi diskon." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Kode</TH>
                <TH>Tipe</TH>
                <TH numeric>Min. belanja</TH>
                <TH>Scope</TH>
                <TH numeric>Terpakai</TH>
                <TH>Periode</TH>
                <TH>Status</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((voucher) => (
                <TR key={voucher.id}>
                  <TD>
                    <span className="num font-medium text-ink">{voucher.code}</span>
                    <span className="block text-xs text-ink-subtle">{voucher.name}</span>
                  </TD>
                  <TD className="text-ink-muted">
                    {voucher.type === "percent" ? (
                      <>{(voucher.value / 100).toFixed(2)}%</>
                    ) : (
                      <Money value={voucher.value} />
                    )}
                  </TD>
                  <TD numeric>
                    <Money value={voucher.min_purchase} />
                  </TD>
                  <TD>
                    <Badge tone={voucher.scope === "platform" ? "brand" : "neutral"}>
                      {voucher.scope === "platform" ? "Platform" : `Toko #${voucher.seller_id}`}
                    </Badge>
                  </TD>
                  <TD numeric>
                    {voucher.used_count}
                    {voucher.usage_limit !== null ? ` / ${voucher.usage_limit}` : ""}
                  </TD>
                  <TD className="text-xs text-ink-muted">
                    {formatDateTime(voucher.starts_at)}
                    <br />
                    {voucher.expires_at ? formatDateTime(voucher.expires_at) : "Tanpa batas"}
                  </TD>
                  <TD>
                    <Badge tone={voucher.is_active ? "success" : "neutral"}>
                      {voucher.is_active ? "Aktif" : "Nonaktif"}
                    </Badge>
                  </TD>
                  <TD>
                    <Button size="xs" variant="ghost" onClick={() => openEdit(voucher)}>
                      Ubah
                    </Button>
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
        open={open}
        onOpenChange={setOpen}
        title={editing ? "Ubah voucher" : "Voucher baru"}
        description="Voucher platform tidak memotong komisi penjual; voucher toko memotong komisi penjual."
        footer={
          <>
            <Button variant="ghost" onClick={() => setOpen(false)}>
              Batal
            </Button>
            <Button
              loading={create.isPending || update.isPending}
              disabled={!valid}
              onClick={submit}
            >
              {editing ? "Simpan" : "Buat"}
            </Button>
          </>
        }
      >
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Kode" htmlFor="voucher-code" required>
            <Input
              id="voucher-code"
              className="uppercase"
              value={form.code}
              onChange={(event) => setForm({ ...form, code: event.target.value })}
            />
          </Field>
          <Field label="Nama" htmlFor="voucher-name" required>
            <Input
              id="voucher-name"
              value={form.name}
              onChange={(event) => setForm({ ...form, name: event.target.value })}
            />
          </Field>
          <Field label="Tipe" htmlFor="voucher-type">
            <Select
              id="voucher-type"
              value={form.type}
              onChange={(event) =>
                setForm({ ...form, type: event.target.value as FormState["type"] })
              }
            >
              <option value="fixed">Nominal (Rp)</option>
              <option value="percent">Persen</option>
            </Select>
          </Field>
          <Field
            label={form.type === "percent" ? "Nilai (basis point, 10000 = 100%)" : "Nilai (Rp)"}
            htmlFor="voucher-value"
            required
          >
            <Input
              id="voucher-value"
              inputMode="numeric"
              value={form.value}
              onChange={(event) => setForm({ ...form, value: event.target.value })}
            />
          </Field>
          <Field label="Min. belanja (Rp)" htmlFor="voucher-min">
            <Input
              id="voucher-min"
              inputMode="numeric"
              value={form.min_purchase}
              onChange={(event) => setForm({ ...form, min_purchase: event.target.value })}
            />
          </Field>
          <Field label="Maks. diskon (Rp, opsional)" htmlFor="voucher-max">
            <Input
              id="voucher-max"
              inputMode="numeric"
              value={form.max_discount}
              onChange={(event) => setForm({ ...form, max_discount: event.target.value })}
            />
          </Field>
          <Field label="Scope" htmlFor="voucher-scope">
            <Select
              id="voucher-scope"
              value={form.scope}
              onChange={(event) =>
                setForm({ ...form, scope: event.target.value as FormState["scope"] })
              }
            >
              <option value="platform">Platform</option>
              <option value="seller">Toko</option>
            </Select>
          </Field>
          {form.scope === "seller" ? (
            <Field label="Seller ID" htmlFor="voucher-seller" required>
              <Input
                id="voucher-seller"
                inputMode="numeric"
                value={form.seller_id}
                onChange={(event) => setForm({ ...form, seller_id: event.target.value })}
              />
            </Field>
          ) : null}
          <Field label="Kuota total (opsional)" htmlFor="voucher-limit">
            <Input
              id="voucher-limit"
              inputMode="numeric"
              value={form.usage_limit}
              onChange={(event) => setForm({ ...form, usage_limit: event.target.value })}
            />
          </Field>
          <Field label="Kuota per pengguna (opsional)" htmlFor="voucher-limit-user">
            <Input
              id="voucher-limit-user"
              inputMode="numeric"
              value={form.usage_limit_per_user}
              onChange={(event) =>
                setForm({ ...form, usage_limit_per_user: event.target.value })
              }
            />
          </Field>
          <Field label="Mulai" htmlFor="voucher-start" required>
            <Input
              id="voucher-start"
              type="datetime-local"
              value={form.starts_at}
              onChange={(event) => setForm({ ...form, starts_at: event.target.value })}
            />
          </Field>
          <Field label="Berakhir (opsional)" htmlFor="voucher-end">
            <Input
              id="voucher-end"
              type="datetime-local"
              value={form.expires_at}
              onChange={(event) => setForm({ ...form, expires_at: event.target.value })}
            />
          </Field>
          <label className="flex items-center gap-2 text-sm text-ink sm:col-span-2">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
            />
            Aktif
          </label>
        </div>
      </Modal>
    </div>
  );
}
