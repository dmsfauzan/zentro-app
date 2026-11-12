"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import { API_BASE_URL } from "@/lib/config";
import { ApiError } from "@/lib/api/client";
import type {
  AdminCategory,
  AdminReview,
  AdminUser,
  AuditLog,
  Commission,
  OrderSummary,
  Payout,
  PaymentInstructions,
  ProductSummary,
  Refund,
  SellerProfile,
} from "@/lib/api/domain";
import { qk, type Filters } from "@/lib/query/keys";
import { getList } from "@/features/http";
import { useAuth } from "@/providers/AuthProvider";

/* Sellers ------------------------------------------------------------ */

export function useAdminSellers(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.sellers(filters),
    queryFn: () => getList<SellerProfile>(endpoints.admin.sellers, token, filters),
    enabled: Boolean(token),
  });
}

function useSellerAction(action: "approve" | "reject" | "suspend" | "reactivate") {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason?: string }) => {
      const path =
        action === "approve"
          ? endpoints.admin.sellerApprove(id)
          : action === "reject"
            ? endpoints.admin.sellerReject(id)
            : action === "suspend"
              ? endpoints.admin.sellerSuspend(id)
              : endpoints.admin.sellerReactivate(id);

      return apiFetch<SellerProfile>(path, {
        method: "POST",
        token,
        json: reason ? { reason } : {},
      });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "sellers"] });
    },
  });
}

export const useApproveSeller = () => useSellerAction("approve");
export const useRejectSeller = () => useSellerAction("reject");
export const useSuspendSeller = () => useSellerAction("suspend");
export const useReactivateSeller = () => useSellerAction("reactivate");

/** Unduh dokumen seller (butuh Authorization header, jadi lewat blob). */
export async function downloadSellerDocument(
  id: number,
  token: string | null,
  filename: string,
): Promise<void> {
  const response = await fetch(`${API_BASE_URL}${endpoints.admin.sellerDocument(id)}`, {
    headers: { Accept: "application/octet-stream", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });

  if (!response.ok) {
    throw new ApiError("Gagal mengunduh dokumen.", response.status);
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");

  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

/* Products ----------------------------------------------------------- */

export function useAdminProducts(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.products(filters),
    queryFn: () => getList<ProductSummary>(endpoints.admin.products, token, filters),
    enabled: Boolean(token),
  });
}

export function useModerateProduct() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      status,
      reason,
    }: {
      id: number;
      status: "active" | "inactive" | "rejected";
      reason?: string;
    }) =>
      apiFetch(endpoints.admin.productStatus(id), {
        method: "PATCH",
        token,
        json: reason ? { status, reason } : { status },
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "products"] });
    },
  });
}

export function useAdminCommissions(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.commissions(filters),
    queryFn: () =>
      getList<Commission>(endpoints.admin.commissions, token, filters),
    enabled: Boolean(token),
  });
}

/* Read-only admin: orders, payments, audit logs ----------------------- */

export function useAdminOrders(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.orders(filters),
    queryFn: () => getList<OrderSummary>(endpoints.admin.orders, token, filters),
    enabled: Boolean(token),
  });
}

export type AdminPaymentRow = PaymentInstructions & { order_number?: string | null };

export function useAdminPayments(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.payments(filters),
    queryFn: () => getList<AdminPaymentRow>(endpoints.admin.payments, token, filters),
    enabled: Boolean(token),
  });
}

export function useAdminAuditLogs(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.auditLogs(filters),
    queryFn: () => getList<AuditLog>(endpoints.admin.auditLogs, token, filters),
    enabled: Boolean(token),
  });
}

/* Payouts ------------------------------------------------------------ */

export function useAdminPayouts(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.payouts(filters),
    queryFn: () => getList<Payout>(endpoints.admin.payouts, token, filters),
    enabled: Boolean(token),
  });
}

export function useProcessPayout() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      action,
      providerReference,
      reason,
    }: {
      id: number;
      action: "approve" | "start" | "mark_paid" | "fail" | "retry" | "reject";
      providerReference?: string;
      reason?: string;
    }) =>
      apiFetch<Payout>(endpoints.admin.payoutProcess(id), {
        method: "POST",
        token,
        json: {
          action,
          ...(providerReference ? { provider_reference: providerReference } : {}),
          ...(reason ? { reason } : {}),
        },
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "payouts"] });
    },
  });
}

/* Refunds ------------------------------------------------------------ */

export function useAdminRefunds(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.refunds(filters),
    queryFn: () => getList<Refund>(endpoints.admin.refunds, token, filters),
    enabled: Boolean(token),
  });
}

export function useAdminCreateRefund() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: {
      order_number: string;
      seller_order_id: number;
      amount: number;
      reason: string;
    }) =>
      apiFetch<Refund>(endpoints.admin.refunds, {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "refunds"] });
    },
  });
}

export function useApproveRefund() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<Refund>(endpoints.admin.refundApprove(id), {
        method: "POST",
        token,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "refunds"] });
    },
  });
}

export function useRejectRefund() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      apiFetch<Refund>(endpoints.admin.refundReject(id), {
        method: "POST",
        token,
        json: { reason },
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "refunds"] });
    },
  });
}

/* Categories --------------------------------------------------------- */

export function useAdminCategories(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.categories(filters),
    queryFn: () => getList<AdminCategory>(endpoints.admin.categories, token, filters),
    enabled: Boolean(token),
  });
}

export function useCreateCategory() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: {
      name: string;
      parent_id?: number | null;
      description?: string | null;
      sort_order?: number;
      is_active?: boolean;
    }) =>
      apiFetch<AdminCategory>(endpoints.admin.categories, {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "categories"] });
      void queryClient.invalidateQueries({ queryKey: ["categories"] });
    },
  });
}

export function useUpdateCategory() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      ...payload
    }: {
      id: number;
      name?: string;
      parent_id?: number | null;
      description?: string | null;
      sort_order?: number;
      is_active?: boolean;
    }) =>
      apiFetch<AdminCategory>(endpoints.admin.category(id), {
        method: "PATCH",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "categories"] });
      void queryClient.invalidateQueries({ queryKey: ["categories"] });
    },
  });
}

export function useDeleteCategory() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch(endpoints.admin.category(id), { method: "DELETE", token }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "categories"] });
      void queryClient.invalidateQueries({ queryKey: ["categories"] });
    },
  });
}

/* Reviews ------------------------------------------------------------ */

export function useAdminReviews(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.reviews(filters),
    queryFn: () => getList<AdminReview>(endpoints.admin.reviews, token, filters),
    enabled: Boolean(token),
  });
}

export function useModerateReview() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: "published" | "hidden" | "pending" }) =>
      apiFetch<AdminReview>(endpoints.admin.review(id), {
        method: "PATCH",
        token,
        json: { status },
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "reviews"] });
    },
  });
}

/* Users -------------------------------------------------------------- */

export function useAdminUsers(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.admin.users(filters),
    queryFn: () => getList<AdminUser>(endpoints.admin.users, token, filters),
    enabled: Boolean(token),
  });
}

export function useUpdateUser() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      roles,
      status,
    }: {
      id: number;
      roles?: string[];
      status?: string;
    }) =>
      apiFetch<AdminUser>(endpoints.admin.user(id), {
        method: "PATCH",
        token,
        json: {
          ...(roles ? { roles } : {}),
          ...(status ? { status } : {}),
        },
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
    },
  });
}
