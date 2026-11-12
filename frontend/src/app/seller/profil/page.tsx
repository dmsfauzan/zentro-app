"use client";

import { useEffect, useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Field, Input, Textarea } from "@/components/ui/Input";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { useSellerProfile, useUpdateSellerProfile } from "@/features/seller";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";

interface FormState {
  store_name: string;
  description: string;
  contact_name: string;
  phone: string;
  address_line: string;
  province: string;
  city: string;
  district: string;
  village: string;
  postal_code: string;
}

const emptyForm: FormState = {
  store_name: "",
  description: "",
  contact_name: "",
  phone: "",
  address_line: "",
  province: "",
  city: "",
  district: "",
  village: "",
  postal_code: "",
};

export default function SellerProfilePage() {
  const profile = useSellerProfile();
  const update = useUpdateSellerProfile();
  const toast = useToast();
  const [form, setForm] = useState<FormState>(emptyForm);

  const seller = profile.data;

  useEffect(() => {
    if (!seller) return;

    const origin = seller.addresses?.find((address) => address.type === "origin");

    // Form diisi dari data server (sumber eksternal) saat profil termuat.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setForm({
      store_name: seller.store_name,
      description: seller.description ?? "",
      contact_name: origin?.contact_name ?? "",
      phone: origin?.phone ?? "",
      address_line: origin?.address_line ?? "",
      province: origin?.province ?? "",
      city: origin?.city ?? "",
      district: origin?.district ?? "",
      village: origin?.village ?? "",
      postal_code: origin?.postal_code ?? "",
    });
  }, [seller]);

  if (profile.isPending) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-28 w-full" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (!seller) {
    return (
      <Alert tone="warning" title="Profil toko tidak tersedia">
        {errorMessage(profile.error)}
      </Alert>
    );
  }

  const submit = () =>
    update.mutate(
      {
        store_name: form.store_name.trim(),
        description: form.description.trim() || null,
        address: {
          contact_name: form.contact_name.trim(),
          phone: form.phone.trim(),
          address_line: form.address_line.trim(),
          province: form.province.trim(),
          city: form.city.trim(),
          district: form.district.trim() || null,
          village: form.village.trim() || null,
          postal_code: form.postal_code.trim(),
        },
      },
      {
        onSuccess: () => toast.success("Profil toko diperbarui"),
        onError: (caught) => toast.error("Gagal", errorMessage(caught)),
      },
    );

  return (
    <div className="space-y-6">
      <PageHeader
        title="Profil toko"
        description="Perbarui nama, deskripsi, dan alamat asal pengiriman."
        eyebrow="Penjual"
        actions={<StatusBadge domain="seller" value={seller.status} />}
      />

      <Card>
        <CardHeader title="Informasi toko" />
        <CardBody className="space-y-4">
          <Field label="Nama toko" htmlFor="store_name" required>
            <Input
              id="store_name"
              value={form.store_name}
              onChange={(event) => setForm({ ...form, store_name: event.target.value })}
            />
          </Field>
          <Field label="Deskripsi" htmlFor="description">
            <Textarea
              id="description"
              value={form.description}
              onChange={(event) => setForm({ ...form, description: event.target.value })}
            />
          </Field>
          <div className="flex justify-between gap-4 rounded-[8px] bg-canvas px-3 py-2 text-sm">
            <span className="text-ink-muted">Slug</span>
            <span className="font-mono text-ink-subtle">{seller.slug}</span>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardHeader title="Alamat asal pengiriman" description="Dipakai untuk perhitungan ongkir." />
        <CardBody className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Nama kontak" htmlFor="contact_name" required>
              <Input
                id="contact_name"
                value={form.contact_name}
                onChange={(event) => setForm({ ...form, contact_name: event.target.value })}
              />
            </Field>
            <Field label="Telepon" htmlFor="phone" required>
              <Input
                id="phone"
                value={form.phone}
                onChange={(event) => setForm({ ...form, phone: event.target.value })}
              />
            </Field>
          </div>
          <Field label="Alamat" htmlFor="address_line" required>
            <Input
              id="address_line"
              value={form.address_line}
              onChange={(event) => setForm({ ...form, address_line: event.target.value })}
            />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Provinsi" htmlFor="province" required>
              <Input
                id="province"
                value={form.province}
                onChange={(event) => setForm({ ...form, province: event.target.value })}
              />
            </Field>
            <Field label="Kota" htmlFor="city" required>
              <Input
                id="city"
                value={form.city}
                onChange={(event) => setForm({ ...form, city: event.target.value })}
              />
            </Field>
          </div>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Kecamatan" htmlFor="district">
              <Input
                id="district"
                value={form.district}
                onChange={(event) => setForm({ ...form, district: event.target.value })}
              />
            </Field>
            <Field label="Kelurahan" htmlFor="village">
              <Input
                id="village"
                value={form.village}
                onChange={(event) => setForm({ ...form, village: event.target.value })}
              />
            </Field>
            <Field label="Kode pos" htmlFor="postal_code" required>
              <Input
                id="postal_code"
                value={form.postal_code}
                onChange={(event) => setForm({ ...form, postal_code: event.target.value })}
              />
            </Field>
          </div>

          <Button loading={update.isPending} onClick={submit}>
            Simpan perubahan
          </Button>
        </CardBody>
      </Card>
    </div>
  );
}
