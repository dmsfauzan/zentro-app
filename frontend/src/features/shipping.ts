"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { Shipment, ShippingOption } from "@/lib/api/domain";
import { qk } from "@/lib/query/keys";
import { useAuth } from "@/providers/AuthProvider";

export function useShippingQuotes(
  query: { seller_id: number; destination_city?: string; weight_gram: number; item_value?: number },
  enabled = true,
) {
  const { token } = useAuth();
  const search = new URLSearchParams();
  search.set("seller_id", String(query.seller_id));
  search.set("weight_gram", String(query.weight_gram));
  if (query.destination_city) search.set("destination_city", query.destination_city);
  if (query.item_value) search.set("item_value", String(query.item_value));

  return useQuery({
    queryKey: qk.shippingQuotes(query),
    queryFn: () =>
      apiFetch<ShippingOption[]>(`${endpoints.shipping.quotes}?${search.toString()}`, {
        token,
      }),
    enabled: Boolean(token) && enabled,
  });
}

export function useTracking(shipmentId: number, enabled = true) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.tracking(shipmentId),
    queryFn: () =>
      apiFetch<Shipment>(endpoints.shipping.tracking(shipmentId), { token }),
    enabled: Boolean(token) && Number.isFinite(shipmentId) && enabled,
  });
}

export function useDevTrack(shipmentId: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (status: "delivered" | "in_transit" | "failed" = "delivered") =>
      apiFetch<{ event: { provider_event_id: string; status: string } }>(
        endpoints.shipping.devTrack(shipmentId),
        { method: "POST", token, json: { status } },
      ),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.tracking(shipmentId) });
      void queryClient.invalidateQueries({ queryKey: ["orders"] });
      void queryClient.invalidateQueries({ queryKey: ["seller"] });
    },
  });
}
