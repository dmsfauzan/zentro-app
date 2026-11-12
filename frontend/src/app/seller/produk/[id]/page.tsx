"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { ImagePlus, Plus, Trash2 } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { Money } from "@/components/ui/Money";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useCategories } from "@/features/catalog";
import {
  useCreateVariant,
  useDeleteProductImage,
  useSellerProduct,
  useUpdateInventory,
  useUpdateProduct,
  useUpdateVariant,
  useUploadProductImage,
} from "@/features/seller";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import type { ProductVariant } from "@/lib/api/domain";

export default function SellerProductDetailPage() {
  const { id } = useParams<{ id: string }>();
  const productId = Number(id);
  const product = useSellerProduct(productId);
  const categories = useCategories();
  const updateProduct = useUpdateProduct(productId);
  const uploadImage = useUploadProductImage(productId);
  const deleteImage = useDeleteProductImage(productId);
  const toast = useToast();

  const [form, setForm] = useState({
    name: "",
    category_id: "",
    description: "",
    weight_gram: "",
    status: "draft",
  });

  const [variantModal, setVariantModal] = useState<
    { mode: "create" } | { mode: "edit"; variant: ProductVariant } | null
  >(null);
  const [stockModal, setStockModal] = useState<ProductVariant | null>(null);

  useEffect(() => {
    if (!product.data) return;

    // Sinkronisasi form saat data produk selesai dimuat (sumber eksternal).
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setForm({
      name: product.data.name,
      category_id: String(product.data.category_id ?? ""),
      description: product.data.description ?? "",
      weight_gram: product.data.weight_gram ? String(product.data.weight_gram) : "",
      status: product.data.status ?? "draft",
    });
  }, [product.data]);

  if (product.isPending) {
    return <div className="space-y-4"><Skeleton className="h-24 w-full" /><Skeleton className="h-64 w-full" /></div>;
  }

  if (!product.data) {
    return <Alert tone="danger" title="Produk tidak ditemukan">Periksa kembali daftar produk.</Alert>;
  }

  const data = product.data;

  return (
    <div className="space-y-5">
      <PageHeader
        title={data.name}
        description={`Slug: ${data.slug}`}
        eyebrow="Katalog"
        actions={
          <div className="flex items-center gap-2">
            <StatusBadge domain="product" value={data.status} />
            <Link href={`/produk/${data.slug}`} className="text-sm text-brand hover:underline">Lihat publik</Link>
          </div>
        }
      />

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader title="Informasi" />
          <CardBody>
            <form
              className="space-y-4"
              onSubmit={(event) => {
                event.preventDefault();
                updateProduct.mutate(
                  {
                    name: form.name,
                    category_id: Number(form.category_id),
                    description: form.description || null,
                    weight_gram: form.weight_gram ? Number(form.weight_gram) : null,
                    status: form.status as "draft" | "active" | "inactive" | "archived",
                  },
                  {
                    onSuccess: () => toast.success("Produk diperbarui"),
                    onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                  },
                );
              }}
            >
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Nama" htmlFor="name" required>
                  <Input id="name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required />
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
                <Field label="Status" htmlFor="status">
                  <Select id="status" value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}>
                    <option value="draft">Draf</option>
                    <option value="active">Tayang</option>
                    <option value="inactive">Nonaktif</option>
                    <option value="archived">Diarsipkan</option>
                  </Select>
                </Field>
              </div>
              <Button type="submit" loading={updateProduct.isPending}>Simpan</Button>
            </form>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Gambar" description="Urutan pertama menjadi gambar utama." />
          <CardBody className="space-y-3">
            <div className="grid grid-cols-3 gap-2">
              {(data.images ?? []).map((image) => (
                <div key={image.id} className="group relative aspect-square overflow-hidden rounded-[6px] border border-line">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={image.url} alt={image.alt_text ?? ""} className="size-full object-cover" loading="lazy" />
                  <button
                    type="button"
                    onClick={() => deleteImage.mutate(image.id, {
                      onSuccess: () => toast.success("Gambar dihapus"),
                      onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                    })}
                    className="absolute right-1 top-1 rounded bg-canvas/90 p-1 text-danger opacity-0 transition-opacity group-hover:opacity-100"
                    aria-label="Hapus gambar"
                  >
                    <Icon as={Trash2} size="xs" />
                  </button>
                </div>
              ))}
            </div>

            <label className="flex h-10 cursor-pointer items-center justify-center gap-2 rounded-[8px] border border-dashed border-line-strong text-sm text-ink-muted hover:border-brand/40 hover:text-brand">
              <Icon as={ImagePlus} size="sm" />
              {uploadImage.isPending ? "Mengunggah…" : "Unggah gambar"}
              <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                className="sr-only"
                onChange={(event) => {
                  const file = event.target.files?.[0];
                  if (!file) return;
                  uploadImage.mutate(file, {
                    onSuccess: () => toast.success("Gambar diunggah"),
                    onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                  });
                  event.target.value = "";
                }}
              />
            </label>
          </CardBody>
        </Card>
      </div>

      <Card>
        <CardHeader
          title="Varian & stok"
          actions={
            <Button size="sm" variant="secondary" onClick={() => setVariantModal({ mode: "create" })}>
              <Icon as={Plus} size="xs" />
              Varian
            </Button>
          }
        />
        <TableWrap>
          <THead>
            <TR>
              <TH>SKU</TH>
              <TH>Varian</TH>
              <TH numeric>Harga</TH>
              <TH numeric>Stok tersedia</TH>
              <TH>Status</TH>
              <TH />
            </TR>
          </THead>
          <TBody>
            {(data.variants ?? []).map((variant) => (
              <TR key={variant.id}>
                <TD><span className="num text-xs text-ink-muted">{variant.sku}</span></TD>
                <TD>{variant.variant_name ?? "-"}</TD>
                <TD numeric><Money value={variant.price} /></TD>
                <TD numeric>{variant.inventory?.available_stock ?? 0}</TD>
                <TD><StatusBadge domain="product" value={variant.status} /></TD>
                <TD>
                  <div className="flex justify-end gap-2">
                    <Button size="xs" variant="ghost" onClick={() => setVariantModal({ mode: "edit", variant })}>Ubah</Button>
                    <Button size="xs" variant="secondary" onClick={() => setStockModal(variant)}>Stok</Button>
                  </div>
                </TD>
              </TR>
            ))}
          </TBody>
        </TableWrap>
      </Card>

      {variantModal ? (
        <VariantModal
          productId={productId}
          state={variantModal}
          onClose={() => setVariantModal(null)}
        />
      ) : null}

      {stockModal ? (
        <StockModal
          productId={productId}
          variant={stockModal}
          onClose={() => setStockModal(null)}
        />
      ) : null}
    </div>
  );
}

function VariantModal({
  productId,
  state,
  onClose,
}: {
  productId: number;
  state: { mode: "create" } | { mode: "edit"; variant: ProductVariant };
  onClose: () => void;
}) {
  const create = useCreateVariant(productId);
  const update = useUpdateVariant(productId);
  const toast = useToast();

  const editing = state.mode === "edit" ? state.variant : null;
  const [sku, setSku] = useState(editing?.sku ?? "");
  const [name, setName] = useState(editing?.variant_name ?? "");
  const [price, setPrice] = useState(editing ? String(editing.price) : "");
  const [status, setStatus] = useState(editing?.status ?? "active");
  const [stock, setStock] = useState("0");

  const submit = () => {
    if (editing) {
      update.mutate(
        { id: editing.id, payload: { sku, variant_name: name, price: Number(price), status } },
        {
          onSuccess: () => { toast.success("Varian diperbarui"); onClose(); },
          onError: (caught) => toast.error("Gagal", errorMessage(caught)),
        },
      );
    } else {
      create.mutate(
        { sku, variant_name: name, price: Number(price), stock: Number(stock) },
        {
          onSuccess: () => { toast.success("Varian ditambahkan"); onClose(); },
          onError: (caught) => toast.error("Gagal", errorMessage(caught)),
        },
      );
    }
  };

  return (
    <Modal
      open
      onOpenChange={(open) => { if (!open) onClose(); }}
      title={editing ? "Ubah varian" : "Tambah varian"}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button loading={create.isPending || update.isPending} onClick={submit}>Simpan</Button>
        </>
      }
    >
      <div className="space-y-3">
        <Field label="SKU" htmlFor="v-sku" required>
          <Input id="v-sku" value={sku} onChange={(event) => setSku(event.target.value)} />
        </Field>
        <Field label="Nama varian" htmlFor="v-name" required>
          <Input id="v-name" value={name} onChange={(event) => setName(event.target.value)} />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Harga (Rp)" htmlFor="v-price" required>
            <Input id="v-price" inputMode="numeric" value={price} onChange={(event) => setPrice(event.target.value)} />
          </Field>
          {editing ? (
            <Field label="Status" htmlFor="v-status">
              <Select id="v-status" value={status} onChange={(event) => setStatus(event.target.value)}>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
              </Select>
            </Field>
          ) : (
            <Field label="Stok awal" htmlFor="v-stock">
              <Input id="v-stock" inputMode="numeric" value={stock} onChange={(event) => setStock(event.target.value)} />
            </Field>
          )}
        </div>
      </div>
    </Modal>
  );
}

function StockModal({
  productId,
  variant,
  onClose,
}: {
  productId: number;
  variant: ProductVariant;
  onClose: () => void;
}) {
  const update = useUpdateInventory(productId);
  const toast = useToast();
  const [mode, setMode] = useState<"set" | "adjust">("set");
  const [quantity, setQuantity] = useState(String(variant.inventory?.stock ?? 0));
  const [threshold, setThreshold] = useState(String(variant.inventory?.low_stock_threshold ?? 5));
  const [note, setNote] = useState("");

  return (
    <Modal
      open
      onOpenChange={(open) => { if (!open) onClose(); }}
      title={`Stok · ${variant.sku}`}
      description={`Stok saat ini ${variant.inventory?.stock ?? 0}.`}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button
            loading={update.isPending}
            onClick={() =>
              update.mutate(
                {
                  variantId: variant.id,
                  payload: {
                    mode,
                    quantity: Number(quantity),
                    low_stock_threshold: Number(threshold),
                    note: note || undefined,
                  },
                },
                {
                  onSuccess: () => { toast.success("Stok diperbarui"); onClose(); },
                  onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                },
              )
            }
          >
            Simpan
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        <Field label="Mode" htmlFor="stock-mode">
          <Select id="stock-mode" value={mode} onChange={(event) => setMode(event.target.value as "set" | "adjust")}>
            <option value="set">Set jumlah stok</option>
            <option value="adjust">Tambah/kurangi (boleh negatif)</option>
          </Select>
        </Field>
        <Field label="Jumlah" htmlFor="stock-qty" required>
          <Input id="stock-qty" inputMode="numeric" value={quantity} onChange={(event) => setQuantity(event.target.value)} />
        </Field>
        <Field label="Ambang stok tipis" htmlFor="stock-threshold">
          <Input id="stock-threshold" inputMode="numeric" value={threshold} onChange={(event) => setThreshold(event.target.value)} />
        </Field>
        <Field label="Catatan" htmlFor="stock-note" hint="Tercatat pada riwayat pergerakan stok.">
          <Input id="stock-note" value={note} onChange={(event) => setNote(event.target.value)} />
        </Field>
      </div>
    </Modal>
  );
}
