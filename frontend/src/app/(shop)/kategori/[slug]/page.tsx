"use client";

import { useParams } from "next/navigation";
import { ProductListing } from "@/components/shop/ProductListing";
import { useCategories } from "@/features/catalog";
import { findCategoryBySlug } from "@/lib/categories";

export default function CategoryPage() {
  const { slug } = useParams<{ slug: string }>();
  const categories = useCategories();
  const category = findCategoryBySlug(categories.data ?? [], slug);

  return (
    <ProductListing
      title={category?.name ?? "Kategori"}
      subtitle="Produk dari seluruh penjual pada kategori ini (termasuk sub-kategori)."
      lockedCategory={slug}
    />
  );
}
