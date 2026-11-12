"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { ChevronDown, LogOut, ReceiptText, RefreshCw, Store, ShieldCheck, UserRound } from "lucide-react";
import * as DropdownMenu from "@radix-ui/react-dropdown-menu";
import { Icon } from "@/components/ui/Icon";
import { useAuth } from "@/providers/AuthProvider";
import { cn } from "@/lib/cn";

const itemClass =
  "flex w-full items-center gap-2 rounded-[6px] px-2.5 py-2 text-sm text-ink outline-none data-[highlighted]:bg-paper";

export function UserMenu() {
  const { user, loading, logout, isSeller, can } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  if (loading) {
    return <span className="h-9 w-20 animate-pulse rounded-[8px] bg-line" />;
  }

  if (!user) {
    return (
      <Link
        href={`/login?next=${encodeURIComponent(pathname)}`}
        className="inline-flex h-9 items-center rounded-[8px] border border-line-strong bg-canvas px-3 text-sm text-ink hover:border-brand/40 hover:text-brand"
      >
        Masuk
      </Link>
    );
  }

  const isAdmin = can("manage_sellers") || can("manage_payouts") || can("manage_products");

  return (
    <DropdownMenu.Root>
      <DropdownMenu.Trigger
        className="inline-flex h-9 items-center gap-2 rounded-[8px] border border-line-strong bg-canvas px-3 text-sm text-ink hover:border-brand/40"
        aria-label="Menu akun"
      >
        <span className="flex size-6 items-center justify-center rounded-full bg-brand-soft text-brand">
          <Icon as={UserRound} size="xs" />
        </span>
        <span className="max-w-28 truncate">{user.name}</span>
        <Icon as={ChevronDown} size="xs" className="text-ink-subtle" />
      </DropdownMenu.Trigger>
      <DropdownMenu.Portal>
        <DropdownMenu.Content
          align="end"
          className="z-50 min-w-52 rounded-[8px] border border-line bg-canvas p-1 shadow-[var(--shadow-e2)]"
        >
          <div className="px-2.5 py-2">
            <p className="text-sm font-medium text-ink">{user.name}</p>
            <p className="truncate text-xs text-ink-subtle">{user.email}</p>
          </div>
          <DropdownMenu.Separator className="my-1 h-px bg-line" />
          <DropdownMenu.Item asChild>
            <Link href="/pesanan" className={cn(itemClass)}>
              <Icon as={ReceiptText} size="xs" />
              Pesanan
            </Link>
          </DropdownMenu.Item>
          <DropdownMenu.Item asChild>
            <Link href="/akun" className={cn(itemClass)}>
              <Icon as={UserRound} size="xs" />
              Akun
            </Link>
          </DropdownMenu.Item>
          {isSeller ? (
            <DropdownMenu.Item asChild>
              <Link href="/seller" className={cn(itemClass)}>
                <Icon as={Store} size="xs" />
                Toko saya
              </Link>
            </DropdownMenu.Item>
          ) : null}
          {isAdmin ? (
            <DropdownMenu.Item asChild>
              <Link href="/admin" className={cn(itemClass)}>
                <Icon as={ShieldCheck} size="xs" />
                Admin
              </Link>
            </DropdownMenu.Item>
          ) : null}
          <DropdownMenu.Separator className="my-1 h-px bg-line" />
          <DropdownMenu.Item
            className={cn(itemClass, "cursor-pointer")}
            onSelect={() => {
              logout();
              router.push("/login");
            }}
          >
            <Icon as={RefreshCw} size="xs" />
            Ganti akun
          </DropdownMenu.Item>
          <DropdownMenu.Item
            className={cn(itemClass, "cursor-pointer text-danger")}
            onSelect={() => {
              logout();
              router.push("/");
            }}
          >
            <Icon as={LogOut} size="xs" />
            Keluar
          </DropdownMenu.Item>
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  );
}
