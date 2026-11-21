"use client";

import { useMutation } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { ProductSummary } from "@/lib/api/domain";
import { useAuth } from "@/providers/AuthProvider";

export interface AssistantProduct extends ProductSummary {
  default_variant: {
    id: number;
    variant_name: string | null;
    price: number;
    available_stock: number;
  } | null;
}

export interface AssistantResponse {
  reply: string;
  products: AssistantProduct[];
}

export interface AssistantTurn {
  role: "user" | "assistant";
  content: string;
}

export function useAssistantChat() {
  const { token } = useAuth();

  return useMutation({
    mutationFn: ({ message, history }: { message: string; history: AssistantTurn[] }) =>
      apiFetch<AssistantResponse>(endpoints.assistant.chat, {
        method: "POST",
        token,
        json: { message, history },
      }),
  });
}
