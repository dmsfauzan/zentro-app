# 12 — Brand Assets & Logo Usage

**Brand:** ZENTRO — *Everything in One Place*
**Source asset:** logo resmi yang diberikan pengguna (`logo.jpg`, 1408 × 768 px).

---

## 1. Aset yang Digunakan

| File | Ukuran | Kegunaan |
|---|---|---|
| `frontend/public/brand/zentro-logo.png` | 985 × 300 | **Lockup penuh dengan latar transparan** (dipakai header) |
| `frontend/public/brand/zentro-logo.jpg` | 1408 × 768 | Logo sumber asli (arsip; latar terang) |
| `frontend/public/brand/zentro-mark.png` | 512 × 512 | Mark "Z" (transparan) untuk header ringkas / komponen `<LogoMark />` |
| `frontend/src/app/icon.png` | 512 × 512 | Favicon modern — **mark "Z"**, latar transparan |
| `frontend/src/app/apple-icon.png` | 180 × 180 | Apple touch icon — mark "Z", transparan |
| `frontend/src/app/favicon.ico` | 16/32/48 multi-size | Favicon legacy & `/favicon.ico` — mark "Z", transparan |

Komponen: `frontend/src/components/brand/Logo.tsx` → `<Logo />` dan `<LogoMark />`.

## 2. Palet Warna (diambil dari logo)

| Token | Hex | Pemakaian |
|---|---|---|
| `brand` | `#0875E2` | Warna utama aksi/CTA (gradien mark: `#0283FA` → `#0062D1`) |
| `brand-strong` | `#0062D1` | Hover/active |
| `brand-light` | `#35A3FF` | Aksen ringan |
| `navy` | `#052440` | Teks utama (wordmark) |
| `surface` | `#F6F7F6` | Latar terang (mengikuti latar logo) |

Token didefinisikan di `frontend/src/app/globals.css` (`@theme inline`), sehingga tersedia sebagai utility Tailwind: `bg-brand`, `text-navy`, `border-navy/10`, dst.

## 3. Aturan Penggunaan

1. Gunakan **lockup penuh** untuk header/halaman brand; gunakan **mark saja** untuk ruang sempit.
2. Jangan mengubah warna, memiringkan, atau meregangkan logo (selalu pertahankan rasio).
3. Beri ruang kosong (clear space) minimal setinggi huruf "Z" di sekeliling logo.
4. Ukuran minimum lockup digital: tinggi ±32 px.
5. Latar yang disarankan: terang (`surface`/putih). Untuk latar gelap, gunakan varian logo terang (belum tersedia).
6. Jangan menempatkan logo di atas foto/pattern yang mengurangi keterbacaan.

## 4. Keterbatasan Saat Ini

**Keputusan aset ikon:** favicon memakai **mark "Z"**, bukan lockup penuh, karena lockup berasio ±3,3:1 akan praktis tak terbaca pada 16–32 px. Lockup penuh tetap dipakai untuk header/halaman.
- Mark dibuat dengan latar **transparan** (background JPG di-key out), sehingga tidak muncul kotak/seam di tab browser.
- Bagian dalam huruf "Z" bersifat *knockout* (transparan) — mengikuti latar tab. Ini normal untuk logo monogram.
- Halaman Phase 1 masih **light-only** karena wordmark berwarna navy (butuh latar terang); varian logo terang (wordmark putih) diperlukan untuk dark mode (Phase 8).
- **Update Phase 8:** UI tetap **light-only** (varian logo gelap belum tersedia); tipografi memakai **Geist** (UI) + **Geist Mono** (angka) + **Newsreader** (judul marketing).
- Jika tersedia **SVG/PNG transparan** resolusi tinggi, aset raster ini sebaiknya diganti agar tajam di semua ukuran.

## 5. Catatan Teknis

- `next/image` dipakai untuk optimasi & mencegah layout shift.
- Next.js 16 (Cache Components) melarang nilai dinamis seperti `new Date()` saat prerender — footer memakai teks statis.
- Ikon didaftarkan lewat konvensi file (`src/app/icon.png`, `src/app/apple-icon.png`), bukan `metadata.icons`.
