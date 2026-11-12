"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import {
  Banknote,
  ClipboardList,
  CreditCard,
  LayoutDashboard,
  Package,
  ReceiptText,
  RotateCcw,
  ScrollText,
  ShieldCheck,
  Star,
  Store,
  Tags,
  Ticket,
  Users,
} from "lucide-react";
import { Icon } from "@/components/ui/Icon";
import { PageLoader } from "@/components/ui/Spinner";
import { Logo } from "@/components/brand/Logo";
import { UserMenu } from "@/components/layout/UserMenu";
import { NotificationBell } from "@/components/layout/NotificationBell";
import { useAuth } from "@/providers/AuthProvider";
import { cn } from "@/lib/cn";

const links = [
  { href: "/admin", label: "Ringkasan", icon: LayoutDashboard, exact: true, permission: null },
  { href: "/admin/seller", label: "Penjual", icon: Store, permission: "manage_sellers" },
  { href: "/admin/produk", label: "Produk", icon: Package, permission: "manage_products" },
  { href: "/admin/kategori", label: "Kategori", icon: Tags, permission: "manage_categories" },
  { href: "/admin/ulasan", label: "Ulasan", icon: Star, permission: "manage_reviews" },
  { href: "/admin/pesanan", label: "Pesanan", icon: ClipboardList, permission: "manage_orders" },
  { href: "/admin/pembayaran", label: "Pembayaran", icon: CreditCard, permission: "manage_payments" },
  { href: "/admin/refund", label: "Refund", icon: RotateCcw, permission: "manage_refunds" },
  { href: "/admin/komisi", label: "Komisi", icon: ReceiptText, permission: "manage_commissions" },
  { href: "/admin/payout", label: "Payout", icon: Banknote, permission: "manage_payouts" },
  { href: "/admin/voucher", label: "Voucher", icon: Ticket, permission: "manage_vouchers" },
  { href: "/admin/pengguna", label: "Pengguna", icon: Users, permission: "manage_users" },
  { href: "/admin/audit-log", label: "Audit log", icon: ScrollText, permission: "view_reports" },
];

export function AdminShell({ children }: { children: ReactNode }) {
  const { user, loading, can } = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  const allowed =
    can("manage_sellers") ||
    can("manage_products") ||
    can("manage_categories") ||
    can("manage_reviews") ||
    can("manage_orders") ||
    can("manage_payments") ||
    can("manage_refunds") ||
    can("manage_commissions") ||
    can("manage_payouts") ||
    can("manage_vouchers") ||
    can("manage_users") ||
    can("view_reports");

  useEffect(() => {
    if (!loading && !user) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [loading, user, router, pathname]);

  if (loading) return <PageLoader />;
  if (!user) return null;

  if (!allowed) {
    return (
      <div className="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center gap-3 px-6 text-center">
        <Icon as={ShieldCheck} size="xl" className="text-ink-subtle" />
        <h1 className="text-xl font-semibold text-ink">Tidak berhak</h1>
        <p className="text-sm text-ink-muted">
          Akun ini tidak punya izin untuk membuka area admin.
        </p>
        <Link href="/" className="text-sm font-medium text-brand hover:underline">
          Kembali ke beranda
        </Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-paper">
      <header className="sticky top-0 z-30 border-b border-line bg-paper/95">
        <div className="container-page flex h-16 items-center gap-4">
          <Link href="/" aria-label="ZENTRO">
            <Logo heightClass="h-7" />
          </Link>
          <span className="hidden h-5 w-px bg-line sm:block" />
          <span className="hidden text-sm font-medium text-ink sm:block">Admin</span>
          <div className="ml-auto flex items-center gap-2">
            <NotificationBell />
            <UserMenu />
          </div>
        </div>
      </header>

      <div className="container-page flex flex-col gap-6 py-6 lg:flex-row">
        <aside className="lg:w-52 lg:shrink-0">
          <nav className="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Navigasi admin">
            {links
              .filter((link) => link.permission === null || can(link.permission))
              .map((link) => {
                const active = link.exact ? pathname === link.href : pathname.startsWith(link.href);

                return (
                  <Link
                    key={link.href}
                    href={link.href}
                    aria-current={active ? "page" : undefined}
                    className={cn(
                      "inline-flex h-10 shrink-0 items-center gap-2 rounded-[8px] px-3 text-sm",
                      active
                        ? "bg-canvas font-medium text-ink shadow-[var(--shadow-e1)]"
                        : "text-ink-muted hover:bg-canvas hover:text-ink",
                    )}
                  >
                    <Icon as={link.icon} size="sm" className={active ? "text-brand" : undefined} />
                    {link.label}
                  </Link>
                );
              })}
          </nav>
        </aside>

        <main className="min-w-0 flex-1">{children}</main>
      </div>
    </div>
  );
}
