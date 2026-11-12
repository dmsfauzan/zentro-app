"use client";

import { PackageSearch } from "lucide-react";
import Link from "next/link";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useSellerProducts } from "@/features/seller";
import { Money } from "@/components/ui/Money";

export default function InventoryPage() {
  const products = useSellerProducts({ per_page: 50 });
  const items = products.data?.items ?? [];

  return (
    <div className="space-y-5">
      <PageHeader
        title="Inventori"
        description="Ringkasan katalog. Perubahan stok dilakukan per varian di halaman produk."
        eyebrow="Penjual"
      />

      {products.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState
          icon={PackageSearch}
          title="Belum ada produk"
          description="Tambahkan produk untuk mulai mengelola stok."
          action={<Link href="/seller/produk/baru" className="text-sm font-medium text-brand hover:underline">Produk baru</Link>}
        />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Produk</TH>
                <TH>Kategori</TH>
                <TH>Status</TH>
                <TH numeric>Harga mulai</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((product) => (
                <TR key={product.id}>
                  <TD>
                    <p className="line-clamp-1 text-sm font-medium text-ink">{product.name}</p>
                    <p className="truncate text-xs text-ink-subtle">{product.slug}</p>
                  </TD>
                  <TD>{product.category?.name ?? "-"}</TD>
                  <TD><StatusBadge domain="product" value={product.status} /></TD>
                  <TD numeric><Money value={product.min_price} /></TD>
                  <TD>
                    <Link href={`/seller/produk/${product.id}`} className="text-sm font-medium text-brand hover:underline">
                      Atur stok
                    </Link>
                  </TD>
                </TR>
              ))}
            </TBody>
          </TableWrap>
        </Card>
      )}
    </div>
  );
}
