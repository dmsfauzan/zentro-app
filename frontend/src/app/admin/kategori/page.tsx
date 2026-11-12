"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { Badge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import {
  useAdminCategories,
  useCreateCategory,
  useDeleteCategory,
  useUpdateCategory,
} from "@/features/admin";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import type { AdminCategory } from "@/lib/api/domain";

interface FormState {
  id: number | null;
  name: string;
  parent_id: string;
  description: string;
  sort_order: string;
  is_active: boolean;
}

const emptyForm: FormState = {
  id: null,
  name: "",
  parent_id: "",
  description: "",
  sort_order: "0",
  is_active: true,
};

export default function AdminCategoriesPage() {
  const [filters, setFilters] = useUrlFilters({ q: "", page: "1" });
  const categories = useAdminCategories({
    q: filters.q || undefined,
    page: filters.page,
    per_page: 50,
  });
  const parentOptions = useAdminCategories({ per_page: 200 });
  const create = useCreateCategory();
  const update = useUpdateCategory();
  const remove = useDeleteCategory();
  const toast = useToast();

  const [form, setForm] = useState<FormState | null>(null);

  const items = categories.data?.items ?? [];
  const pagination = categories.data?.pagination;
  const roots = useMemo(
    () =>
      (parentOptions.data?.items ?? []).filter(
        (category) => category.parent_id === null && category.id !== form?.id,
      ),
    [parentOptions.data, form?.id],
  );

  const saving = create.isPending || update.isPending;

  const openCreate = () => setForm({ ...emptyForm });

  const openEdit = (category: AdminCategory) =>
    setForm({
      id: category.id,
      name: category.name,
      parent_id: category.parent_id ? String(category.parent_id) : "",
      description: category.description ?? "",
      sort_order: String(category.sort_order),
      is_active: category.is_active,
    });

  const submit = () => {
    if (!form || !form.name.trim()) return;

    const payload = {
      name: form.name.trim(),
      parent_id: form.parent_id ? Number(form.parent_id) : null,
      description: form.description.trim() || null,
      sort_order: Number(form.sort_order) || 0,
      is_active: form.is_active,
    };

    const options = {
      onSuccess: () => {
        toast.success(form.id ? "Kategori diperbarui" : "Kategori dibuat");
        setForm(null);
      },
      onError: (caught: unknown) => toast.error("Gagal", errorMessage(caught)),
    };

    if (form.id) update.mutate({ id: form.id, ...payload }, options);
    else create.mutate(payload, options);
  };

  const confirmDelete = (category: AdminCategory) => {
    if (!window.confirm(`Hapus kategori "${category.name}"?`)) return;

    remove.mutate(category.id, {
      onSuccess: () => toast.success("Kategori dihapus"),
      onError: (caught) => toast.error("Gagal menghapus", errorMessage(caught)),
    });
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Kategori"
        description="Kelola struktur kategori katalog dua level."
        eyebrow="Admin"
        actions={<Button onClick={openCreate}>Tambah kategori</Button>}
      />

      <div>
        <Input
          aria-label="Cari kategori"
          placeholder="Cari kategori…"
          className="h-9 w-64"
          value={filters.q}
          onChange={(event) => setFilters({ q: event.target.value, page: "1" })}
        />
      </div>

      {categories.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Belum ada kategori" description="Tambahkan kategori pertama." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Kategori</TH>
                <TH>Induk</TH>
                <TH numeric>Produk</TH>
                <TH numeric>Urutan</TH>
                <TH>Status</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((category) => (
                <TR key={category.id}>
                  <TD>
                    <p className="text-sm font-medium text-ink">
                      {category.parent_id ? "— " : ""}
                      {category.name}
                    </p>
                    <p className="text-xs text-ink-subtle">{category.slug}</p>
                  </TD>
                  <TD className="text-ink-muted">{category.parent_name ?? "-"}</TD>
                  <TD numeric>{category.products_count}</TD>
                  <TD numeric>{category.sort_order}</TD>
                  <TD>
                    <Badge tone={category.is_active ? "success" : "neutral"}>
                      {category.is_active ? "Aktif" : "Nonaktif"}
                    </Badge>
                  </TD>
                  <TD>
                    <div className="flex flex-wrap justify-end gap-1.5">
                      <Button size="xs" variant="ghost" onClick={() => openEdit(category)}>
                        Ubah
                      </Button>
                      <Button size="xs" variant="ghost" onClick={() => confirmDelete(category)}>
                        Hapus
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
        open={form !== null}
        onOpenChange={(open) => {
          if (!open) setForm(null);
        }}
        title={form?.id ? "Ubah kategori" : "Tambah kategori"}
        footer={
          <>
            <Button variant="ghost" onClick={() => setForm(null)}>
              Batal
            </Button>
            <Button loading={saving} disabled={!form?.name.trim()} onClick={submit}>
              Simpan
            </Button>
          </>
        }
      >
        {form ? (
          <div className="space-y-4">
            <Field label="Nama" htmlFor="name" required>
              <Input
                id="name"
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </Field>
            <Field label="Kategori induk" htmlFor="parent_id" hint="Kosongkan untuk kategori utama.">
              <Select
                id="parent_id"
                value={form.parent_id}
                onChange={(event) => setForm({ ...form, parent_id: event.target.value })}
              >
                <option value="">— Kategori utama —</option>
                {roots.map((root) => (
                  <option key={root.id} value={root.id}>
                    {root.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Deskripsi" htmlFor="description">
              <Textarea
                id="description"
                value={form.description}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
              />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Urutan" htmlFor="sort_order">
                <Input
                  id="sort_order"
                  type="number"
                  min={0}
                  value={form.sort_order}
                  onChange={(event) => setForm({ ...form, sort_order: event.target.value })}
                />
              </Field>
              <Field label="Status" htmlFor="is_active">
                <Select
                  id="is_active"
                  value={form.is_active ? "1" : "0"}
                  onChange={(event) => setForm({ ...form, is_active: event.target.value === "1" })}
                >
                  <option value="1">Aktif</option>
                  <option value="0">Nonaktif</option>
                </Select>
              </Field>
            </div>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}
