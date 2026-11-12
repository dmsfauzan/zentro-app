import type { ReactNode } from "react";
import Link from "next/link";
import { Logo } from "@/components/brand/Logo";

export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col bg-paper">
      <header className="container-page flex h-16 items-center">
        <Link href="/" aria-label="ZENTRO — beranda">
          <Logo heightClass="h-8" />
        </Link>
      </header>
      <main className="flex flex-1 items-center justify-center px-5 py-10">
        <div className="w-full max-w-md">{children}</div>
      </main>
      <footer className="container-page py-6 text-xs text-ink-subtle">
        © ZENTRO. Semua hak dilindungi.
      </footer>
    </div>
  );
}
