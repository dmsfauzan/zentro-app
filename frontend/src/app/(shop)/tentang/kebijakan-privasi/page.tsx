import type { Metadata } from "next";
import Link from "next/link";
import { LegalDocument } from "@/components/legal/LegalDocument";

export const metadata: Metadata = {
  title: "Kebijakan Privasi",
  description:
    "Bagaimana ZENTRO mengumpulkan, menggunakan, dan melindungi data pengguna serta penjual.",
};

export default function PrivacyPage() {
  return (
    <LegalDocument
      current="/tentang/kebijakan-privasi"
      title="Kebijakan Privasi"
      description="Bagaimana ZENTRO mengumpulkan, menggunakan, dan melindungi data Anda."
      updatedAt="10 November 2026"
      intro="Dokumen ini menjelaskan praktik pengelolaan data di platform ZENTRO. Versi ini bersifat awal dan dapat diperbarui seiring perubahan layanan."
      sections={[
        {
          heading: "1. Data yang kami kumpulkan",
          bullets: [
            "Data akun: nama, alamat email, nomor telepon, dan identitas masuk.",
            "Data transaksi: pesanan, pembayaran, pengiriman, dan riwayat refund.",
            "Data penjual: informasi toko, alamat asal pengiriman, dan dokumen verifikasi.",
            "Data teknis: alamat IP, perangkat, dan catatan aktivitas untuk keamanan.",
          ],
        },
        {
          heading: "2. Tujuan penggunaan",
          bullets: [
            "Memproses pesanan, pembayaran, pengiriman, dan refund.",
            "Menjalankan verifikasi penjual dan pencegahan penyalahgunaan.",
            "Memberikan dukungan, notifikasi transaksi, dan peningkatan layanan.",
            "Memenuhi kewajiban hukum yang berlaku.",
          ],
        },
        {
          heading: "3. Pembagian data",
          paragraphs: [
            "Data dibagikan hanya sebatas yang diperlukan: kepada penjual terkait untuk memproses pesanan, kepada penyedia pembayaran dan pengiriman untuk menyelesaikan transaksi, serta kepada otoritas bila diwajibkan hukum.",
          ],
        },
        {
          heading: "4. Penyimpanan & keamanan",
          paragraphs: [
            "ZENTRO tidak menyimpan kredensial kartu pembayaran. Dokumen sensitif penjual disimpan pada penyimpanan privat dengan akses terbatas, dan akses administratif dicatat dalam audit log.",
          ],
        },
        {
          heading: "5. Hak Anda",
          bullets: [
            "Mengakses dan memperbarui data profil melalui halaman akun.",
            "Meminta informasi mengenai pemrosesan data Anda.",
            "Mengajukan pertanyaan atau keberatan melalui kanal kontak resmi.",
          ],
        },
        {
          heading: "6. Retensi & perubahan",
          paragraphs: [
            "Data disimpan selama diperlukan untuk operasional dan kepatuhan hukum. Kebijakan ini dapat diperbarui; perubahan penting akan diinformasikan melalui platform.",
          ],
        },
      ]}
      footer={
        <p className="text-sm text-ink-muted">
          Pertanyaan tentang privasi?{" "}
          <Link href="/tentang/kontak" className="font-medium text-brand hover:underline">
            Hubungi kami
          </Link>
          .
        </p>
      }
    />
  );
}
