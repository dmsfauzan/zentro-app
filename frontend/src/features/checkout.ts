"use client";

import { keepPreviousData, useMutation, useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { CheckoutPreview, PaymentInstructions } from "@/lib/api/domain";
import { qk } from "@/lib/query/keys";
import { useAuth } from "@/providers/AuthProvider";

export interface CheckoutItemPayload {
  variant_id: number;
  quantity: number;
}

export interface ShippingSelection {
  seller_id: number;
  service_code: string;
}

export interface PreviewPayload {
  items?: CheckoutItemPayload[];
  address_id?: number | null;
  shipping_selections?: ShippingSelection[];
  voucher_code?: string | null;
}

export interface PlaceOrderPayload {
  items: CheckoutItemPayload[];
  address_id: number;
  shipping_selections: ShippingSelection[];
  voucher_code?: string | null;
}

export interface CheckoutResult {
  order_number: string;
  status: string;
  subtotal: number;
  discount_total: number;
  shipping_total: number;
  grand_total: number;
  currency: string;
  expires_at: string;
  payment: PaymentInstructions | null;
  seller_orders: {
    id: number;
    seller_id: number;
    seller_order_number: string;
    status: string;
    subtotal: number;
    shipping_cost: number;
    discount_total: number;
    grand_total: number;
  }[];
}

export function useCheckoutPreview(payload: PreviewPayload, enabled = true) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.checkoutPreview(JSON.stringify(payload)),
    queryFn: () =>
      apiFetch<CheckoutPreview>(endpoints.checkout.preview, {
        method: "POST",
        token,
        json: payload,
      }),
    enabled: Boolean(token) && enabled,
    staleTime: 0,
    // Jangan kosongkan ringkasan/pilihan diskon saat menghitung ulang voucher.
    placeholderData: keepPreviousData,
  });
}

export function usePlaceOrder() {
  const { token } = useAuth();

  return useMutation({
    mutationFn: ({
      payload,
      idempotencyKey,
    }: {
      payload: PlaceOrderPayload;
      idempotencyKey: string;
    }) =>
      apiFetch<CheckoutResult>(endpoints.checkout.place, {
        method: "POST",
        token,
        json: payload,
        idempotencyKey,
      }),
  });
}
