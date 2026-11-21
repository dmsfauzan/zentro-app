"use client";

import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { RegionOption, RegionVillageOption } from "@/lib/api/domain";
import { qk } from "@/lib/query/keys";

export function useProvinces() {
  return useQuery({
    queryKey: qk.regions.provinces,
    queryFn: () => apiFetch<RegionOption[]>(endpoints.regions.provinces),
    staleTime: 24 * 60 * 60_000,
  });
}

export function useRegencies(provinceCode: string) {
  return useQuery({
    queryKey: qk.regions.regencies(provinceCode),
    queryFn: () =>
      apiFetch<RegionOption[]>(
        `${endpoints.regions.regencies}?province=${encodeURIComponent(provinceCode)}`,
      ),
    enabled: Boolean(provinceCode),
    staleTime: 24 * 60 * 60_000,
  });
}

export function useDistricts(regencyCode: string) {
  return useQuery({
    queryKey: qk.regions.districts(regencyCode),
    queryFn: () =>
      apiFetch<RegionOption[]>(
        `${endpoints.regions.districts}?regency=${encodeURIComponent(regencyCode)}`,
      ),
    enabled: Boolean(regencyCode),
    staleTime: 24 * 60 * 60_000,
  });
}

export function useVillages(districtCode: string) {
  return useQuery({
    queryKey: qk.regions.villages(districtCode),
    queryFn: () =>
      apiFetch<RegionVillageOption[]>(
        `${endpoints.regions.villages}?district=${encodeURIComponent(districtCode)}`,
      ),
    enabled: Boolean(districtCode),
    staleTime: 24 * 60 * 60_000,
  });
}
