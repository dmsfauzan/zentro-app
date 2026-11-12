"use client";

import Link from "next/link";
import { ArrowRight } from "lucide-react";
import { ProductCard } from "@/components/shop/ProductCard";
import { CardGridSkeleton } from "@/components/ui/Skeleton";
import { ButtonLink } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { useCategories, useProducts } from "@/features/catalog";
import { formatNumber } from "@/lib/format";

export default function HomePage() {
  const categories = useCategories();
  const featured = useProducts({ featured: 1, per_page: 4 });
  const newest = useProducts({ per_page: 8, sort: "newest" });
  const popular = useProducts({ per_page: 4, sort: "popular" });

  const productCount = newest.data?.pagination.total ?? 0;
  const categoryList = categories.data ?? [];

  return (
    <div>
      <section className="border-b border-line bg-paper">
        <div className="container-page py-12 lg:py-16">
          <div className="grid gap-10 lg:grid-cols-2 lg:items-center">
            <div>
              <h1 className="font-display text-[44px] uppercase leading-[0.95] tracking-[0.01em] text-ink sm:text-[60px]">
                Belanja
                <br />
                dari banyak toko
              </h1>
              <p className="mt-5 max-w-lg text-base leading-7 text-ink-muted">
                Satu keranjang untuk semua penjual, satu pembayaran, dan pengiriman
                yang dikelola tiap toko.
              </p>

              <div className="mt-7 flex flex-wrap gap-3">
                <ButtonLink href="/produk" size="md">
                  Belanja sekarang
                </ButtonLink>
                <ButtonLink href="/seller/onboarding" variant="secondary" size="md">
                  Buka toko
                </ButtonLink>
              </div>

              <dl className="mt-10 grid max-w-xl grid-cols-3 gap-4 border-t border-line pt-6">
                <Stat value={productCount > 0 ? formatNumber(productCount) : "—"} label="Produk" />
                <Stat value={categoryList.length > 0 ? formatNumber(categoryList.length) : "—"} label="Kategori" />
                <Stat value="1" label="Checkout" />
              </dl>
            </div>

            <div className="grid grid-cols-2 gap-4">
              {featured.isPending
                ? Array.from({ length: 4 }).map((_, index) => (
                    <div key={index} className="aspect-[4/5] rounded-[16px] bg-canvas" />
                  ))
                : (featured.data?.items ?? []).slice(0, 4).map((product) => (
                    <ProductCard key={product.id} product={product} />
                  ))}
            </div>
          </div>
        </div>
      </section>

      <section className="bg-ink text-white">
        <div className="container-page flex items-center gap-6 overflow-x-auto py-4">
          <span className="shrink-0 text-xs font-medium uppercase tracking-[0.16em] text-white/50">
            Kategori
          </span>
          <Link href="/produk" className="shrink-0 text-sm font-medium hover:underline">
            Semua
          </Link>
          {categoryList.map((category) => (
            <Link
              key={category.id}
              href={`/kategori/${category.slug}`}
              className="shrink-0 text-sm text-white/70 hover:text-white"
            >
              {category.name}
            </Link>
          ))}
        </div>
      </section>

      <section className="container-page py-14">
        <SectionHead title="Produk terbaru" href="/produk?sort=newest" />
        {newest.isPending ? (
          <CardGridSkeleton count={8} />
        ) : (
          <div className="grid grid-cols-2 gap-x-4 gap-y-9 md:grid-cols-3 lg:grid-cols-4">
            {(newest.data?.items ?? []).map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </section>

      <section className="container-page pb-14">
        <SectionHead title="Terlaris" href="/produk?sort=popular" />
        {popular.isPending ? (
          <CardGridSkeleton count={4} />
        ) : (
          <div className="grid grid-cols-2 gap-x-4 gap-y-9 md:grid-cols-3 lg:grid-cols-4">
            {(popular.data?.items ?? []).map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </section>

      {categoryList.length > 0 ? (
        <section className="container-page pb-16">
          <SectionHead title="Jelajahi kategori" href="/produk" />
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
            {categoryList.slice(0, 5).map((category, index) => (
              <Link
                key={category.id}
                href={`/kategori/${category.slug}`}
                className={`group relative flex min-h-32 items-end overflow-hidden rounded-[20px] bg-paper p-5 ${
                  index === 0 ? "col-span-2" : ""
                }`}
              >
                <span className="font-display text-2xl uppercase tracking-[0.02em] text-ink group-hover:text-brand">
                  {category.name}
                </span>
                <Icon
                  as={ArrowRight}
                  size="sm"
                  className="absolute right-5 top-5 text-ink-subtle transition-transform group-hover:translate-x-1 group-hover:text-brand"
                />
              </Link>
            ))}
          </div>
        </section>
      ) : null}
    </div>
  );
}

function Stat({ value, label }: { value: string; label: string }) {
  return (
    <div>
      <dt className="font-display text-3xl leading-none text-ink">{value}</dt>
      <dd className="mt-1 text-xs uppercase tracking-[0.14em] text-ink-subtle">{label}</dd>
    </div>
  );
}

function SectionHead({ title, href }: { title: string; href: string }) {
  return (
    <div className="mb-7 flex items-end justify-between gap-4">
      <h2 className="font-display text-3xl uppercase leading-none tracking-[0.01em] text-ink sm:text-4xl">
        {title}
      </h2>
      <Link
        href={href}
        className="inline-flex items-center gap-1 text-sm text-ink-muted hover:text-brand"
      >
        Lihat semua
        <Icon as={ArrowRight} size="xs" />
      </Link>
    </div>
  );
}
