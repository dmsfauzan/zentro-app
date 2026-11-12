import type { Metadata } from "next";
import Link from "next/link";
import { Clock, Mail, MessageCircle, Store } from "lucide-react";
import { AboutNav } from "@/components/about/AboutNav";
import { Card, CardBody } from "@/components/ui/Card";
import { Icon } from "@/components/ui/Icon";
import { PageHeader } from "@/components/ui/PageHeader";

export const metadata: Metadata = {
  title: "Kontak",
  description: "Hubungi tim ZENTRO untuk bantuan pesanan, pembayaran, pengiriman, atau berjualan.",
};

const channels = [
  {
    icon: Mail,
    title: "Bantuan umum",
    value: "halo@zentro.id",
    href: "mailto:halo@zentro.id",
    note: "Pertanyaan seputar akun, pesanan, dan pembayaran.",
  },
  {
    icon: MessageCircle,
    title: "Refund & sengketa",
    value: "bantuan@zentro.id",
    href: "mailto:bantuan@zentro.id",
    note: "Sertakan nomor pesanan agar penanganan lebih cepat.",
  },
  {
    icon: Store,
    title: "Penjual",
    value: "penjual@zentro.id",
    href: "mailto:penjual@zentro.id",
    note: "Verifikasi toko, komisi, dan pencairan dana.",
  },
];

export default function ContactPage() {
  return (
    <div className="container-page py-10 lg:py-14">
      <AboutNav current="/tentang/kontak" />

      <PageHeader
        eyebrow="Tentang"
        title="Hubungi ZENTRO"
        description="Pilih kanal yang sesuai. Kami berusaha membalas pada hari kerja."
      />

      <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {channels.map((channel) => (
          <Card key={channel.title} className="h-full">
            <CardBody className="space-y-2">
              <span className="flex size-9 items-center justify-center rounded-full bg-paper text-brand">
                <Icon as={channel.icon} size="md" />
              </span>
              <h2 className="text-base font-semibold text-ink">{channel.title}</h2>
              <Link
                href={channel.href}
                className="inline-block text-sm font-medium text-brand hover:underline"
              >
                {channel.value}
              </Link>
              <p className="text-sm leading-6 text-ink-muted">{channel.note}</p>
            </CardBody>
          </Card>
        ))}
      </div>

      <Card className="mt-6">
        <CardBody className="flex flex-wrap items-center gap-3 text-sm text-ink-muted">
          <span className="flex size-9 items-center justify-center rounded-full bg-paper text-brand">
            <Icon as={Clock} size="md" />
          </span>
          <div>
            <p className="font-medium text-ink">Jam layanan</p>
            <p>Senin–Jumat, 09.00–17.00 WIB. Di luar jam tersebut, pesan akan dibalas pada hari kerja berikutnya.</p>
          </div>
        </CardBody>
      </Card>

      <p className="mt-8 text-sm text-ink-muted">
        Mungkin jawabannya sudah ada di{" "}
        <Link href="/tentang/faq" className="font-medium text-brand hover:underline">
          FAQ
        </Link>
        .
      </p>
    </div>
  );
}
