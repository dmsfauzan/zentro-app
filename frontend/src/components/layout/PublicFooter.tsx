import Link from "next/link";
import { Logo } from "@/components/brand/Logo";
import { ButtonLink } from "@/components/ui/Button";
import { APP_TAGLINE } from "@/lib/config";

export function PublicFooter() {
  return (
    <footer className="mt-auto border-t border-line bg-canvas">
      <div className="container-page space-y-10 py-12">
        <div className="flex flex-col items-start justify-between gap-5 rounded-[20px] bg-ink px-6 py-8 text-white sm:px-10 sm:py-10 lg:flex-row lg:items-center">
          <div>
            <h2 className="font-display text-3xl uppercase leading-none tracking-[0.01em] sm:text-4xl">
              Belanja lintas toko, satu pembayaran
            </h2>
            <p className="mt-2 max-w-xl text-sm text-white/70">
              {APP_TAGLINE}. Bandingkan produk dari banyak penjual, bayar sekali,
              lacak tiap pengiriman.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <ButtonLink href="/produk" className="bg-white text-ink hover:bg-white/90">
              Mulai belanja
            </ButtonLink>
            <ButtonLink
              href="/seller/onboarding"
              variant="secondary"
              className="border-white/30 bg-transparent text-white hover:border-white hover:text-white"
            >
              Buka toko
            </ButtonLink>
          </div>
        </div>

        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-6">
          <div className="lg:col-span-2">
            <Logo heightClass="h-8" />
            <p className="mt-2 max-w-xs text-sm text-ink-muted">
              Marketplace multi-penjual: satu keranjang, pengiriman per toko, dan
              dana penjual yang transparan.
            </p>
          </div>

          <FooterColumn
            title="Belanja"
            links={[
              { href: "/produk", label: "Semua produk" },
              { href: "/produk?sort=newest", label: "Produk terbaru" },
              { href: "/produk?sort=popular", label: "Terlaris" },
              { href: "/cari", label: "Cari produk" },
            ]}
          />

          <FooterColumn
            title="Jualan di ZENTRO"
            links={[
              { href: "/seller/onboarding", label: "Buka toko" },
              { href: "/tentang", label: "Tentang ZENTRO" },
              { href: "/tentang/faq", label: "Komisi & pencairan" },
              { href: "/tentang/kontak", label: "Hubungi tim penjual" },
            ]}
          />

          <FooterColumn
            title="Bantuan"
            links={[
              { href: "/pesanan", label: "Pesanan saya" },
              { href: "/keranjang", label: "Keranjang" },
              { href: "/akun/alamat", label: "Alamat pengiriman" },
              { href: "/akun", label: "Akun" },
            ]}
          />

          <FooterColumn
            title="Perusahaan"
            links={[
              { href: "/tentang", label: "Tentang" },
              { href: "/tentang/faq", label: "FAQ" },
              { href: "/tentang/kontak", label: "Kontak" },
              { href: "/tentang/kebijakan-privasi", label: "Kebijakan Privasi" },
              { href: "/tentang/syarat-ketentuan", label: "Syarat & Ketentuan" },
            ]}
          />
        </div>
      </div>

      <div className="border-t border-line">
        <div className="container-page flex flex-col gap-2 py-5 text-xs text-ink-subtle sm:flex-row sm:items-center sm:justify-between">
          <span>© ZENTRO. Semua hak dilindungi.</span>
          <span className="flex items-center gap-3">
            <span>Pembayaran aman</span>
            <span aria-hidden="true">·</span>
            <span>{APP_TAGLINE}</span>
          </span>
        </div>
      </div>
    </footer>
  );
}

function FooterColumn({
  title,
  links,
}: {
  title: string;
  links: { href: string; label: string }[];
}) {
  return (
    <div>
      <p className="text-xs font-medium uppercase tracking-[0.14em] text-ink-subtle">
        {title}
      </p>
      <ul className="mt-3 space-y-2.5 text-sm text-ink-muted">
        {links.map((link) => (
          <li key={link.href}>
            <Link href={link.href} className="hover:text-brand">
              {link.label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
