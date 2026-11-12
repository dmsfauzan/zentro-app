"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ChevronDown, ChevronRight, SlidersHorizontal, X } from "lucide-react";
import { ProductCard } from "@/components/shop/ProductCard";
import { Pagination } from "@/components/ui/Pagination";
import { CardGridSkeleton } from "@/components/ui/Skeleton";
import { EmptyState } from "@/components/ui/EmptyState";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Input, Select } from "@/components/ui/Input";
import { useCategories, useProducts } from "@/features/catalog";
import { parentSlugOf } from "@/lib/categories";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/cn";

const sortOptions = [
  { value: "newest", label: "Terbaru" },
  { value: "price_asc", label: "Harga terendah" },
  { value: "price_desc", label: "Harga tertinggi" },
  { value: "popular", label: "Terlaris" },
];

export function ProductListing({
  title,
  subtitle,
  lockedCategory,
}: {
  title: string;
  subtitle?: string;
  lockedCategory?: string;
}) {
  const [filters, setFilters] = useUrlFilters({
    q: "",
    category: lockedCategory ?? "",
    price_min: "",
    price_max: "",
    sort: "newest",
    page: "1",
  });
  const [openFilter, setOpenFilter] = useState(false);
  const [manualOpen, setManualOpen] = useState<string | null>(null);
  const router = useRouter();

  const categories = useCategories();
  const categoryList = categories.data ?? [];

  // Kategori halaman (`/kategori/{slug}`) mengunci filter; selain itu pakai state URL.
  const activeCategory = lockedCategory ?? filters.category;
  const activeParent = activeCategory ? parentSlugOf(categoryList, activeCategory) : null;
  const openCategory = manualOpen ?? activeParent;

  const selectCategory = (slug: string) => {
    if (lockedCategory !== undefined) {
      if (slug === lockedCategory) {
        return;
      }

      router.push(slug === "" ? "/produk" : `/kategori/${slug}`);

      return;
    }

    setFilters({ category: slug, page: "1" });
  };

  const products = useProducts({
    q: filters.q || undefined,
    category: activeCategory || undefined,
    price_min: filters.price_min || undefined,
    price_max: filters.price_max || undefined,
    sort: filters.sort,
    page: filters.page,
    per_page: 12,
  });

  const items = products.data?.items ?? [];
  const pagination = products.data?.pagination;

  const filterPanel = (
    <div className="space-y-6">
      <div>
        <p className="mb-3 text-xs font-medium uppercase tracking-[0.14em] text-ink-subtle">
          Kategori
        </p>
        <div className="space-y-2.5">
          <FilterItem
            active={activeCategory === ""}
            onClick={() => selectCategory("")}
            label="Semua kategori"
          />
          {(categoryList ?? []).map((category) => {
            const children = category.children ?? [];
            const expanded = openCategory === category.slug;

            return (
              <div key={category.id} className="space-y-2">
                <div className="flex items-center gap-1">
                  <div className="min-w-0 flex-1">
                    <FilterItem
                      active={activeCategory === category.slug}
                      onClick={() => selectCategory(category.slug)}
                      label={category.name}
                    />
                  </div>
                  {children.length > 0 ? (
                    <button
                      type="button"
                      aria-label={
                        expanded
                          ? `Tutup sub-kategori ${category.name}`
                          : `Buka sub-kategori ${category.name}`
                      }
                      aria-expanded={expanded}
                      onClick={() => setManualOpen(expanded ? "" : category.slug)}
                      className="inline-flex size-6 shrink-0 items-center justify-center rounded-full text-ink-subtle hover:bg-ink/5"
                    >
                      <Icon as={expanded ? ChevronDown : ChevronRight} size="xs" />
                    </button>
                  ) : null}
                </div>

                {expanded && children.length > 0 ? (
                  <div className="space-y-2.5 border-l border-line pl-3">
                    {children.map((child) => (
                      <FilterItem
                        key={child.id}
                        active={activeCategory === child.slug}
                        onClick={() => selectCategory(child.slug)}
                        label={child.name}
                      />
                    ))}
                  </div>
                ) : null}
              </div>
            );
          })}
        </div>
      </div>

      <div className="border-t border-line pt-5">
        <p className="mb-3 text-xs font-medium uppercase tracking-[0.14em] text-ink-subtle">
          Harga
        </p>
        <form
          className="space-y-2"
          onSubmit={(event) => {
            event.preventDefault();
            const data = new FormData(event.currentTarget);
            setFilters({
              price_min: String(data.get("price_min") ?? ""),
              price_max: String(data.get("price_max") ?? ""),
              page: "1",
            });
          }}
        >
          <div className="flex items-center gap-2">
            <Input
              name="price_min"
              inputMode="numeric"
              placeholder="Min"
              defaultValue={filters.price_min}
              className="h-9"
            />
            <span className="text-ink-subtle">–</span>
            <Input
              name="price_max"
              inputMode="numeric"
              placeholder="Maks"
              defaultValue={filters.price_max}
              className="h-9"
            />
          </div>
          <Button type="submit" size="sm" className="w-full">
            Terapkan harga
          </Button>
        </form>
      </div>

      {filters.q || activeCategory || filters.price_min || filters.price_max ? (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="w-full justify-start"
          onClick={() => {
            setFilters({ q: "", category: "", price_min: "", price_max: "", page: "1" });

            if (lockedCategory !== undefined) {
              router.push("/produk");
            }
          }}
        >
          <Icon as={X} size="xs" />
          Hapus semua filter
        </Button>
      ) : null}
    </div>
  );

  return (
    <div className="container-page py-8 lg:py-10">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-display text-3xl uppercase leading-none tracking-[0.01em] text-ink sm:text-4xl">
            {title}
          </h1>
          {subtitle ? <p className="mt-2 text-sm text-ink-muted">{subtitle}</p> : null}
        </div>
        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            className="lg:hidden"
            onClick={() => setOpenFilter(true)}
          >
            <Icon as={SlidersHorizontal} size="xs" />
            Filter
          </Button>
          <Select
            aria-label="Urutkan"
            className="h-9 w-44 rounded-full"
            value={filters.sort}
            onChange={(event) => setFilters({ sort: event.target.value, page: "1" })}
          >
            {sortOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </div>
      </div>

      <div className="mt-7 flex gap-8">
        <aside className="hidden w-60 shrink-0 lg:block">
          <div className="sticky top-32 rounded-[16px] border border-line bg-canvas p-5">
            {filterPanel}
          </div>
        </aside>

        <div className="min-w-0 flex-1">
          <p className="mb-5 text-sm text-ink-subtle">
            {pagination
              ? `Menampilkan ${formatNumber(items.length)} dari ${formatNumber(pagination.total)} produk`
              : "Memuat produk…"}
          </p>

          {products.isPending ? (
            <CardGridSkeleton count={9} />
          ) : items.length === 0 ? (
            <EmptyState
              title="Produk tidak ditemukan"
              description="Coba kata kunci lain atau hapus beberapa filter."
            />
          ) : (
            <div className="grid grid-cols-2 gap-x-4 gap-y-9 md:grid-cols-3">
              {items.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}

          {pagination ? (
            <Pagination
              page={pagination.page}
              lastPage={pagination.last_page}
              onChange={(page) => {
                setFilters({ page: String(page) });
                window.scrollTo({ top: 0, behavior: "smooth" });
              }}
            />
          ) : null}
        </div>
      </div>

      {openFilter ? (
        <div className="fixed inset-0 z-50 lg:hidden">
          <button
            type="button"
            aria-label="Tutup filter"
            className="absolute inset-0 bg-ink/40"
            onClick={() => setOpenFilter(false)}
          />
          <div className="absolute inset-y-0 left-0 w-[min(320px,85vw)] overflow-y-auto rounded-r-[16px] bg-canvas p-5">
            <div className="mb-5 flex items-center justify-between">
              <p className="font-display text-xl uppercase tracking-[0.02em] text-ink">
                Filter
              </p>
              <button
                type="button"
                onClick={() => setOpenFilter(false)}
                className="inline-flex size-8 items-center justify-center rounded-full hover:bg-ink/5"
                aria-label="Tutup"
              >
                <Icon as={X} size="sm" />
              </button>
            </div>
            {filterPanel}
            <Button className="mt-6 w-full" onClick={() => setOpenFilter(false)}>
              Terapkan
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function FilterItem({
  label,
  active,
  onClick,
}: {
  label: string;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        "block w-full text-left text-sm",
        active ? "font-semibold text-brand" : "text-ink-muted hover:text-ink",
      )}
    >
      {label}
    </button>
  );
}
