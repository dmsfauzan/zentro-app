"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { Store } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Money } from "@/components/ui/Money";
import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { Skeleton, SkeletonText } from "@/components/ui/Skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/Tabs";
import { ProductCard } from "@/components/shop/ProductCard";
import { ProductReviews } from "@/components/shop/ProductReviews";
import { RatingStars } from "@/components/shop/RatingStars";
import { EmptyState } from "@/components/ui/EmptyState";
import { Alert } from "@/components/ui/Alert";
import { useProduct, useProducts } from "@/features/catalog";
import { useAddToCart } from "@/features/cart";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { cn } from "@/lib/cn";
import { formatNumber } from "@/lib/format";

export default function ProductDetailPage() {
  const { slug } = useParams<{ slug: string }>();
  const router = useRouter();
  const { token } = useAuth();
  const toast = useToast();
  const product = useProduct(slug);
  const related = useProducts({ sort: "popular", per_page: 4 });
  const addToCart = useAddToCart();

  const [variantId, setVariantId] = useState<number | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [activeImage, setActiveImage] = useState(0);

  const variants = useMemo(() => product.data?.variants ?? [], [product.data]);

  const variant = useMemo(
    () =>
      variants.find((item) => item.id === variantId) ??
      variants.find(
        (item) => item.status === "active" && (item.inventory?.available_stock ?? 0) > 0,
      ) ??
      variants[0] ??
      null,
    [variants, variantId],
  );

  const stock = variant?.inventory?.available_stock ?? 0;
  const canBuy = Boolean(variant) && variant?.status === "active" && stock > 0;

  const images = product.data?.images?.length
    ? product.data.images
    : product.data?.primary_image
      ? [product.data.primary_image]
      : [];

  const relatedItems = (related.data?.items ?? []).filter(
    (item) => item.slug !== product.data?.slug,
  );

  const requireAuth = (): boolean => {
    if (!token) {
      router.push(`/login?next=${encodeURIComponent(`/produk/${slug}`)}`);
      return false;
    }

    return true;
  };

  const add = async (then?: () => void) => {
    if (!requireAuth() || !variant) return;

    try {
      await addToCart.mutateAsync({ variant_id: variant.id, quantity });
      toast.success("Ditambahkan ke keranjang");
      then?.();
    } catch (caught) {
      toast.error("Gagal menambahkan", errorMessage(caught));
    }
  };

  if (product.isPending) {
    return (
      <div className="container-page grid gap-8 py-8 lg:grid-cols-2">
        <Skeleton className="aspect-[4/5] w-full rounded-[20px]" />
        <div className="space-y-4">
          <Skeleton className="h-8 w-2/3" />
          <Skeleton className="h-8 w-1/3" />
          <SkeletonText lines={4} />
        </div>
      </div>
    );
  }

  if (product.isError || !product.data) {
    return (
      <div className="container-page py-14">
        <EmptyState
          title="Produk tidak ditemukan"
          description="Produk mungkin sudah tidak tayang."
          action={
            <Link href="/produk" className="text-sm font-medium text-brand hover:underline">
              Lihat produk lain
            </Link>
          }
        />
      </div>
    );
  }

  const data = product.data;

  return (
    <div className="container-page py-6 lg:py-8">
      <nav
        className="mb-6 flex items-center gap-1.5 text-[13px] text-ink-subtle"
        aria-label="Breadcrumb"
      >
        <Link href="/" className="hover:text-brand">Beranda</Link>
        <span aria-hidden="true">/</span>
        <Link href="/produk" className="hover:text-brand">Produk</Link>
        {data.category ? (
          <>
            <span aria-hidden="true">/</span>
            <Link href={`/kategori/${data.category.slug}`} className="hover:text-brand">
              {data.category.name}
            </Link>
          </>
        ) : null}
      </nav>

      <div className="grid gap-8 lg:grid-cols-2 lg:gap-12">
        <div
          className={cn(
            "grid gap-4",
            images.length > 1 && "sm:grid-cols-[88px_1fr]",
          )}
        >
          {images.length > 1 ? (
            <div className="order-2 flex gap-3 overflow-x-auto sm:order-1 sm:flex-col sm:overflow-visible">
              {images.slice(0, 5).map((image, index) => (
                <button
                  key={index}
                  type="button"
                  onClick={() => setActiveImage(index)}
                  aria-pressed={activeImage === index}
                  className={cn(
                    "size-20 shrink-0 overflow-hidden rounded-[12px] bg-paper",
                    activeImage === index ? "ring-2 ring-brand" : "ring-1 ring-line",
                  )}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={image.url}
                    alt={image.alt_text ?? `${data.name} ${index + 1}`}
                    className="size-full object-cover"
                    loading="lazy"
                  />
                </button>
              ))}
            </div>
          ) : null}

          <div className="order-1 overflow-hidden rounded-[20px] bg-paper sm:order-2">
            {images[activeImage] ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={images[activeImage].url}
                alt={images[activeImage].alt_text ?? data.name}
                className="aspect-[4/5] w-full object-cover"
              />
            ) : (
              <div className="flex aspect-[4/5] items-center justify-center font-display text-5xl text-ink-subtle">
                {data.name.slice(0, 1).toUpperCase()}
              </div>
            )}
          </div>
        </div>

        <div>
          <div className="lg:sticky lg:top-32">
            <h1 className="font-display text-3xl uppercase leading-[1.05] tracking-[0.01em] text-ink sm:text-4xl">
              {data.name}
            </h1>

            {data.rating_count > 0 ? (
              <div className="mt-3 flex items-center gap-2 text-sm text-ink-muted">
                <RatingStars value={data.rating_avg} size="sm" />
                <span className="num font-medium text-ink">{data.rating_avg.toFixed(1)}</span>
                <span className="text-ink-subtle">({data.rating_count} ulasan)</span>
              </div>
            ) : null}

            <div className="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
              <Money
                value={variant?.price ?? data.min_price}
                className="text-3xl font-semibold text-ink"
              />
              <span className="num text-sm text-ink-subtle">
                {formatNumber(data.sold_count)} terjual
              </span>
            </div>

            {data.seller ? (
              <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-ink-muted">
                <Icon as={Store} size="xs" className="text-ink-subtle" />
                Dijual oleh{" "}
                <span className="font-medium text-ink">{data.seller.store_name}</span>
              </p>
            ) : null}

            <div className="mt-6 border-t border-line pt-6">
              <p className="text-xs font-medium uppercase tracking-[0.14em] text-ink-subtle">
                Varian
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                {(data.variants ?? []).map((item) => {
                  const available = (item.inventory?.available_stock ?? 0) > 0;
                  const active = item.id === (variant?.id ?? -1);

                  return (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => {
                        setVariantId(item.id);
                        setQuantity(1);
                      }}
                      aria-pressed={active}
                      className={cn(
                        "rounded-full border px-4 py-2 text-sm",
                        active
                          ? "border-brand bg-brand text-white"
                          : "border-line bg-paper text-ink hover:border-line-strong",
                        !available && "opacity-50",
                      )}
                    >
                      {item.variant_name ?? item.sku}
                    </button>
                  );
                })}
              </div>

              <div className="mt-6 flex items-center justify-between gap-4">
                <span className="text-sm text-ink-muted">Jumlah</span>
                <QuantityStepper
                  value={quantity}
                  onChange={setQuantity}
                  max={Math.max(1, stock)}
                  disabled={!canBuy}
                />
              </div>

              <div className="mt-6 space-y-2">
                <Button
                  className="w-full"
                  disabled={!canBuy}
                  loading={addToCart.isPending}
                  onClick={() => void add()}
                >
                  Tambah ke keranjang
                </Button>
                <Button
                  variant="secondary"
                  className="w-full"
                  disabled={!canBuy}
                  onClick={() => void add(() => router.push("/keranjang"))}
                >
                  Beli sekarang
                </Button>
              </div>

              {!canBuy && variant ? (
                <div className="mt-3">
                  <Alert tone="warning" title="Stok habis">
                    Varian ini sedang tidak tersedia. Pilih varian lain.
                  </Alert>
                </div>
              ) : null}

              <dl className="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">SKU</dt>
                  <dd className="num text-ink">{variant?.sku ?? data.slug}</dd>
                </div>
                {variant?.weight_gram ? (
                  <div className="flex justify-between gap-4">
                    <dt className="text-ink-muted">Berat</dt>
                    <dd className="num text-ink">{variant.weight_gram} gram</dd>
                  </div>
                ) : null}
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">Pembayaran</dt>
                  <dd className="text-ink">Terverifikasi</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <section className="mt-12">
        <Tabs defaultValue="detail">
          <TabsList>
            <TabsTrigger value="detail">Detail produk</TabsTrigger>
            <TabsTrigger value="reviews">Ulasan</TabsTrigger>
          </TabsList>
          <TabsContent value="detail">
            <p className="max-w-[70ch] whitespace-pre-line text-sm leading-7 text-ink-muted">
              {data.description?.trim() || "Penjual belum menambahkan deskripsi."}
            </p>
          </TabsContent>
          <TabsContent value="reviews">
            <ProductReviews slug={data.slug} />
          </TabsContent>
        </Tabs>
      </section>

      {relatedItems.length > 0 ? (
        <section className="mt-14 border-t border-line pt-10">
          <h2 className="mb-7 font-display text-3xl uppercase leading-none tracking-[0.01em] text-ink">
            Kamu mungkin suka
          </h2>
          <div className="grid grid-cols-2 gap-x-4 gap-y-9 md:grid-cols-3 lg:grid-cols-4">
            {relatedItems.map((item) => (
              <ProductCard key={item.id} product={item} />
            ))}
          </div>
        </section>
      ) : null}
    </div>
  );
}
