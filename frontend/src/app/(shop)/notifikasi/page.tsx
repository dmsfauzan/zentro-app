"use client";

import Link from "next/link";
import { useState } from "react";
import { Bell } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Icon } from "@/components/ui/Icon";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { PageHeader } from "@/components/ui/PageHeader";
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
} from "@/features/notifications";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import { formatDateTime } from "@/lib/format";
import { cn } from "@/lib/cn";

export default function NotificationsPage() {
  const [filters, setFilters] = useUrlFilters({ unread: "", page: "1" });
  const notifications = useNotifications({
    unread: filters.unread || undefined,
    page: filters.page,
    per_page: 20,
  });
  const markRead = useMarkNotificationRead();
  const markAll = useMarkAllNotificationsRead();
  const toast = useToast();
  const [busyId, setBusyId] = useState<string | null>(null);

  const items = notifications.data?.items ?? [];
  const pagination = notifications.data?.pagination;

  return (
    <div className="container-page py-8">
      <PageHeader
        title="Notifikasi"
        description={`${notifications.data?.unread_count ?? 0} belum dibaca.`}
        actions={
          <div className="flex gap-2">
            <Button
              size="sm"
              variant={filters.unread ? "primary" : "secondary"}
              onClick={() => setFilters({ unread: filters.unread ? "" : "1", page: "1" })}
            >
              {filters.unread ? "Tampilkan semua" : "Hanya belum dibaca"}
            </Button>
            <Button
              size="sm"
              variant="ghost"
              loading={markAll.isPending}
              disabled={(notifications.data?.unread_count ?? 0) === 0}
              onClick={() =>
                markAll.mutate(undefined, {
                  onSuccess: () => toast.success("Semua notifikasi ditandai dibaca"),
                  onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                })
              }
            >
              Tandai semua
            </Button>
          </div>
        }
      />

      {notifications.isPending ? (
        <div className="mt-5 space-y-3">
          <Skeleton className="h-20 w-full" />
          <Skeleton className="h-20 w-full" />
        </div>
      ) : items.length === 0 ? (
        <div className="mt-5">
          <EmptyState
            icon={Bell}
            title="Tidak ada notifikasi"
            description="Pemberitahuan pesanan dan pembayaran akan muncul di sini."
          />
        </div>
      ) : (
        <div className="mt-5 space-y-2">
          {items.map((notification) => {
            const unread = notification.read_at === null;

            return (
              <Link
                key={notification.id}
                href={`/notifikasi/${notification.id}`}
                className="block rounded-[12px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
              >
                <Card
                  className={cn(
                    "p-4 transition hover:border-line-strong",
                    unread ? "border-brand/30 bg-brand-soft/40" : undefined,
                  )}
                >
                  <div className="flex items-start gap-3">
                    <span className="mt-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-canvas text-ink-subtle">
                      <Icon as={Bell} size="xs" />
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-medium text-ink">{notification.title}</p>
                        {unread ? (
                          <span className="rounded-[4px] bg-brand px-1.5 py-0.5 text-[10px] font-medium text-white">
                            Baru
                          </span>
                        ) : null}
                      </div>
                      <p className="mt-1 line-clamp-2 text-sm text-ink-muted">{notification.body}</p>
                      <p className="mt-1 text-xs text-ink-subtle">
                        {formatDateTime(notification.created_at)}
                      </p>
                    </div>
                    {unread ? (
                      <Button
                        size="xs"
                        variant="ghost"
                        loading={busyId === notification.id}
                        onClick={(event) => {
                          event.preventDefault();
                          event.stopPropagation();
                          setBusyId(notification.id);
                          markRead.mutate(notification.id, {
                            onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                            onSettled: () => setBusyId(null),
                          });
                        }}
                      >
                        Tandai dibaca
                      </Button>
                    ) : null}
                  </div>
                </Card>
              </Link>
            );
          })}
        </div>
      )}

      {pagination ? (
        <Pagination
          page={pagination.page}
          lastPage={pagination.last_page}
          onChange={(page) => setFilters({ page: String(page) })}
        />
      ) : null}
    </div>
  );
}
