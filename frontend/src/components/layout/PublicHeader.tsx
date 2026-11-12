"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { ShoppingCart } from "lucide-react";
import { Logo } from "@/components/brand/Logo";
import { Icon } from "@/components/ui/Icon";
import { SearchInput } from "@/components/ui/SearchInput";
import { UserMenu } from "@/components/layout/UserMenu";
import { NotificationBell } from "@/components/layout/NotificationBell";
import { useCart } from "@/features/cart";
import { useAuth } from "@/providers/AuthProvider";

const navLinks = [
  { href: "/produk", label: "Semua produk" },
  { href: "/produk?sort=newest", label: "Terbaru" },
  { href: "/produk?sort=popular", label: "Terlaris" },
  { href: "/seller/onboarding", label: "Buka toko" },
];

export function PublicHeader() {
  const router = useRouter();
  const { token } = useAuth();
  const { data: cart } = useCart();
  const count = token ? (cart?.summary.item_count ?? 0) : 0;

  return (
    <header className="sticky top-0 z-30 border-b border-line bg-canvas/95 backdrop-blur-[2px]">
      <div className="bg-ink text-white">
        <div className="container-page flex h-9 items-center justify-center text-center text-xs">
          <p className="truncate">
            Satu checkout untuk semua toko · Pengiriman terpisah per penjual
          </p>
        </div>
      </div>

      <div className="container-page flex h-[68px] items-center gap-4 lg:gap-8">
        <Link href="/" className="shrink-0" aria-label="ZENTRO — beranda">
          <Logo heightClass="h-8" />
        </Link>

        <nav className="hidden items-center gap-6 text-sm lg:flex">
          {navLinks.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="text-ink hover:text-brand"
            >
              {link.label}
            </Link>
          ))}
        </nav>

        <div className="hidden min-w-0 flex-1 md:block">
          <SearchInput
            onSubmit={(value) => router.push(`/cari?q=${encodeURIComponent(value)}`)}
          />
        </div>

        <nav className="ml-auto flex items-center gap-1.5">
          {token ? <NotificationBell /> : null}
          <Link
            href="/keranjang"
            className="relative inline-flex size-10 items-center justify-center rounded-full text-ink hover:bg-ink/5"
            aria-label={`Keranjang${count > 0 ? `, ${count} item` : ""}`}
          >
            <Icon as={ShoppingCart} size="md" />
            {count > 0 ? (
              <span className="num absolute right-0.5 top-0.5 flex size-4.5 min-w-4.5 items-center justify-center rounded-full bg-brand px-1 text-[10px] font-semibold text-white">
                {count > 99 ? "99+" : count}
              </span>
            ) : null}
          </Link>
          <UserMenu />
        </nav>
      </div>

      <div className="container-page flex items-center gap-2 pb-2 md:hidden">
        <SearchInput
          onSubmit={(value) => router.push(`/cari?q=${encodeURIComponent(value)}`)}
          className="w-full"
        />
      </div>
    </header>
  );
}
