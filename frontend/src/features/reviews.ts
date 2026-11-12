"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { Paginated, Review } from "@/lib/api/domain";
import { qk } from "@/lib/query/keys";
import { getList } from "@/features/http";
import { useAuth } from "@/providers/AuthProvider";

export function useProductReviews(slug: string, page = "1") {
  return useQuery({
    queryKey: qk.productReviews(slug, Number(page)),
    queryFn: () =>
      getList<Review>(endpoints.reviews.product(slug), null, {
        page,
        per_page: 10,
      }) as Promise<Paginated<Review>>,
  });
}

export function useCreateReview(orderNumber: string) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: {
      order_item_id: number;
      rating: number;
      comment?: string | null;
    }) =>
      apiFetch<Review>(endpoints.reviews.create(orderNumber), {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["product-reviews"] });
      void queryClient.invalidateQueries({ queryKey: ["products"] });
      void queryClient.invalidateQueries({ queryKey: qk.order(orderNumber) });
    },
  });
}
