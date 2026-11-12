import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  BadgeCheck,
  CreditCard,
  Landmark,
  Package,
  ReceiptText,
  RotateCcw,
  ShieldCheck,
  ShoppingBag,
  Store,
  Truck,
  Wallet,
  type LucideIcon,
} from "lucide-react";
import { AboutNav } from "@/components/about/AboutNav";
import { ButtonLink } from "@/components/ui/Button";
import { Card, CardBody } from "@/components/ui/Card";
import { Icon } from "@/components/ui/Icon";
import { APP_TAGLINE } from "@/lib/config";

export const metadata: Metadata = {
  title: "Tentang",
  description:
    "ZENTRO adalah marketplace multi-penjual: satu checkout untuk semua toko, pembayaran aman, pengiriman terpisah per penjual, dan dana penjual yang transparan.",
};

const steps = [
  {
    title: "Kumpulkan lintas toko",
    body: "Masukkan produk dari penjual berbeda ke dalam satu keranjang.",
  },
  {
    title: "Bayar sekali",
    body: "Satu checkout dan satu pembayaran untuk seluruh pesanan.",
  },
  {
    title: "Dikirim per toko",
    body: "Setiap penjual memproses dan mengirim pesanannya sendiri.",
  },
  {
    title: "Terima & beri ulasan",
    body: "Konfirmasi penerimaan, lalu nilai produk dan penjualnya.",
  },
];

const buyerBenefits: { icon: LucideIcon; title: string; body: string }[] = [
  { icon: ShoppingBag, title: "Satu keranjang", body: "Belanja dari banyak penjual tanpa berpindah aplikasi." },
  { icon: Truck, title: "Lacak per toko", body: "Pantau status tiap pengiriman secara terpisah." },
  { icon: ShieldCheck, title: "Pembayaran aman", body: "Diproses lewat gateway pembayaran resmi." },
  { icon: RotateCcw, title: "Refund terlindungi", body: "Ajukan refund; ditinjau dan diproses admin." },
];

const sellerBenefits: { icon: LucideIcon; title: string; body: string }[] = [
  { icon: Store, title: "Buka toko cepat", body: "Daftar, lengkapi verifikasi, lalu mulai berjualan." },
  { icon: Package, title: "Kelola produk & stok", body: "Atur varian, harga, dan inventori di satu tempat." },
  { icon: Wallet, title: "Dompet transparan", body: "Lihat saldo pending, tersedia, dan tercairkan." },
  { icon: ReceiptText, title: "Komisi jelas", body: "Tarif komisi tercatat per pesanan secara otomatis." },
];

const trust: { icon: LucideIcon; title: string; body: string }[] = [
  { icon: BadgeCheck, title: "Penjual terverifikasi", body: "Dokumen identitas diverifikasi sebelum toko aktif." },
  { icon: CreditCard, title: "Pembayaran aman", body: "Transaksi diproses gateway resmi; kredensial kartu tidak disimpan di ZENTRO." },
  { icon: Landmark, title: "Dana terkelola", body: "Dana penjual dilepas bertahap setelah pesanan selesai." },
  { icon: ShieldCheck, title: "Pencatatan & audit", body: "Setiap transaksi tercatat dan aksi admin meninggalkan jejak audit." },
];

const faqs = [
  {
    question: "Apa itu ZENTRO?",
    answer:
      "ZENTRO adalah marketplace multi-penjual. Anda bisa membeli dari banyak toko dalam satu keranjang, membayar sekali, dan tetap melacak pengiriman setiap toko.",
  },
  {
    question: "Bagaimana pembayaran beberapa penjual ditangani?",
    answer:
      "Satu checkout menghasilkan satu tagihan untuk seluruh pesanan. ZENTRO kemudian memisahkan pembagian untuk tiap penjual secara otomatis.",
  },
  {
    question: "Kapan dana diteruskan ke penjual?",
    answer:
      "Dana ditahan lebih dulu, lalu dilepas ke saldo penjual setelah pesanan selesai sesuai periode penyelesaian yang berlaku.",
  },
  {
    question: "Bagaimana cara mengajukan refund?",
    answer:
      "Buka detail pesanan lalu pilih Ajukan refund pada sub-pesanan terkait. Permintaan akan ditinjau admin sebelum dana dikembalikan.",
  },
];

export default function AboutPage() {
  return (
    <div className="container-page py-10 lg:py-14">
      <AboutNav current="/tentang" />

      <section className="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
        <div>
          <p className="text-xs font-medium uppercase tracking-[0.16em] text-ink-subtle">
            Tentang ZENTRO
          </p>
          <h1 className="mt-3 font-display text-[40px] uppercase leading-[0.95] tracking-[0.01em] text-ink sm:text-[56px]">
            Belanja dari banyak toko, satu pembayaran
          </h1>
          <p className="mt-5 max-w-xl text-base leading-7 text-ink-muted">
            ZENTRO menyatukan banyak penjual dalam satu pengalaman belanja.
            Bandingkan produk, bayar sekali, lalu lacak setiap pengiriman —
            sementara tiap penjual mengelola tokonya sendiri. {APP_TAGLINE}.
          </p>

          <div className="mt-7 flex flex-wrap gap-3">
            <ButtonLink href="/produk" size="md">
              Belanja sekarang
            </ButtonLink>
            <ButtonLink href="/seller/onboarding" variant="secondary" size="md">
              Buka toko
            </ButtonLink>
          </div>

          <dl className="mt-10 grid max-w-xl grid-cols-3 gap-4 border-t border-line pt-6">
            <Pillar title="Multi-penjual" subtitle="Satu keranjang" />
            <Pillar title="1 checkout" subtitle="Pembayaran tunggal" />
            <Pillar title="Per toko" subtitle="Pengiriman terpisah" />
          </dl>
        </div>

        <Card className="overflow-hidden">
          <CardBody className="space-y-5">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-ink-subtle">
              Prinsip kami
            </p>
            <ul className="space-y-4">
              {[
                "Harga, total, dan komisi dihitung di server — bukan dari perangkat.",
                "Stok diamankan dengan reservasi agar tidak overselling.",
                "Dana penjual dipisahkan dan dilepas bertahap, bukan sekaligus.",
              ].map((item) => (
                <li key={item} className="flex gap-3 text-sm leading-6 text-ink-muted">
                  <Icon as={BadgeCheck} size="sm" className="mt-0.5 shrink-0 text-brand" />
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          </CardBody>
        </Card>
      </section>

      <section className="mt-16">
        <SectionHead title="Cara kerja" description="Empat langkah dari keranjang sampai ulasan." />
        <ol className="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {steps.map((step, index) => (
            <li key={step.title}>
              <Card className="h-full">
                <CardBody className="space-y-2">
                  <span className="font-display text-3xl leading-none text-brand">
                    {String(index + 1).padStart(2, "0")}
                  </span>
                  <h3 className="text-base font-semibold text-ink">{step.title}</h3>
                  <p className="text-sm leading-6 text-ink-muted">{step.body}</p>
                </CardBody>
              </Card>
            </li>
          ))}
        </ol>
      </section>

      <section className="mt-16 grid gap-4 lg:grid-cols-2">
        <BenefitPanel
          eyebrow="Untuk pembeli"
          title="Belanja tenang, dari mana saja"
          items={buyerBenefits}
        />
        <BenefitPanel
          eyebrow="Untuk penjual"
          title="Kelola toko dan pencairan"
          items={sellerBenefits}
        />
      </section>

      <section className="mt-16">
        <SectionHead
          title="Keamanan & kepercayaan"
          description="Fondasi yang membuat transaksi lintas penjual tetap aman."
        />
        <div className="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {trust.map((item) => (
            <Card key={item.title} className="h-full">
              <CardBody className="space-y-2">
                <span className="flex size-9 items-center justify-center rounded-full bg-paper text-brand">
                  <Icon as={item.icon} size="md" />
                </span>
                <h3 className="text-base font-semibold text-ink">{item.title}</h3>
                <p className="text-sm leading-6 text-ink-muted">{item.body}</p>
              </CardBody>
            </Card>
          ))}
        </div>
      </section>

      <section className="mt-16">
        <SectionHead title="Pertanyaan umum" description="Jawaban singkat untuk hal yang paling sering ditanyakan." />
        <div className="mt-7 grid gap-3 lg:grid-cols-2">
          {faqs.map((faq) => (
            <Card key={faq.question}>
              <CardBody className="space-y-1.5">
                <h3 className="text-sm font-semibold text-ink">{faq.question}</h3>
                <p className="text-sm leading-6 text-ink-muted">{faq.answer}</p>
              </CardBody>
            </Card>
          ))}
        </div>
        <div className="mt-5">
          <Link
            href="/tentang/faq"
            className="inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline"
          >
            Lihat semua pertanyaan
            <Icon as={ArrowRight} size="xs" />
          </Link>
        </div>
      </section>
    </div>
  );
}

function Pillar({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <div>
      <dt className="text-sm font-semibold text-ink">{title}</dt>
      <dd className="mt-1 text-xs uppercase tracking-[0.14em] text-ink-subtle">
        {subtitle}
      </dd>
    </div>
  );
}

function SectionHead({ title, description }: { title: string; description: string }) {
  return (
    <div className="max-w-2xl">
      <h2 className="font-display text-3xl uppercase leading-none tracking-[0.01em] text-ink sm:text-4xl">
        {title}
      </h2>
      <p className="mt-3 text-sm leading-7 text-ink-muted">{description}</p>
    </div>
  );
}

function BenefitPanel({
  eyebrow,
  title,
  items,
}: {
  eyebrow: string;
  title: string;
  items: { icon: LucideIcon; title: string; body: string }[];
}) {
  return (
    <Card className="h-full">
      <CardBody className="space-y-5">
        <div>
          <p className="text-xs font-medium uppercase tracking-[0.16em] text-ink-subtle">
            {eyebrow}
          </p>
          <h2 className="mt-2 font-display text-2xl uppercase leading-none tracking-[0.01em] text-ink">
            {title}
          </h2>
        </div>
        <ul className="grid gap-4 sm:grid-cols-2">
          {items.map((item) => (
            <li key={item.title} className="space-y-1.5">
              <span className="flex size-9 items-center justify-center rounded-full bg-paper text-brand">
                <Icon as={item.icon} size="md" />
              </span>
              <h3 className="text-sm font-semibold text-ink">{item.title}</h3>
              <p className="text-sm leading-6 text-ink-muted">{item.body}</p>
            </li>
          ))}
        </ul>
      </CardBody>
    </Card>
  );
}
