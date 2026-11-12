"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
import { ButtonLink } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Icon } from "@/components/ui/Icon";
import { Pagination } from "@/components/ui/Pagination";
import { Select } from "@/components/ui/Input";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { Money } from "@/components/ui/Money";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useSellerProducts } from "@/features/seller";
import { useUrlFilters } from "@/lib/use-url-filters";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "draft", label: "Draf" },
  { value: "active", label: "Tayang" },
  { value: "inactive", label: "Nonaktif" },
  { value: "archived", label: "Diarsipkan" },
];

export default function SellerProductsPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", page: "1" });
  const products = useSellerProducts({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });

  const items = products.data?.items ?? [];
  const pagination = products.data?.pagination;

  return (
    <div className="space-y-5">
      <PageHeader
        title="Produk"
        description="Kelola katalog, varian, gambar, dan stok."
        actions={
          <ButtonLink href="/seller/produk/baru" size="sm">
            <Icon as={Plus} size="xs" />
            Produk baru
          </ButtonLink>
        }
      />

      <div className="flex flex-wrap items-center gap-2">
        <Select
          aria-label="Filter status"
          className="h-9 w-44"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>{status.label}</option>
          ))}
        </Select>
      </div>

      {products.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState
          title="Belum ada produk"
          description="Buat produk pertama untuk mulai berjualan."
          action={<ButtonLink href="/seller/produk/baru">Produk baru</ButtonLink>}
        />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Produk</TH>
                <TH>Status</TH>
                <TH numeric>Harga</TH>
                <TH numeric>Terjual</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((product) => (
                <TR key={product.id}>
                  <TD>
                    <div className="flex items-center gap-3">
                      <div className="size-10 overflow-hidden rounded-[6px] border border-line bg-paper">
                        {product.primary_image ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={product.primary_image.url} alt="" className="size-full object-cover" loading="lazy" />
                        ) : null}
                      </div>
                      <div className="min-w-0">
                        <p className="line-clamp-1 text-sm font-medium text-ink">{product.name}</p>
                        <p className="truncate text-xs text-ink-subtle">{product.category?.name ?? "-"}</p>
                      </div>
                    </div>
                  </TD>
                  <TD><StatusBadge domain="product" value={product.status} /></TD>
                  <TD numeric><Money value={product.min_price} /></TD>
                  <TD numeric>{product.sold_count}</TD>
                  <TD>
                    <Link href={`/seller/produk/${product.id}`} className="text-sm font-medium text-brand hover:underline">
                      Kelola
                    </Link>
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
    </div>
  );
}
