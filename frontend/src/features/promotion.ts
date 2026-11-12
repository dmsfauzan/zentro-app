"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { Voucher } from "@/lib/api/domain";
import { qk, type Filters } from "@/lib/query/keys";
import { getList } from "@/features/http";
import { useAuth } from "@/providers/AuthProvider";

export interface VoucherPayload {
  code?: string;
  name?: string;
  type?: "fixed" | "percent";
  value?: number;
  min_purchase?: number;
  max_discount?: number | null;
  scope?: "platform" | "seller";
  seller_id?: number | null;
  usage_limit?: number | null;
  usage_limit_per_user?: number | null;
  starts_at?: string;
  expires_at?: string | null;
  is_active?: boolean;
}

export function useAdminVouchers(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.vouchers(filters),
    queryFn: () => getList<Voucher>(endpoints.admin.vouchers, token, filters),
    enabled: Boolean(token),
  });
}

export function useCreateVoucher() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: VoucherPayload) =>
      apiFetch<Voucher>(endpoints.admin.vouchers, {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "vouchers"] }),
  });
}

export function useUpdateVoucher() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: VoucherPayload }) =>
      apiFetch<Voucher>(endpoints.admin.voucher(id), {
        method: "PATCH",
        token,
        json: payload,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "vouchers"] }),
  });
}
