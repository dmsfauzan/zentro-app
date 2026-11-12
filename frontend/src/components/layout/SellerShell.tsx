"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import {
  Banknote,
  LayoutDashboard,
  Package,
  PackageSearch,
  ReceiptText,
  Store,
  Truck,
  Wallet,
} from "lucide-react";
import { Icon } from "@/components/ui/Icon";
import { PageLoader } from "@/components/ui/Spinner";
import { Logo } from "@/components/brand/Logo";
import { UserMenu } from "@/components/layout/UserMenu";
import { NotificationBell } from "@/components/layout/NotificationBell";
import { useAuth } from "@/providers/AuthProvider";
import { cn } from "@/lib/cn";

const links = [
  { href: "/seller", label: "Ringkasan", icon: LayoutDashboard, exact: true },
  { href: "/seller/profil", label: "Profil toko", icon: Store },
  { href: "/seller/pesanan", label: "Pesanan", icon: ReceiptText },
  { href: "/seller/pengiriman", label: "Pengiriman", icon: Truck },
  { href: "/seller/produk", label: "Produk", icon: Package },
  { href: "/seller/inventori", label: "Inventori", icon: PackageSearch },
  { href: "/seller/dompet", label: "Dompet", icon: Wallet },
  { href: "/seller/komisi", label: "Komisi", icon: ReceiptText },
  { href: "/seller/payout", label: "Payout", icon: Banknote },
];

export function SellerShell({ children }: { children: ReactNode }) {
  const { user, loading, isSeller } = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [loading, user, router, pathname]);

  if (loading) return <PageLoader />;
  if (!user) return null;

  if (!isSeller) {
    return (
      <div className="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center gap-3 px-6 text-center">
        <Icon as={Store} size="xl" className="text-ink-subtle" />
        <h1 className="text-xl font-semibold text-ink">Akun ini belum punya toko</h1>
        <p className="text-sm text-ink-muted">
          Daftarkan toko dulu untuk mengelola produk, pesanan, dan pencairan dana.
        </p>
        <Link
          href="/seller/onboarding"
          className="inline-flex h-10 items-center rounded-[8px] bg-brand px-4 text-sm font-medium text-white"
        >
          Buka toko
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
          <span className="hidden text-sm font-medium text-ink sm:block">Penjual</span>
          <div className="ml-auto flex items-center gap-2">
            <NotificationBell />
            <UserMenu />
          </div>
        </div>
      </header>

      <div className="container-page flex flex-col gap-6 py-6 lg:flex-row">
        <aside className="lg:w-56 lg:shrink-0">
          <nav className="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Navigasi penjual">
            {links.map((link) => {
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
