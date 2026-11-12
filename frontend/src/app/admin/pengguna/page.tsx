"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input, Select } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useAdminUsers, useUpdateUser } from "@/features/admin";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import type { AdminUser } from "@/lib/api/domain";

const roles = ["customer", "seller", "admin", "super_admin"];

const statuses = [
  { value: "", label: "Semua status" },
  { value: "active", label: "Aktif" },
  { value: "inactive", label: "Nonaktif" },
  { value: "suspended", label: "Ditangguhkan" },
  { value: "banned", label: "Diblokir" },
];

interface FormState {
  user: AdminUser;
  roles: string[];
  status: string;
}

export default function AdminUsersPage() {
  const { user: me } = useAuth();
  const [filters, setFilters] = useUrlFilters({ q: "", role: "", status: "", page: "1" });
  const users = useAdminUsers({
    q: filters.q || undefined,
    role: filters.role || undefined,
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });
  const update = useUpdateUser();
  const toast = useToast();

  const [form, setForm] = useState<FormState | null>(null);

  const items = users.data?.items ?? [];
  const pagination = users.data?.pagination;

  const openEdit = (user: AdminUser) =>
    setForm({ user, roles: [...user.roles], status: user.status });

  const toggleRole = (role: string) => {
    if (!form) return;

    const next = form.roles.includes(role)
      ? form.roles.filter((item) => item !== role)
      : [...form.roles, role];

    setForm({ ...form, roles: next });
  };

  const submit = () => {
    if (!form || form.roles.length === 0) return;

    update.mutate(
      { id: form.user.id, roles: form.roles, status: form.status },
      {
        onSuccess: () => {
          toast.success("Pengguna diperbarui");
          setForm(null);
        },
        onError: (caught) => toast.error("Gagal", errorMessage(caught)),
      },
    );
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Pengguna"
        description="Kelola role dan status akun. Hanya super admin yang dapat mengakses halaman ini."
        eyebrow="Admin"
      />

      <div className="flex flex-wrap gap-2">
        <Input
          aria-label="Cari pengguna"
          placeholder="Cari nama atau email…"
          className="h-9 w-64"
          value={filters.q}
          onChange={(event) => setFilters({ q: event.target.value, page: "1" })}
        />
        <Select
          aria-label="Role"
          className="h-9 w-40"
          value={filters.role}
          onChange={(event) => setFilters({ role: event.target.value, page: "1" })}
        >
          <option value="">Semua role</option>
          {roles.map((role) => (
            <option key={role} value={role}>
              {role}
            </option>
          ))}
        </Select>
        <Select
          aria-label="Status"
          className="h-9 w-40"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>
              {status.label}
            </option>
          ))}
        </Select>
      </div>

      {users.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Tidak ada pengguna" description="Tidak ada data pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Pengguna</TH>
                <TH>Role</TH>
                <TH>Status</TH>
                <TH>Toko</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((user) => (
                <TR key={user.id}>
                  <TD>
                    <p className="text-sm font-medium text-ink">{user.name}</p>
                    <p className="text-xs text-ink-subtle">{user.email}</p>
                  </TD>
                  <TD>
                    <div className="flex flex-wrap gap-1">
                      {user.roles.length === 0 ? (
                        <span className="text-xs text-ink-subtle">—</span>
                      ) : (
                        user.roles.map((role) => (
                          <span
                            key={role}
                            className="inline-flex h-5 items-center rounded-[6px] bg-canvas px-2 text-xs text-ink-muted"
                          >
                            {role}
                          </span>
                        ))
                      )}
                    </div>
                  </TD>
                  <TD>
                    <StatusBadge domain="user" value={user.status} />
                  </TD>
                  <TD className="text-ink-muted">{user.seller?.store_name ?? "-"}</TD>
                  <TD>
                    <div className="flex justify-end">
                      <Button
                        size="xs"
                        variant="ghost"
                        disabled={me?.id === user.id}
                        onClick={() => openEdit(user)}
                      >
                        Ubah
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
        title="Ubah pengguna"
        description={form ? `${form.user.name} · ${form.user.email}` : undefined}
        footer={
          <>
            <Button variant="ghost" onClick={() => setForm(null)}>
              Batal
            </Button>
            <Button loading={update.isPending} disabled={!form || form.roles.length === 0} onClick={submit}>
              Simpan
            </Button>
          </>
        }
      >
        {form ? (
          <div className="space-y-4">
            <Field label="Role" hint="Minimal satu role.">
              <div className="grid grid-cols-2 gap-2">
                {roles.map((role) => (
                  <label
                    key={role}
                    className="flex cursor-pointer items-center gap-2 rounded-[8px] border border-line px-3 py-2 text-sm"
                  >
                    <input
                      type="checkbox"
                      className="size-4 accent-[#0875E2]"
                      checked={form.roles.includes(role)}
                      onChange={() => toggleRole(role)}
                    />
                    <span className="text-ink">{role}</span>
                  </label>
                ))}
              </div>
            </Field>
            <Field label="Status" htmlFor="status">
              <Select
                id="status"
                value={form.status}
                onChange={(event) => setForm({ ...form, status: event.target.value })}
              >
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="suspended">Ditangguhkan</option>
                <option value="banned">Diblokir</option>
              </Select>
            </Field>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}
