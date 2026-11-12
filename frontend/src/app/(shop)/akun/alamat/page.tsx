"use client";

import { useState } from "react";
import { MapPin, Pencil, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card, CardBody } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { Skeleton } from "@/components/ui/Skeleton";
import { Alert } from "@/components/ui/Alert";
import {
  useAddresses,
  useCreateAddress,
  useDeleteAddress,
  useUpdateAddress,
  type AddressPayload,
} from "@/features/account";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import type { UserAddress } from "@/lib/api/domain";

const emptyForm: AddressPayload = {
  label: "",
  recipient_name: "",
  phone: "",
  address_line: "",
  province: "",
  city: "",
  district: "",
  village: "",
  postal_code: "",
  is_default: false,
};

export default function AddressesPage() {
  const addresses = useAddresses();
  const create = useCreateAddress();
  const update = useUpdateAddress();
  const remove = useDeleteAddress();
  const toast = useToast();

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<UserAddress | null>(null);
  const [form, setForm] = useState<AddressPayload>(emptyForm);

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setOpen(true);
  };

  const openEdit = (address: UserAddress) => {
    setEditing(address);
    setForm({
      label: address.label ?? "",
      recipient_name: address.recipient_name,
      phone: address.phone,
      address_line: address.address_line,
      province: address.province,
      city: address.city,
      district: address.district ?? "",
      village: address.village ?? "",
      postal_code: address.postal_code,
      is_default: address.is_default,
    });
    setOpen(true);
  };

  const submit = () => {
    const payload = {
      ...form,
      label: form.label || null,
      district: form.district || null,
      village: form.village || null,
    } as AddressPayload;

    const onSuccess = () => {
      setOpen(false);
      toast.success(editing ? "Alamat diperbarui" : "Alamat ditambahkan");
    };

    if (editing) {
      update.mutate(
        { id: editing.id, payload },
        { onSuccess, onError: (caught) => toast.error("Gagal", errorMessage(caught)) },
      );
    } else {
      create.mutate(payload, {
        onSuccess,
        onError: (caught) => toast.error("Gagal", errorMessage(caught)),
      });
    }
  };

  return (
    <div className="container-page py-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h1 className="text-2xl font-semibold tracking-[-0.01em] text-ink">Alamat</h1>
        <Button onClick={openCreate}>
          <Icon as={Plus} size="xs" />
          Tambah alamat
        </Button>
      </div>

      {addresses.isPending ? (
        <div className="mt-5 space-y-3">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-24 w-full" />
        </div>
      ) : addresses.isError ? (
        <div className="mt-5">
          <Alert tone="warning" title="Masuk untuk mengelola alamat">
            Alamat tersimpan di akunmu.
          </Alert>
        </div>
      ) : (addresses.data?.length ?? 0) === 0 ? (
        <div className="mt-5">
          <EmptyState
            icon={MapPin}
            title="Belum ada alamat"
            description="Tambahkan alamat agar bisa checkout."
            action={<Button onClick={openCreate}>Tambah alamat</Button>}
          />
        </div>
      ) : (
        <div className="mt-5 grid gap-3 sm:grid-cols-2">
          {addresses.data?.map((address) => (
            <Card key={address.id}>
              <CardBody className="space-y-2">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="flex items-center gap-2 text-sm font-medium text-ink">
                      <Icon as={MapPin} size="xs" className="text-ink-subtle" />
                      {address.recipient_name}
                      {address.is_default ? (
                        <span className="rounded-[4px] bg-brand-soft px-1.5 py-0.5 text-[10px] font-medium text-brand">
                          Utama
                        </span>
                      ) : null}
                    </p>
                    <p className="mt-1 text-xs text-ink-muted">{address.phone}</p>
                  </div>
                  <div className="flex gap-1">
                    <button
                      type="button"
                      onClick={() => openEdit(address)}
                      className="rounded-[6px] p-1.5 text-ink-subtle hover:bg-paper hover:text-ink"
                      aria-label="Ubah alamat"
                    >
                      <Icon as={Pencil} size="xs" />
                    </button>
                    <button
                      type="button"
                      onClick={() =>
                        remove.mutate(address.id, {
                          onSuccess: () => toast.success("Alamat dihapus"),
                          onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                        })
                      }
                      className="rounded-[6px] p-1.5 text-ink-subtle hover:bg-danger-soft hover:text-danger"
                      aria-label="Hapus alamat"
                    >
                      <Icon as={Trash2} size="xs" />
                    </button>
                  </div>
                </div>
                <p className="text-sm leading-6 text-ink-muted">
                  {address.address_line}, {address.city}, {address.province}{" "}
                  {address.postal_code}
                </p>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      <Modal
        open={open}
        onOpenChange={setOpen}
        title={editing ? "Ubah alamat" : "Tambah alamat"}
        footer={
          <>
            <Button variant="ghost" onClick={() => setOpen(false)}>Batal</Button>
            <Button
              loading={create.isPending || update.isPending}
              onClick={submit}
              disabled={!form.recipient_name || !form.phone || !form.address_line || !form.province || !form.city || !form.postal_code}
            >
              Simpan
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Label" htmlFor="label">
              <Input id="label" value={form.label ?? ""} onChange={(event) => setForm({ ...form, label: event.target.value })} placeholder="Rumah" />
            </Field>
            <Field label="Nama penerima" htmlFor="recipient_name" required>
              <Input id="recipient_name" value={form.recipient_name} onChange={(event) => setForm({ ...form, recipient_name: event.target.value })} />
            </Field>
          </div>
          <Field label="Telepon" htmlFor="phone" required>
            <Input id="phone" value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} />
          </Field>
          <Field label="Alamat" htmlFor="address_line" required>
            <Input id="address_line" value={form.address_line} onChange={(event) => setForm({ ...form, address_line: event.target.value })} />
          </Field>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Provinsi" htmlFor="province" required>
              <Input id="province" value={form.province} onChange={(event) => setForm({ ...form, province: event.target.value })} />
            </Field>
            <Field label="Kota" htmlFor="city" required>
              <Input id="city" value={form.city} onChange={(event) => setForm({ ...form, city: event.target.value })} />
            </Field>
          </div>
          <div className="grid gap-3 sm:grid-cols-3">
            <Field label="Kecamatan" htmlFor="district">
              <Input id="district" value={form.district ?? ""} onChange={(event) => setForm({ ...form, district: event.target.value })} />
            </Field>
            <Field label="Kelurahan" htmlFor="village">
              <Input id="village" value={form.village ?? ""} onChange={(event) => setForm({ ...form, village: event.target.value })} />
            </Field>
            <Field label="Kode pos" htmlFor="postal_code" required>
              <Input id="postal_code" value={form.postal_code} onChange={(event) => setForm({ ...form, postal_code: event.target.value })} />
            </Field>
          </div>
          <label className="flex items-center gap-2 text-sm text-ink">
            <input
              type="checkbox"
              checked={Boolean(form.is_default)}
              onChange={(event) => setForm({ ...form, is_default: event.target.checked })}
              className="size-4 accent-[#0875E2]"
            />
            Jadikan alamat utama
          </label>
        </div>
      </Modal>
    </div>
  );
}
