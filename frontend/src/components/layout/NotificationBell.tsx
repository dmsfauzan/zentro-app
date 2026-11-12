"use client";

import { Bell } from "lucide-react";
import Link from "next/link";
import { Icon } from "@/components/ui/Icon";
import { useUnreadNotificationCount } from "@/features/notifications";
import { useAuth } from "@/providers/AuthProvider";

export function NotificationBell() {
  const { token } = useAuth();
  const notifications = useUnreadNotificationCount();
  const count = token ? (notifications.data?.unread_count ?? 0) : 0;

  return (
    <Link
      href="/notifikasi"
      className="relative inline-flex size-9 items-center justify-center rounded-[8px] text-ink hover:bg-ink/5"
      aria-label={`Notifikasi${count > 0 ? `, ${count} belum dibaca` : ""}`}
    >
      <Icon as={Bell} size="md" />
      {count > 0 ? (
        <span className="num absolute -right-0.5 -top-0.5 flex min-w-[18px] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
          {count > 99 ? "99+" : count}
        </span>
      ) : null}
    </Link>
  );
}
