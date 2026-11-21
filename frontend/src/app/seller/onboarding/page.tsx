"use client";

import Link from "next/link";
import { useState } from "react";
import { Upload } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { RegionFields } from "@/components/region/RegionFields";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import {
  useSellerApplication,
  useSubmitSellerApplication,
  useUploadSellerDocument,
} from "@/features/seller";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";

const emptyForm = {
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

export default function OnboardingPage() {
  const { refresh } = useAuth();
  const application = useSellerApplication();
  const submit = useSubmitSellerApplication();
  const upload = useUploadSellerDocument();
  const toast = useToast();
  const [form, setForm] = useState(emptyForm);
  const [docType, setDocType] = useState("ktp");
  const [file, setFile] = useState<File | null>(null);

  if (application.isPending) {
    return <div className="space-y-4"><Skeleton className="h-28 w-full" /><Skeleton className="h-64 w-full" /></div>;
  }

  const seller = application.data;

  if (seller) {
    return (
      <div className="space-y-6">
        <PageHeader
          title="Aplikasi toko"
          description="Lengkapi dokumen agar toko bisa aktif."
          eyebrow="Onboarding"
          actions={<StatusBadge domain="seller" value={seller.status} />}
        />

        {seller.status === "rejected" && seller.rejection_reason ? (
          <Alert tone="danger" title="Aplikasi ditolak">{seller.rejection_reason}</Alert>
        ) : null}
        {seller.status === "under_review" ? (
          <Alert tone="info" title="Sedang ditinjau admin">
            Dokumen sudah diterima. Toko aktif setelah disetujui.
          </Alert>
        ) : null}
        {seller.status === "active" ? (
          <Alert tone="success" title="Toko aktif">
            Kamu bisa mengelola produk dan pesanan.{" "}
            <Link href="/seller" className="font-medium underline">Buka dashboard</Link>.
          </Alert>
        ) : null}

        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader title="Profil toko" />
            <CardBody className="space-y-2 text-sm">
              <Row label="Nama toko" value={seller.store_name} />
              <Row label="Slug" value={seller.slug} />
              <Row label="Status" value={seller.status} />
              {seller.kyc ? (
                <Row
                  label="Dokumen wajib"
                  value={seller.kyc.complete ? "Lengkap" : `Kurang: ${seller.kyc.missing.join(", ")}`}
                />
              ) : null}
            </CardBody>
          </Card>

          <Card>
            <CardHeader title="Dokumen" description="Unggah KTP (wajib). NPWP/NIB opsional." />
            <CardBody className="space-y-3">
              <ul className="space-y-2 text-sm">
                {(seller.documents ?? []).map((document) => (
                  <li key={document.id} className="flex items-center justify-between gap-3">
                    <span className="text-ink">{document.type.toUpperCase()}</span>
                    <StatusBadge domain="seller" value={document.status === "approved" ? "active" : "under_review"} />
                  </li>
                ))}
                {(seller.documents ?? []).length === 0 ? (
                  <li className="text-ink-muted">Belum ada dokumen.</li>
                ) : null}
              </ul>

              {seller.status !== "active" ? (
                <div className="space-y-3 border-t border-line pt-3">
                  <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Jenis dokumen" htmlFor="doc-type">
                      <Select id="doc-type" value={docType} onChange={(event) => setDocType(event.target.value)}>
                        <option value="ktp">KTP</option>
                        <option value="npwp">NPWP</option>
                        <option value="nib">NIB</option>
                      </Select>
                    </Field>
                    <Field label="File" htmlFor="doc-file" hint="JPG/PNG/PDF, maks 5 MB.">
                      <Input
                        id="doc-file"
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf"
                        onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                      />
                    </Field>
                  </div>
                  <Button
                    loading={upload.isPending}
                    disabled={!file}
                    onClick={() => {
                      if (!file) return;
                      upload.mutate(
                        { sellerId: seller.id, type: docType, file },
                        {
                          onSuccess: () => {
                            setFile(null);
                            toast.success("Dokumen diunggah");
                            void refresh();
                          },
                          onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                        },
                      );
                    }}
                  >
                    <Icon as={Upload} size="xs" />
                    Unggah dokumen
                  </Button>
                </div>
              ) : null}
            </CardBody>
          </Card>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Buka toko"
        description="Isi data toko dan alamat asal pengiriman."
        eyebrow="Onboarding"
      />

      <Card>
        <CardBody>
          <form
            className="space-y-4"
            onSubmit={(event) => {
              event.preventDefault();
              submit.mutate(
                {
                  store_name: form.store_name,
                  description: form.description || null,
                  address: {
                    contact_name: form.contact_name,
                    phone: form.phone,
                    address_line: form.address_line,
                    province: form.province,
                    city: form.city,
                    district: form.district || null,
                    village: form.village || null,
                    postal_code: form.postal_code,
                  },
                },
                {
                  onSuccess: () => {
                    toast.success("Aplikasi toko dikirim");
                    void application.refetch();
                    void refresh();
                  },
                  onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                },
              );
            }}
          >
            <Field label="Nama toko" htmlFor="store_name" required>
              <Input id="store_name" value={form.store_name} onChange={(event) => setForm({ ...form, store_name: event.target.value })} required />
            </Field>
            <Field label="Deskripsi" htmlFor="description">
              <Textarea id="description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
            </Field>

            <div className="border-t border-line pt-4">
              <p className="mb-3 text-sm font-medium text-ink">Alamat asal pengiriman</p>
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Nama kontak" htmlFor="contact_name" required>
                  <Input id="contact_name" value={form.contact_name} onChange={(event) => setForm({ ...form, contact_name: event.target.value })} required />
                </Field>
                <Field label="Telepon" htmlFor="phone" required>
                  <Input id="phone" value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} required />
                </Field>
              </div>
              <div className="mt-3">
                <Field label="Alamat" htmlFor="address_line" required>
                  <Input id="address_line" value={form.address_line} onChange={(event) => setForm({ ...form, address_line: event.target.value })} required />
                </Field>
              </div>
              <div className="mt-3">
                <RegionFields
                  value={form}
                  onChange={(region) =>
                    setForm({
                      ...form,
                      province: region.province,
                      city: region.city,
                      district: region.district ?? "",
                      village: region.village ?? "",
                      postal_code: region.postal_code,
                    })
                  }
                />
              </div>
            </div>

            <Button type="submit" loading={submit.isPending}>Kirim aplikasi</Button>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-4">
      <span className="text-ink-muted">{label}</span>
      <span className="text-ink">{value}</span>
    </div>
  );
}
