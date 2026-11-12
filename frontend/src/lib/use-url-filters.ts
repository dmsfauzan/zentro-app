"use client";

import { usePathname, useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";

export type UrlFilters = Record<string, string>;

/**
 * Filter berbasis URL tanpa `useSearchParams` (menghindari CSR bailout).
 *
 * Catatan penting: `router.replace` TIDAK boleh dipanggil di dalam updater
 * `setState` (React akan menganggap Router di-update saat render). Karena itu
 * nilai terbaru dilacak lewat ref, lalu navigasi dilakukan di luar updater.
 */
export function useUrlFilters(initial: UrlFilters = {}) {
  const pathname = usePathname();
  const router = useRouter();
  const initialRef = useRef(initial);
  const filtersRef = useRef<UrlFilters>(initial);
  const [filters, setFiltersState] = useState<UrlFilters>(initial);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const next: UrlFilters = { ...initialRef.current };

    for (const key of Object.keys(initialRef.current)) {
      const value = params.get(key);
      if (value !== null) next[key] = value;
    }

    filtersRef.current = next;
    // Hydration satu kali dari URL (sumber eksternal); bukan cascading render.
    setFiltersState(next);
  }, []);

  const setFilters = useCallback(
    (patch: UrlFilters) => {
      const next: UrlFilters = { ...filtersRef.current, ...patch };
      filtersRef.current = next;
      setFiltersState(next);

      const params = new URLSearchParams();

      for (const [key, value] of Object.entries(next)) {
        if (value !== "") params.set(key, value);
      }

      const query = params.toString();
      router.replace(query ? `${pathname}?${query}` : pathname, { scroll: false });
    },
    [pathname, router],
  );

  return [filters, setFilters] as const;
}
