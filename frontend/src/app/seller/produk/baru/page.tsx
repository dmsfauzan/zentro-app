"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { PageHeader } from "@/components/ui/PageHeader";
import { useCategories } from "@/features/catalog";
import { useCreateProduct } from "@/features/seller";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";

export default function NewProductPage() {
  const categories = useCategories();
  const create = useCreateProduct();
  const router = useRouter();
  const toast = useToast();

  const [form, setForm] = useState({
    name: "",
    category_id: "",
    description: "",
    weight_gram: "",
    sku: "",
    variant_name: "Default",
    price: "",
    stock: "",
  });
  const [error, setError] = useState<string | null>(null);

  return (
    <div className="space-y-5">
      <PageHeader title="Produk baru" description="Produk selalu dibuat sebagai draf. Tayangkan setelah lengkap." eyebrow="Katalog" />

      <Card>
        <CardHeader title="Informasi produk" />
        <CardBody>
          <form
            className="space-y-4"
            onSubmit={(event) => {
              event.preventDefault();
              setError(null);

              create.mutate(
                {
                  name: form.name,
                  category_id: Number(form.category_id),
                  description: form.description || null,
                  weight_gram: form.weight_gram ? Number(form.weight_gram) : null,
                  variants: [
                    {
                      sku: form.sku,
                      variant_name: form.variant_name,
                      price: Number(form.price),
                      weight_gram: form.weight_gram ? Number(form.weight_gram) : null,
                      stock: form.stock ? Number(form.stock) : 0,
                    },
                  ],
                },
                {
                  onSuccess: (product) => {
                    toast.success("Produk dibuat");
                    router.push(`/seller/produk/${product.id}`);
                  },
                  onError: (caught) => {
                    setError(errorMessage(caught));
                    toast.error("Gagal", errorMessage(caught));
                  },
                },
              );
            }}
          >
            {error ? <Alert tone="danger" title="Gagal membuat produk">{error}</Alert> : null}

            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Nama produk" htmlFor="name" required>
                <Input id="name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required minLength={3} />
              </Field>
              <Field label="Kategori" htmlFor="category_id" required>
                <Select id="category_id" value={form.category_id} onChange={(event) => setForm({ ...form, category_id: event.target.value })} required>
                  <option value="">Pilih kategori</option>
                  {(categories.data ?? []).map((category) => (
                    <option key={category.id} value={category.id}>{category.name}</option>
                  ))}
                </Select>
              </Field>
            </div>

            <Field label="Deskripsi" htmlFor="description">
              <Textarea id="description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
            </Field>

            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Berat (gram)" htmlFor="weight_gram">
                <Input id="weight_gram" inputMode="numeric" value={form.weight_gram} onChange={(event) => setForm({ ...form, weight_gram: event.target.value })} />
              </Field>
            </div>

            <div className="border-t border-line pt-4">
              <p className="mb-3 text-sm font-medium text-ink">Varian pertama</p>
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="SKU" htmlFor="sku" required>
                  <Input id="sku" value={form.sku} onChange={(event) => setForm({ ...form, sku: event.target.value })} required />
                </Field>
                <Field label="Nama varian" htmlFor="variant_name" required>
                  <Input id="variant_name" value={form.variant_name} onChange={(event) => setForm({ ...form, variant_name: event.target.value })} required />
                </Field>
                <Field label="Harga (Rp)" htmlFor="price" required>
                  <Input id="price" inputMode="numeric" value={form.price} onChange={(event) => setForm({ ...form, price: event.target.value })} required />
                </Field>
                <Field label="Stok awal" htmlFor="stock">
                  <Input id="stock" inputMode="numeric" value={form.stock} onChange={(event) => setForm({ ...form, stock: event.target.value })} />
                </Field>
              </div>
            </div>

            <Button type="submit" loading={create.isPending}>Simpan produk</Button>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
