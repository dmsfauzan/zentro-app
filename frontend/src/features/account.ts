"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { User } from "@/lib/api/types";
import type { UserAddress } from "@/lib/api/domain";
import { qk } from "@/lib/query/keys";
import { useAuth } from "@/providers/AuthProvider";

export function useMe() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.me,
    queryFn: () => apiFetch<User>(endpoints.auth.me, { token }),
    enabled: Boolean(token),
  });
}

export function useUpdateMe() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: { name?: string; phone?: string | null }) =>
      apiFetch<User>(endpoints.auth.me, { method: "PATCH", token, json: payload }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.me }),
  });
}

export function useAddresses() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.addresses,
    queryFn: () => apiFetch<UserAddress[]>(endpoints.addresses.list, { token }),
    enabled: Boolean(token),
  });
}

export type AddressPayload = Omit<
  UserAddress,
  "id" | "created_at" | "updated_at" | "latitude" | "longitude"
> & { latitude?: number | null; longitude?: number | null };

export function useCreateAddress() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: AddressPayload) =>
      apiFetch<UserAddress>(endpoints.addresses.create, {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.addresses }),
  });
}

export function useUpdateAddress() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<AddressPayload> }) =>
      apiFetch<UserAddress>(endpoints.addresses.detail(id), {
        method: "PATCH",
        token,
        json: payload,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.addresses }),
  });
}

export function useDeleteAddress() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<null>(endpoints.addresses.detail(id), {
        method: "DELETE",
        token,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.addresses }),
  });
}
