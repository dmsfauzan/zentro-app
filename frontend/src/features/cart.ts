"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { CartData, CartItem } from "@/lib/api/domain";
import { qk } from "@/lib/query/keys";
import { useAuth } from "@/providers/AuthProvider";

export function useCart() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.cart,
    queryFn: () => apiFetch<CartData>(endpoints.cart.root, { token }),
    enabled: Boolean(token),
    staleTime: 0,
  });
}

function useCartMutation<TVariables>(
  mutationFn: (variables: TVariables, token: string | null) => Promise<unknown>,
) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variables: TVariables) => mutationFn(variables, token),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.cart });
      void queryClient.invalidateQueries({ queryKey: ["products"] });
    },
  });
}

export function useAddToCart() {
  return useCartMutation<{ variant_id: number; quantity: number }>((payload, token) =>
    apiFetch<CartItem>(endpoints.cart.items, {
      method: "POST",
      token,
      json: payload,
    }),
  );
}

export function useUpdateCartItem() {
  return useCartMutation<{ id: number; quantity: number }>(({ id, quantity }, token) =>
    apiFetch<CartItem>(endpoints.cart.item(id), {
      method: "PATCH",
      token,
      json: { quantity },
    }),
  );
}

export function useRemoveCartItem() {
  return useCartMutation<number>((id, token) =>
    apiFetch<null>(endpoints.cart.item(id), { method: "DELETE", token }),
  );
}
