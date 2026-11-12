import Link from "next/link";

const links = [
  { href: "/tentang", label: "Tentang" },
  { href: "/tentang/faq", label: "FAQ" },
  { href: "/tentang/kontak", label: "Kontak" },
  { href: "/tentang/kebijakan-privasi", label: "Kebijakan Privasi" },
  { href: "/tentang/syarat-ketentuan", label: "Syarat & Ketentuan" },
];

/** Navigasi lintas halaman "Tentang" agar konsisten di semua sub-halaman. */
export function AboutNav({ current }: { current: string }) {
  return (
    <nav className="mb-8 flex flex-wrap gap-2" aria-label="Navigasi halaman tentang">
      {links.map((link) => {
        const active = link.href === current;

        return (
          <Link
            key={link.href}
            href={link.href}
            aria-current={active ? "page" : undefined}
            className={
              active
                ? "inline-flex h-9 items-center rounded-full bg-ink px-4 text-sm font-medium text-white"
                : "inline-flex h-9 items-center rounded-full border border-line px-4 text-sm text-ink-muted transition-colors hover:border-line-strong hover:text-ink"
            }
          >
            {link.label}
          </Link>
        );
      })}
    </nav>
  );
}
