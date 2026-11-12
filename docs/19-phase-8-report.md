# 19 — Phase 8 Implementation Report (Frontend)

**Date:** 2026-11-11
**Status:** ✅ Selesai & terverifikasi (build/lint/typecheck hijau; 12 E2E Playwright hijau)
**Stack:** Next.js 16.4.5 (App Router, Turbopack) · React 19.3 · TypeScript 5.9 · Tailwind CSS 4
**Scope:** Fondasi frontend, area Customer, Seller, Admin, design system, E2E smoke.

---

## 1. Objective

- Fondasi: layout, UI kit, API client, TanStack Query, auth (Firebase + dev mode), guard route.
- Tiga area berfungsi end-to-end terhadap API Phase 1–7.
- Semua state: loading, error, empty, validasi; responsif; akses kontrol UI.
- Desain konsisten dan tidak generik (“Kios Editorial”), **tanpa emoji**.
- E2E smoke alur utama lulus.

## 2. Keputusan yang Ditetapkan

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Arah desain | **Kios Editorial** — kertas hangat, tinta navy, satu warna sinyal (brand blue), tipografi editorial |
| 2 | UI kit | **Radix headless + styling sendiri** (bukan shadcn default) |
| 3 | Ikon | **Lucide**, stroke 1.5, hanya lewat `components/ui/Icon`; **nol emoji** |
| 4 | Tipografi | Geist Sans (UI), Geist Mono (angka), **Newsreader** (judul marketing) |
| 5 | Warna | brand + ink + paper + semantik; tanpa aksen tambahan |
| 6 | UI gaps | Frontend-only; fitur tanpa endpoint ditandai/ditunda |
| 7 | Auth lokal | **Dev Auth mode** (fake token sesuai `FakeFirebaseTokenVerifier`) + Firebase bila dikonfigurasi |
| 8 | Cache Components | **Dimatikan** untuk MVP (lihat §4 deviasi) |

## 3. Arsitektur

| Aspek | Implementasi |
|---|---|
| Route group | `(shop)`, `(auth)`, `seller`, `admin`, `dev/design` |
| Struktur fitur | `src/features/<domain>` = API + hook TanStack Query + tipe + schema logic |
| API client | `lib/api/client.ts` (`apiFetch`, `apiFetchFull`, timeout, `Idempotency-Key`, multipart, error mapping) |
| Auth | `providers/AuthProvider` (sessionStorage + cookie penanda), `lib/auth/dev-token`, `lib/firebase/client` |
| Guard | `src/proxy.ts` (Next 16, pengganti middleware) + guard klien per area |
| Data | TanStack Query (semua area ber-auth), query key terpusat |
| Form | Validasi HTML + zod tersedia; error 422 dipetakan via toast/field |
| Idempotensi | `checkout` & `payout` memakai key per percobaan |
| State UI | `Skeleton`, `EmptyState`, `Alert`, `PageLoader`, `Toast` |
| Akses | `useAuth().can(permission)` + `SellerShell`/`AdminShell`; authz final backend |

**Total route halaman:** 31 (termasuk `/dev/design`).

## 4. Deviasi & Catatan Teknis

| Deviasi | Alasan | Dampak |
|---|---|---|
| `cacheComponents` dinonaktifkan | Data per-user via TanStack Query; menghindari `blocking-prerender` dan ketergantungan SSR hydrate. Build deterministik tanpa backend saat build | Tidak memakai PPR/`use cache`; dapat diaktifkan lagi di Phase 9 |
| `next/image` tidak dipakai untuk gambar produk | Next 16 memblokir optimasi IP lokal & butuh `remotePatterns` | `<img loading="lazy">` + rasio tetap |
| Backend: `SellerOrderResource` +`created_at` & `shipping_address` | Seller butuh tanggal & alamat pembeli untuk mengirim | Perubahan aditif; 225 test backend tetap hijau |
| Halaman komisi seller tidak dibuat | Belum ada endpoint seller-facing | Menu tidak ditampilkan |
| Admin orders/payments/users/voucher/laporan/audit-log | Endpoint belum ada | Tidak ditampilkan |
| Review produk | Modul review belum ada | Ditunda |
| `/dev/design` | Galeri token & komponen untuk review desain | Non-produksi (404 di production) |

## 5. Halaman yang Dibangun

**Customer**
- Home editorial (hero asimetris + produk unggulan + kategori + terlaris).
- Listing dengan filter kategori/harga/urut + pagination; kategori; pencarian.
- Detail produk: galeri, varian, stok, tambah keranjang/beli sekarang.
- Keranjang: per penjual, ubah qty, hapus, item tidak tersedia.
- Checkout: pilih alamat, preview per penjual, opsi ongkir, buat pesanan idempoten.
- Pembayaran: instruksi (token/VA/redirect), retry, cek status, simulator dev.
- Pesanan: daftar + filter, detail, batalkan, konfirmasi diterima, lacak.
- Akun: profil, alamat CRUD.

**Seller**
- Onboarding: aplikasi toko + unggah dokumen KYC + status.
- Dashboard: KPI (produk, pesanan, omzet, stok tipis), antrean pesanan, dompet.
- Produk: list/filter, buat baru, edit, gambar, varian, stok.
- Inventori: ringkasan + pintasan pengaturan stok.
- Pesanan: list + konfirmasi, detail + kirim (resi).
- Pengiriman: list + dispatch.
- Dompet: saldo + mutasi (filter bucket/type).
- Payout: rekening, ajukan, riwayat, batalkan.

**Admin**
- Dashboard antrean (seller review, payout, refund, komisi).
- Penjual: filter/cari, setujui/tolak/tangguhkan/aktifkan, unduh dokumen.
- Moderasi produk: tayangkan/nonaktifkan/tolak.
- Refund: cari pesanan → pilih sub-pesanan → ajukan; setujui/tolak.
- Komisi: filter status/periode.
- Payout: setujui/tolak/tandai dibayar.

## 6. Design System

- Token Tailwind v4 (`@theme`): paper/canvas/ink/line/brand + semantik + radius + shadow + font display.
- Komponen: Button, Input/Select/Textarea/Field, Card/Panel, Badge/StatusBadge, Alert, Spinner, Skeleton, EmptyState, Pagination, Table, Modal (Radix), Tabs (Radix), PageHeader, Money, QuantityStepper, SearchInput, Toast.
- **Peta status terpusat** (order, payment, shipment, seller order, payout, refund, commission, seller, product) dengan dot + teks.
- Aturan anti-slop: tanpa gradient dekoratif, glassmorphism, kartu bertumpuk, bayangan berlebihan, emoji, ikon dekoratif di judul.
- **Gerbang emoji otomatis:** `scripts/check-emoji.mjs` memindai `src/` dan gagal bila menemukan emoji/glyph; dijalankan di `npm run lint`.
- Galeri review: `/dev/design` (token, tipografi, tombol, badge, form, state).

## 7. Verifikasi

```
Backend : 225 passed (1071 assertions) — termasu perubahan aditif SellerOrderResource
Frontend: npm run lint        -> PASS (ESLint + check-emoji: 0 emoji)
          npm run typecheck   -> PASS
          npm run build       -> PASS (31 route, proxy terdeteksi)
          npx playwright test -> 12 passed
```

E2E (Playwright, Chromium):
- Auth: login dev customer, guard admin menolak customer.
- Katalog: listing → detail produk; filter kategori tersedia.
- Checkout: keranjang kosong & halaman checkout.
- Pesanan: daftar pesanan.
- Seller: dashboard, produk, dompet.
- Admin: dashboard, modul seller, payout.

Data E2E disiapkan lewat API di `e2e/global-setup.ts` (toko demo diajukan + dokumen KTP + disetujui; produk aktif + gambar; alamat buyer) tanpa mengubah kode backend.

## 8. Cara Menjalankan

Backend:
```powershell
cd C:\laragon\www\Zentro\backend
php artisan migrate
php artisan db:seed
php artisan serve   # FIREBASE_FAKE_VERIFIER=true (default lokal)
```

Frontend:
```powershell
cd C:\laragon\www\Zentro\frontend
copy .env.local.example .env.local
npm install
npm run dev         # http://localhost:3000
```

Akun demo: `demo-customer`, `demo-seller`, `demo-admin`, `demo-super-admin` (tombol di halaman masuk).

Kualitas:
```powershell
npm run lint
npm run typecheck
npm run build
npx playwright install chromium
npm run e2e
```

## 9. Batasan yang Diketahui

1. Light mode saja (logo varian gelap belum ada).
2. Review produk, voucher, laporan, admin users/orders/payments/audit-log, dan halaman komisi seller belum ada karena endpoint backend belum tersedia.
3. Gambar produk memakai `<img>` (belum `next/image` + proxy gambar).
4. E2E hanya Chromium dan smoke (bukan regresi penuh/perf).
5. `cacheComponents`/PPR tidak dipakai (dapat diaktifkan Phase 9).
6. Belum ada observability frontend (Sentry/metrics/web-vitals).

## 10. Perbaikan Pasca-Review (2026-11-11)

| Temuan | Perbaikan |
|---|---|
| Hydration error: `<a>` bersarang — `Logo` membungkus dirinya dengan `<Link>`, lalu header membungkus lagi | `Logo` dijadikan **presentational** (tanpa `Link`); wrapper header/layout yang menyediakan tautan. Berlaku untuk PublicHeader, SellerShell, AdminShell, AuthLayout |
| Console error: “Cannot update a component (`Router`) while rendering a different component” | `useUrlFilters` tidak lagi memanggil `router.replace` di dalam updater `setState`; nilai terbaru dilacak lewat `ref`, state di-set murni, navigasi dilakukan setelahnya |
| Link/button **Masuk** terasa tidak berfungsi | Cookie penanda `zentro_auth` bisa tertinggal sementara token sesi sudah hilang, sehingga `proxy.ts` me-redirect `/login` kembali ke `/`. Perbaikan: proxy **tidak** lagi redirect dari `/login`; `AuthProvider` membuang cookie penanda bila tidak ada sesi |
| Login menampilkan **“Failed to fetch”** | Penyebab lingkungan: backend mati, atau backend 500 karena **Redis mati** (rate limiter/cache). Perbaikan UX: pesan jaringan kini menyebut URL API + saran menyalakan backend; halaman login menampilkan alamat API. CORS backend juga mengizinkan `127.0.0.1:3000` dan mengekspos `X-Idempotent-Replayed` |
| Regresi serupa bisa lolos | E2E katalog mendengarkan `console`/`pageerror` dan gagal bila ada pesan `hydration`, nested anchor, atau “Cannot update a component”. Uji filter (ubah urutan) ikut memicu jalur `router.replace` |

Verifikasi ulang: `lint`, `typecheck`, `build` hijau; **12 E2E lulus** (termasuk assertion hydration & state-update).

## 11. Next Phase

**Phase 9 — Hardening & Deployment:** security review OWASP, dependency audit, load test checkout/webhook, observability, backup/restore, Docker + Nginx TLS + CI/CD staging.
