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

/** Status pembayaran yang masih menunggu penyelesaian (polling aktif). */
export const OPEN_PAYMENT_STATUSES = ["pending", "requires_action"] as const;

export function isOpenPaymentStatus(status: string | null | undefined): boolean {
  return (OPEN_PAYMENT_STATUSES as readonly string[]).includes(status ?? "");
}

export function useOrders(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.orders(filters),
    queryFn: () => getList<OrderSummary>(endpoints.orders.list, token, filters),
    enabled: Boolean(token),
    // Daftar ikut menyegarkan diri selama masih ada pesanan menunggu bayar.
    refetchInterval: (query) => {
      const items = query.state.data?.items ?? [];

      return items.some((order) => order.status === "pending_payment") ? 20_000 : false;
    },
  });
}

export function useOrder(orderNumber: string) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.order(orderNumber),
    queryFn: () =>
      apiFetch<OrderDetail>(endpoints.orders.detail(orderNumber), { token }),
    enabled: Boolean(token) && Boolean(orderNumber),
    refetchInterval: (query) =>
      query.state.data?.status === "pending_payment" ? 10_000 : false,
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

/**
 * Menarik status pembayaran terkini dari provider tiap beberapa detik selama
 * pembayaran masih terbuka (halaman pembayaran & detail pesanan).
 *
 * Data hasil sinkronisasi langsung ditulis ke cache `usePayment`, jadi UI tidak
 * perlu refetch tambahan. Saat status berakhir (paid/failed/expired), query
 * order & daftar pesanan di-invalidate dan polling berhenti.
 */
export function usePaymentSync(orderNumber: string, enabled = true) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useQuery({
    queryKey: ["payment-sync", orderNumber],
    queryFn: async () => {
      const data = await apiFetch<PaymentInstructions>(
        endpoints.payment.sync(orderNumber),
        { method: "POST", token },
      );

      queryClient.setQueryData(qk.payment(orderNumber), data);

      if (!isOpenPaymentStatus(data.status)) {
        void queryClient.invalidateQueries({ queryKey: qk.order(orderNumber) });
        void queryClient.invalidateQueries({ queryKey: ["orders"] });
      }

      return data;
    },
    enabled: Boolean(token) && Boolean(orderNumber) && enabled,
    refetchInterval: (query) =>
      !query.state.data || isOpenPaymentStatus(query.state.data.status) ? 6_000 : false,
    refetchIntervalInBackground: false,
    retry: false,
  });
}

/**
 * Sinkronisasi pembayaran untuk beberapa pesanan sekaligus (halaman daftar
 * pesanan). Dibatasi 5 pesanan per siklus agar tidak membebani provider.
 */
export function usePendingPaymentsSync(orderNumbers: string[], enabled = true) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useQuery({
    queryKey: ["pending-payment-sync", orderNumbers],
    queryFn: async () => {
      const unique = [...new Set(orderNumbers)].slice(0, 5);

      await Promise.all(
        unique.map((orderNumber) =>
          apiFetch<PaymentInstructions>(endpoints.payment.sync(orderNumber), {
            method: "POST",
            token,
          }).catch(() => undefined),
        ),
      );

      void queryClient.invalidateQueries({ queryKey: ["orders"] });

      return unique;
    },
    enabled: Boolean(token) && enabled && uniqueLength(orderNumbers) > 0,
    refetchInterval: enabled ? 20_000 : false,
    retry: false,
  });
}

function uniqueLength(orderNumbers: string[]): number {
  return new Set(orderNumbers).size;
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
