"use client";

import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { Category, Paginated, ProductDetail, ProductSummary } from "@/lib/api/domain";
import { qk, type Filters } from "@/lib/query/keys";
import { apiFetchFull } from "@/features/http";

export async function fetchCategories(): Promise<Category[]> {
  return apiFetch<Category[]>(endpoints.catalog.categories);
}

export async function fetchProducts(
  filters: Filters = {},
): Promise<Paginated<ProductSummary>> {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value === undefined || value === null || value === "") continue;
    search.set(key, String(value));
  }

  const result = await apiFetchFull<ProductSummary[]>(
    `${endpoints.catalog.products}?${search.toString()}`,
  );

  return {
    items: result.data,
    pagination: result.meta?.pagination ?? {
      page: 1,
      per_page: result.data.length,
      total: result.data.length,
      last_page: 1,
      has_more: false,
    },
  };
}

export function useCategories() {
  return useQuery({
    queryKey: qk.categories,
    queryFn: fetchCategories,
    staleTime: 5 * 60_000,
  });
}

export function useProducts(filters: Filters) {
  return useQuery({
    queryKey: qk.products(filters),
    queryFn: () => fetchProducts(filters),
  });
}

export function useProduct(slug: string) {
  return useQuery({
    queryKey: qk.product(slug),
    queryFn: () => apiFetch<ProductDetail>(endpoints.catalog.product(slug)),
  });
}
