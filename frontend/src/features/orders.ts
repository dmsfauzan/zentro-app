"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type {
  Invoice,
  OrderDetail,
  OrderSummary,
  PaymentInstructions,
  Refund,
} from "@/lib/api/domain";
import { qk, type Filters } from "@/lib/query/keys";
import { getList } from "@/features/http";
import { useAuth } from "@/providers/AuthProvider";

export function useOrders(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.orders(filters),
    queryFn: () => getList<OrderSummary>(endpoints.orders.list, token, filters),
    enabled: Boolean(token),
  });
}

export function useOrder(orderNumber: string) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.order(orderNumber),
    queryFn: () =>
      apiFetch<OrderDetail>(endpoints.orders.detail(orderNumber), { token }),
    enabled: Boolean(token) && Boolean(orderNumber),
  });
}

export function useInvoice(orderNumber: string) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.invoice(orderNumber),
    queryFn: () => apiFetch<Invoice>(endpoints.orders.invoice(orderNumber), { token }),
    enabled: Boolean(token) && Boolean(orderNumber),
  });
}

export function useCancelOrder(orderNumber: string) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (reason: string) =>
      apiFetch<OrderDetail>(endpoints.orders.cancel(orderNumber), {
        method: "POST",
        token,
        json: { reason },
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.order(orderNumber) });
      void queryClient.invalidateQueries({ queryKey: ["orders"] });
    },
  });
}

export function useConfirmReceived(orderNumber: string) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (sellerOrderId?: number) =>
      apiFetch<OrderDetail>(endpoints.orders.confirmReceived(orderNumber), {
        method: "POST",
        token,
        json: sellerOrderId ? { seller_order_id: sellerOrderId } : {},
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.order(orderNumber) });
      void queryClient.invalidateQueries({ queryKey: ["orders"] });
    },
  });
}

export function useOrderRefunds(orderNumber: string) {
  const { token } = useAuth();

  return useQuery({
    queryKey: ["order-refunds", orderNumber],
    queryFn: () => apiFetch<Refund[]>(endpoints.orders.refunds(orderNumber), { token }),
    enabled: Boolean(token) && Boolean(orderNumber),
  });
}

export function useRequestRefund(orderNumber: string) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: { seller_order_id: number; reason: string }) =>
      apiFetch<Refund>(endpoints.orders.refunds(orderNumber), {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["order-refunds", orderNumber] });
      void queryClient.invalidateQueries({ queryKey: qk.order(orderNumber) });
    },
  });
}

export function usePayment(orderNumber: string) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.payment(orderNumber),
    queryFn: () =>
      apiFetch<PaymentInstructions>(endpoints.payment.status(orderNumber), {
        token,
      }),
    enabled: Boolean(token) && Boolean(orderNumber),
    retry: false,
  });
}

export function useRetryPayment(orderNumber: string) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (method?: string) =>
      apiFetch<PaymentInstructions>(endpoints.payment.retry(orderNumber), {
        method: "POST",
        token,
        json: method ? { method } : {},
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.payment(orderNumber) });
      void queryClient.invalidateQueries({ queryKey: qk.order(orderNumber) });
    },
  });
}

export function useSimulatePayment(orderNumber: string) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (status: "paid" | "failed" | "expired" = "paid") =>
      apiFetch<{ payload: Record<string, unknown> }>(
        endpoints.payment.devSimulate(orderNumber),
        { method: "POST", token, json: { status } },
      ),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.payment(orderNumber) });
      void queryClient.invalidateQueries({ queryKey: qk.order(orderNumber) });
      void queryClient.invalidateQueries({ queryKey: ["orders"] });
    },
  });
}
