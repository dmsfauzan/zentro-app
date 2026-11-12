"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { AppNotification, NotificationList } from "@/lib/api/domain";
import { qk, type Filters } from "@/lib/query/keys";
import { useAuth } from "@/providers/AuthProvider";

export function useNotifications(filters: Filters = {}) {
  const { token } = useAuth();
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value === undefined || value === null || value === "") continue;
    search.set(key, String(value));
  }

  return useQuery({
    queryKey: qk.notificationsList(filters),
    queryFn: () =>
      apiFetch<NotificationList>(
        `${endpoints.notifications.list}?${search.toString()}`,
        { token },
      ),
    enabled: Boolean(token),
    refetchInterval: 60_000,
  });
}

export function useNotification(id: string) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.notification(id),
    queryFn: () => apiFetch<AppNotification>(endpoints.notifications.show(id), { token }),
    enabled: Boolean(token) && Boolean(id),
    retry: false,
  });
}

export function useUnreadNotificationCount() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.notificationsList({ per_page: 1 }),
    queryFn: () =>
      apiFetch<NotificationList>(`${endpoints.notifications.list}?per_page=1`, {
        token,
      }),
    enabled: Boolean(token),
    refetchInterval: 60_000,
  });
}

export function useMarkNotificationRead() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) =>
      apiFetch<AppNotification>(endpoints.notifications.read(id), {
        method: "POST",
        token,
      }),
    onSuccess: (_data, id) => {
      void queryClient.invalidateQueries({ queryKey: qk.notifications });
      void queryClient.invalidateQueries({ queryKey: qk.notification(id) });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () =>
      apiFetch<null>(endpoints.notifications.readAll, { method: "POST", token }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.notifications });
    },
  });
}
