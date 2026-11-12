import { apiFetch, apiFetchFull } from "@/lib/api/client";
import type { Paginated, Pagination } from "@/lib/api/domain";

export type QueryParams = Record<
  string,
  string | number | boolean | null | undefined
>;

export function toQuery(params: QueryParams = {}): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") continue;
    search.set(key, String(value));
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function getList<T>(
  path: string,
  token: string | null,
  params: QueryParams = {},
): Promise<Paginated<T>> {
  const result = await apiFetchFull<T[]>(`${path}${toQuery(params)}`, { token });

  return {
    items: result.data,
    pagination: (result.meta?.pagination ?? {
      page: 1,
      per_page: result.data.length,
      total: result.data.length,
      last_page: 1,
      has_more: false,
    }) as Pagination,
  };
}

export { apiFetch, apiFetchFull };
