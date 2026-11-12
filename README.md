# ZENTRO — *Everything in One Place*

Platform **marketplace multi-vendor**: banyak seller, satu checkout untuk banyak seller, satu transaksi pembayaran per checkout, pengiriman terpisah per seller, komisi persentase, wallet & payout seller, serta kontrol admin penuh.

> **Status saat ini:** **Phase 8.5 (Feature Gaps + Operational Baseline) selesai & terverifikasi.** **245 test backend** hijau + **15 E2E Playwright** hijau; tiga area (Customer, Seller, Admin) berjalan terhadap API, desain mengikuti sistem “Kios Editorial” (tanpa emoji, ikon Lucide, token warna terbatas). Voucher, ulasan, notifikasi in-app, dan read-only admin sudah aktif. Baseline operasional (CI, security headers, Docker scaffold, backup/restore, observability) terpasang **tanpa** kredensial produksi.
>
> Laporan: [Phase 1](docs/11-phase-1-report.md) · [Phase 2](docs/13-phase-2-report.md) · [Phase 3](docs/14-phase-3-report.md) · [Phase 4](docs/15-phase-4-report.md) · [Phase 5](docs/16-phase-5-report.md) · [Phase 6](docs/17-phase-6-report.md) · [Phase 7](docs/18-phase-7-report.md) · [Phase 8](docs/19-phase-8-report.md) · [Phase 8.5 plan](docs/20-phase-8.5-plan.md) · [Phase 8.5 report](docs/21-phase-8.5-report.md)
>
> **Keputusan kunci yang sudah ditetapkan (2026-11-09):**
> - Model dana: **escrow platform + payout terjadwal**.
> - Payment gateway: **Midtrans** — integrasi **Core API tanpa Snap** (QRIS, e-wallet, Virtual Account semua bank); detail: [docs/22](docs/22-midtrans-integration.md).
> - Komisi: **subtotal produk saja (ongkir tidak termasuk)**, default **5%**.
> Detail: [docs/10 §B-0](docs/10-assumptions-and-decisions.md).

---

## Stack (terverifikasi pada lingkungan ini)

| Lapisan | Teknologi |
|---|---|
| Backend | Laravel 13 · PHP 8.3.32 |
| Frontend | Next.js 16 · React 19 · TypeScript 5.9 · Tailwind CSS 4 |
| Database | MySQL 8.0 |
| Cache/Queue | Redis (Predis) |
| Auth | Firebase Authentication |
| Storage | S3-compatible |
| Payment | Payment Gateway (via adapter) |
| Test | Pest 4.7.8 |
| Deploy awal | Docker + Nginx |

---

## Dokumen Arsitektur

| # | Dokumen | Isi |
|---|---|---|
| 00 | [project inspection](docs/00-inspection-report.md) | Kondisi proyek & versi toolchain |
| 01 | [Software Architecture Document](docs/01-architecture.md) | Arsitektur, modul, prinsip, ADR |
| 02 | [ERD](docs/02-erd.md) | Entity Relationship Diagram per domain |
| 03 | [Data Dictionary](docs/03-data-dictionary.md) | Definisi lengkap 45 tabel |
| 04 | [Constraints & Indexes](docs/04-constraints-and-indexes.md) | FK, unique, index, CHECK, urutan migrasi |
| 05 | [State Machines](docs/05-state-machines.md) | Lifecycle order/payment/seller/shipment/payout |
| 06 | [API Specification](docs/06-api-spec.md) | 60+ endpoint, envelope, error, webhook |
| 07 | [Folder Structure](docs/07-folder-structure.md) | Struktur Laravel & Next.js |
| 08 | [Implementation Roadmap](docs/08-implementation-roadmap.md) | 9 fase teknis + exit criteria |
| 09 | [Risk Register](docs/09-risk-register.md) | Risiko keamanan, finansial, teknis |
| 10 | [Assumptions & Decisions](docs/10-assumptions-and-decisions.md) | Asumsi + keputusan bisnis yang dibutuhkan |
| 11 | [Phase 1 Report](docs/11-phase-1-report.md) | Implementasi fondasi & hasil verifikasi |
| 12 | [Brand Assets & Logo](docs/12-brand-assets.md) | Aset logo, palet warna, aturan pemakaian |
| 13 | [Phase 2 Report](docs/13-phase-2-report.md) | Seller management & hasil verifikasi |
| 14 | [Phase 3 Report](docs/14-phase-3-report.md) | Catalog & inventory + hasil verifikasi |
| 15 | [Phase 4 Report](docs/15-phase-4-report.md) | Cart & checkout + uji konkurensi |
| 16 | [Phase 5 Report](docs/16-phase-5-report.md) | Payment, webhook idempotent & commit stok |
| 17 | [Phase 6 Report](docs/17-phase-6-report.md) | Shipping, tracking & agregasi status order |
| 18 | [Phase 7 Report](docs/18-phase-7-report.md) | Komisi, wallet, payout, refund & rekonsiliasi |
| 19 | [Phase 8 Report](docs/19-phase-8-report.md) | Frontend Customer/Seller/Admin + design system |
| 20 | [Phase 8.5 Plan](docs/20-phase-8.5-plan.md) | Rencana feature gaps + baseline operasional |
| 21 | [Phase 8.5 Report](docs/21-phase-8.5-report.md) | Hasil voucher, ulasan, notifikasi, read-only admin, ops |
| 22 | [Midtrans Integration](docs/22-midtrans-integration.md) | Kredensial, metode, webhook, ngrok, checklist produksi |

---

## Struktur Repo

```
Zentro/
├─ backend/                  Laravel API (Modular Monolith)
├─ frontend/                 Next.js 16 (Customer, Seller, Admin)
├─ docs/                     Dokumen arsitektur & laporan fase
├─ scripts/                  scan-secrets.mjs, backup.ps1, restore.ps1
├─ docker/nginx/             Reverse proxy staging
├─ docker-compose.staging.yml
├─ .github/workflows/ci.yml  CI (Pint, Pest, lint, typecheck, build, secret scan)
└─ README.md
```

---

## Operasional (Phase 8.5)

| Aspek | Cara |
|---|---|
| Test backend | `cd backend && php artisan test` (245 test) |
| Gaya kode backend | `cd backend && vendor/bin/pint --test` |
| Lint/typecheck/build FE | `cd frontend && npm run lint && npm run typecheck && npm run build` |
| E2E | `cd frontend && npx playwright test` (butuh backend di `:8000`; jalankan dengan `CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync` bila Redis tak tersedia) |
| Backup DB + storage | `pwsh scripts/backup.ps1` (hasil di `backups/`, tak di-commit) |
| Restore | `pwsh scripts/restore.ps1 -SqlFile backups/zentro-db-*.sql -StorageZip backups/zentro-storage-*.zip` |
| Secret scan | `node scripts/scan-secrets.mjs` |
| Health check | `GET /up` |
| Docker staging (belum go-live) | `docker compose -f docker-compose.staging.yml up --build` |

Palet & aturan ikon: tanpa emoji (`node scripts/check-emoji.mjs`). Driver pembayaran/pengiriman tetap `fake`/`manual` sampai kredensial produksi tersedia.

---

## URL Penting (lokal)

| URL | Isi |
|---|---|
| `http://127.0.0.1:8000/` | Deskriptor JSON layanan API (bukan halaman HTML) |
| `http://127.0.0.1:8000/api/v1/...` | Endpoint API (Bearer Firebase ID Token) |
| `http://127.0.0.1:8000/up` | Health check |
| `http://127.0.0.1:8000/docs/api` | OpenAPI UI (hanya environment `local`) |
| `http://localhost:3000` | Frontend ZENTRO (Next.js, `npm run dev`) |

> Backend bersifat **API-only**. Root `/` sengaja mengembalikan JSON, bukan tampilan marketplace/admin — UI ada di frontend (port 3000).

---

## Prinsip Utama

- Modular monolith (bukan microservices) sampai skala menuntut.
- Firebase hanya identity provider; semua otorisasi & data transaksi di Laravel + MySQL.
- Integer rupiah untuk semua nilai uang; ledger wallet immutable.
- Transaksi database + row lock untuk mencegah overselling & double-pay.
- Idempotency untuk mutasi kritis & webhook.
- Snapshot data pada order; komisi memakai snapshot rate.
- Secure by default: verifikasi token penuh, RBAC dari DB, audit log.

---

## Langkah Berikutnya

1. Tinjau laporan [Phase 8.5](docs/21-phase-8.5-report.md) (feature gaps + baseline operasional).
2. **Midtrans sandbox sudah aktif & terverifikasi** (Core API tanpa Snap) — panduan lengkap: [docs/22](docs/22-midtrans-integration.md).
3. **Phase 9 (Hardening & Deployment) diblokir** sampai kredensial **Midtrans produksi** dan **Firebase produksi** tersedia.
4. Setelah kredensial ada: isi `MIDTRANS_*` & `FIREBASE_CREDENTIALS` produksi, Notification URL publik, lalu jalankan load test checkout/webhook dan staging production-like.

> Keputusan yang sudah ditetapkan tercatat di [docs/10 §B-0](docs/10-assumptions-and-decisions.md).
