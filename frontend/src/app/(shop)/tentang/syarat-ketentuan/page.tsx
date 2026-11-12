import type { Metadata } from "next";
import Link from "next/link";
import { LegalDocument } from "@/components/legal/LegalDocument";

export const metadata: Metadata = {
  title: "Syarat & Ketentuan",
  description:
    "Ketentuan penggunaan platform ZENTRO bagi pembeli dan penjual.",
};

export default function TermsPage() {
  return (
    <LegalDocument
      current="/tentang/syarat-ketentuan"
      title="Syarat & Ketentuan"
      description="Ketentuan penggunaan platform ZENTRO bagi pembeli dan penjual."
      updatedAt="10 November 2026"
      intro="Dengan menggunakan ZENTRO, Anda menyetujui ketentuan ini. Versi ini bersifat awal dan dapat diperbarui seiring perubahan layanan."
      sections={[
        {
          heading: "1. Penerimaan ketentuan",
          paragraphs: [
            "ZENTRO menyediakan platform yang mempertemukan pembeli dan penjual. Dengan mengakses atau menggunakan layanan, Anda menyetujui ketentuan yang berlaku.",
          ],
        },
        {
          heading: "2. Akun",
          bullets: [
            "Anda bertanggung jawab menjaga kerahasiaan akses akun.",
            "Data yang didaftarkan harus benar dan diperbarui bila berubah.",
            "ZENTRO dapat menonaktifkan akun yang melanggar ketentuan.",
          ],
        },
        {
          heading: "3. Kewajiban penjual",
          bullets: [
            "Menyediakan informasi produk, harga, dan stok yang akurat.",
            "Memenuhi verifikasi yang diminta sebelum toko aktif.",
            "Memproses dan mengirim pesanan sesuai waktu yang wajar.",
            "Mematuhi ketentuan komisi, refund, dan pencairan dana.",
          ],
        },
        {
          heading: "4. Kewajiban pembeli",
          bullets: [
            "Memberikan data pengiriman yang benar.",
            "Menyelesaikan pembayaran sesuai tagihan checkout.",
            "Menggunakan fitur refund secara wajar dan jujur.",
          ],
        },
        {
          heading: "5. Pembayaran, komisi, dan pencairan",
          paragraphs: [
            "Pembayaran diproses melalui gateway resmi. Komisi dihitung dari subtotal produk dan dicatat per pesanan. Dana penjual dilepas bertahap setelah pesanan selesai sesuai periode penyelesaian yang berlaku.",
          ],
        },
        {
          heading: "6. Pengiriman & penyelesaian",
          paragraphs: [
            "Setiap penjual mengirim pesanannya sendiri. Pesanan dianggap selesai setelah diterima pembeli atau melewati periode penyelesaian otomatis yang berlaku.",
          ],
        },
        {
          heading: "7. Refund & pembatalan",
          paragraphs: [
            "Pembatalan sebelum pembayaran melepas reservasi stok. Refund diajukan melalui detail pesanan dan ditinjau admin; hasilnya mengikuti status pembayaran dan kebijakan yang berlaku.",
          ],
        },
        {
          heading: "8. Larangan",
          bullets: [
            "Menjual barang ilegal atau melanggar hak pihak lain.",
            "Memanipulasi harga, ulasan, atau transaksi.",
            "Mengakses sistem secara tidak sah atau mengganggu layanan.",
          ],
        },
        {
          heading: "9. Batasan tanggung jawab",
          paragraphs: [
            "ZENTRO bertindak sebagai platform. Tanggung jawab atas produk dan pemenuhan pesanan berada pada penjual terkait, sepanjang diizinkan hukum yang berlaku.",
          ],
        },
        {
          heading: "10. Perubahan & hukum yang berlaku",
          paragraphs: [
            "Ketentuan ini dapat diperbarui sewaktu-waktu. Penggunaan berkelanjutan setelah pembaruan berarti Anda menyetujui versi terbaru. Ketentuan tunduk pada hukum yang berlaku di Indonesia.",
          ],
        },
      ]}
      footer={
        <p className="text-sm text-ink-muted">
          Ada pertanyaan mengenai ketentuan ini?{" "}
          <Link href="/tentang/kontak" className="font-medium text-brand hover:underline">
            Hubungi kami
          </Link>
          .
        </p>
      }
    />
  );
}
