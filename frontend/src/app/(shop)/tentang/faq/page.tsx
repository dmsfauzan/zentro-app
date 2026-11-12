import type { Metadata } from "next";
import Link from "next/link";
import { AboutNav } from "@/components/about/AboutNav";
import { Card } from "@/components/ui/Card";
import { PageHeader } from "@/components/ui/PageHeader";

export const metadata: Metadata = {
  title: "FAQ",
  description:
    "Pertanyaan yang sering diajukan tentang belanja, pembayaran, pengiriman, refund, dan berjualan di ZENTRO.",
};

const faqs = [
  {
    question: "Apa itu ZENTRO?",
    answer:
      "ZENTRO adalah marketplace multi-penjual. Anda dapat membeli produk dari banyak toko dalam satu keranjang, membayar sekali, lalu melacak pengiriman setiap toko secara terpisah.",
  },
  {
    question: "Bagaimana cara membeli dari beberapa penjual sekaligus?",
    answer:
      "Tambahkan produk dari penjual mana pun ke keranjang, lalu lakukan satu checkout. Pesanan otomatis dipecah menjadi sub-pesanan per penjual dengan satu tagihan.",
  },
  {
    question: "Metode pembayaran apa saja yang didukung?",
    answer:
      "Pembayaran diproses melalui gateway resmi. Metode yang tersedia dapat mencakup transfer virtual account, QRIS, dan dompet digital, tergantung konfigurasi yang aktif.",
  },
  {
    question: "Kapan dana diteruskan ke penjual?",
    answer:
      "Dana ditahan lebih dulu, lalu dilepas ke saldo penjual setelah pesanan selesai sesuai periode penyelesaian (settlement) yang berlaku.",
  },
  {
    question: "Bagaimana cara mengajukan refund?",
    answer:
      "Buka detail pesanan, pilih Ajukan refund pada sub-pesanan terkait, dan isi alasannya. Permintaan ditinjau admin sebelum dana dikembalikan.",
  },
  {
    question: "Bagaimana cara membuka toko?",
    answer:
      "Daftarkan akun, lengkapi data toko dan alamat asal pengiriman, lalu unggah dokumen verifikasi. Toko aktif setelah disetujui admin.",
  },
  {
    question: "Berapa komisi platform?",
    answer:
      "Komisi dihitung dari subtotal produk (tidak termasuk ongkir) dan tercatat per pesanan. Tarif yang berlaku terlihat pada ringkasan komisi penjual.",
  },
  {
    question: "Bagaimana cara melacak pengiriman?",
    answer:
      "Setelah penjual mengunggah nomor resi, status pengiriman dapat dipantau dari halaman detail pesanan atau tautan pelacakan.",
  },
  {
    question: "Apakah data saya aman?",
    answer:
      "ZENTRO tidak menyimpan kredensial kartu pembayaran. Data sensitif dan dokumen penjual disimpan pada penyimpanan privat dengan akses terbatas. Lihat Kebijakan Privasi untuk detail.",
  },
  {
    question: "Bagaimana menghubungi dukungan?",
    answer:
      "Gunakan kanal yang tercantum di halaman Kontak. Sertakan nomor pesanan agar tim dapat membantu lebih cepat.",
  },
];

export default function FaqPage() {
  return (
    <div className="container-page py-10 lg:py-14">
      <AboutNav current="/tentang/faq" />

      <PageHeader
        eyebrow="Tentang"
        title="Pertanyaan umum"
        description="Jawaban singkat seputar belanja, pembayaran, pengiriman, refund, dan berjualan di ZENTRO."
      />

      <div className="mt-8 max-w-3xl space-y-3">
        {faqs.map((faq) => (
          <Card key={faq.question}>
            <details className="group px-5 py-4">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium text-ink">
                {faq.question}
                <span
                  aria-hidden="true"
                  className="text-ink-subtle transition-transform group-open:rotate-45"
                >
                  +
                </span>
              </summary>
              <p className="mt-3 text-sm leading-7 text-ink-muted">{faq.answer}</p>
            </details>
          </Card>
        ))}
      </div>

      <p className="mt-8 text-sm text-ink-muted">
        Belum terjawab?{" "}
        <Link href="/tentang/kontak" className="font-medium text-brand hover:underline">
          Hubungi kami
        </Link>
        .
      </p>
    </div>
  );
}
