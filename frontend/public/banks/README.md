# Logo Bank & Metode Pembayaran

Aset SVG untuk pemilih metode pembayaran di halaman checkout.

**Sumber:** Wikimedia Commons (lihat daftar di bawah). Logo adalah **merek dagang** masing-masing
bank/penyedia dan dipakai di sini semata untuk menandai metode pembayaran (nominative use).
Ganti dengan aset resmi dari bank/Midtrans bila diperlukan untuk produksi.

| Berkas | Sumber (Wikimedia Commons) |
|---|---|
| `bca.svg` | [File:Bank Central Asia.svg](https://commons.wikimedia.org/wiki/File:Bank_Central_Asia.svg) |
| `bni.svg` | [File:Bank Negara Indonesia logo (2004).svg](https://commons.wikimedia.org/wiki/File:Bank_Negara_Indonesia_logo_(2004).svg) |
| `bri.svg` | [File:BRI 2025 (with full name).svg](https://commons.wikimedia.org/wiki/File:BRI_2025_(with_full_name).svg) |
| `mandiri.svg` | [File:Bank Mandiri logo 2016.svg](https://commons.wikimedia.org/wiki/File:Bank_Mandiri_logo_2016.svg) |
| `permata.svg` | [File:Permata Bank (2024).svg](https://commons.wikimedia.org/wiki/File:Permata_Bank_(2024).svg) |
| `cimb.svg` | [File:CIMB Niaga logo.svg](https://commons.wikimedia.org/wiki/File:CIMB_Niaga_logo.svg) |
| `qris.svg` | [File:Logo QRIS.svg](https://commons.wikimedia.org/wiki/File:Logo_QRIS.svg) |
| `gopay.svg` | [File:Logo Gopay.svg](https://commons.wikimedia.org/wiki/File:Logo_Gopay.svg) |

Dipakai oleh `src/components/payment/{PaymentMethodPicker,PaymentMethodLogo}.tsx` (checkout & halaman
pembayaran) dengan pemetaan kunci metode Midtrans:

| Kunci | Berkas |
|---|---|
| `bca_va`, `bni_va`, `bri_va`, `mandiri_va`, `permata_va`, `cimb_va` | `<bank>.svg` |
| `qris` | `qris.svg` |
| `gopay` | `gopay.svg` |
| `shopeepay` | belum ada aset → ditampilkan sebagai teks |
