# 21 — Phase 8.5 Report (Feature Gaps + Operational Baseline)

**Status:** ✅ SELESAI — 245 test backend hijau, 15 E2E Playwright hijau.
**Prasyarat:** Phase 1–8 selesai. **Rencana:** [20-phase-8.5-plan.md](20-phase-8.5-plan.md).
**Catatan:** Phase 9 **belum dimulai** (diblokir sampai kredensial Midtrans/Firebase produksi tersedia).
Semua driver tetap `fake`/`manual`; tidak ada kredensial produksi yang dipakai.

---

## Ringkasan

Phase 8.5 menutup celah fungsional Phase 8 dan memasang baseline operasional **tanpa deploy**:

| Area | Hasil |
|---|---|
| Voucher | Aktif end-to-end (validasi, alokasi, basis komisi, UI checkout + admin) |
| Ulasan produk | Order `completed` → 1 ulasan/item, auto-publish, recompute rating produk & toko |
| Notifikasi in-app | Trigger di 5 aksi domain; lonceng + halaman `/notifikasi` |
| Admin read-only | `/admin/orders`, `/admin/payments`, `/admin/audit-logs` + `/seller/commissions` |
| Operasional | CI, security headers, Docker scaffold, backup/restore, observability |

Test baru: **20 backend** (voucher 7, ulasan 5, notifikasi 4, admin read-only 4) + **3 E2E** smoke.
Total: **245 backend** (1180 assertion), **15 E2E**.

---

## A. Fitur Pelengkap

### A1. Voucher (modul `Promotion`)

**Migrasi:** `vouchers`, `voucher_usages`, FK `orders.voucher_id` (SET NULL) — sesuai docs/03 §J1–J2
dan CHECK docs/04 (`chk_voucher_value`, `chk_voucher_dates`, `chk_voucher_scope_target`).

**Enum:** `VoucherType` (`fixed`,`percent`), `VoucherScope` (`platform`,`seller`),
`VoucherFundedBy` (`platform`,`seller`).

**`VoucherService`** (`app/Modules/Promotion/Support/VoucherService.php`):
- Validasi: aktif, window `starts_at`/`expires_at`, `min_purchase`, `usage_limit`, `usage_limit_per_user`.
- Diskon: `fixed` → `min(value, subtotal)`; `percent` → `min(floor(subtotal × value / 10000), max_discount)`.
- Scope seller → hanya item seller tsb; platform → seluruh order.
- **Aturan basis komisi (keputusan inti):**
  - `funded_by=platform` → diskon dicatat di `seller_orders.platform_funded_discount`, **tidak**
    mengurangi basis komisi (`commission.base_amount` = subtotal penuh).
  - `funded_by=seller` → diskon dicatat di `seller_orders.discount_total` /
    `voucher_discount_borne_by_seller`, **mengurangi** basis komisi.

**Checkout:** preview & place memakai voucher nyata (menggantikan `422 VOUCHER_NOT_AVAILABLE`),
lock voucher saat place, `used_count++`, catat `voucher_usages` (UQ `voucher_id`+`order_id`),
snapshot diskon di `orders`/`seller_orders`. Hash idempotency kini menyertakan `voucher_code`.

**Admin:** `GET/POST/PATCH /admin/vouchers` (permission `manage_vouchers`). `funded_by` diturunkan
dari `scope`; voucher toko wajib `seller_id`; persen memakai basis point.

**UI:** input voucher di checkout (Terapkan/Hapus + baris diskon di ringkasan) dan halaman
`/admin/voucher` (list + form buat/ubah). Hook: `frontend/src/features/promotion.ts`.

### A2. Ulasan produk (modul `Review`)

**Migrasi:** `reviews` (UQ `order_item_id`, CHECK `chk_review_rating`) — docs/03 §J3.

**`CreateReviewAction`:** buyer pemilik order, order `completed`, item milik order, 1 ulasan/item;
status `published` (auto untuk MVP); recompute `products.rating_avg`/`rating_count` dan
`sellers.rating_avg`/`rating_count` dari ulasan `published`.

**Endpoint:** `POST /orders/{orderNumber}/reviews`, `GET /products/{slug}/reviews` (publik, hanya published).
`OrderItemResource` kini menyertakan `reviewed` (relasi `OrderItem@review` dimuat pada detail order).

**UI:** daftar ulasan di PDP (`components/shop/ProductReviews.tsx`) + dialog ulasan di detail pesanan
(`components/shop/ReviewDialog.tsx`, tombol “Beri ulasan” untuk order `completed`). Hook: `features/reviews.ts`.

### A3. Notifikasi in-app (modul `Notification`)

**Migrasi:** `notifications` (UUID PK, docs/03 §J4).

**`NotificationService::send()`** dipanggil di dalam aksi domain (transaksi yang sudah ada, tanpa jaringan):
pembayaran sukses (buyer + seller), sub-order diterima kurir (buyer), sub-order selesai (seller),
payout dibayar/ditolak (seller), refund berhasil (buyer).

**Endpoint:** `GET /notifications?unread=1`, `POST /notifications/{id}/read`, `POST /notifications/read-all`.

**UI:** `NotificationBell` (badge belum dibaca) di header publik/seller/admin + halaman `/notifikasi`
(filter belum dibaca, tandai satu/semua). Hook: `features/notifications.ts`.

### A4. Admin read-only + komisi seller

- `GET /admin/orders` (`manage_orders`) — filter status/buyer/seller/q; `OrderResource` menyertakan
  `buyer`.
- `GET /admin/payments` (`manage_payments`) — filter status/provider; `PaymentResource` menyertakan `order_number`.
- `GET /admin/audit-logs` (`view_reports`) — filter action/entity_type/actor_id.
- `GET /seller/commissions` (milik seller) — read-only.

**UI:** `/admin/pesanan`, `/admin/pembayaran`, `/admin/audit-log`, `/admin/voucher`, `/seller/komisi`;
nav `AdminShell`/`SellerShell` diperbarui; permission guard `AdminShell` diperluas.

---

## B. Baseline Operasional (tanpa deploy)

1. **CI** — `.github/workflows/ci.yml`:
   - `backend`: PHP 8.3, Composer cache, MySQL 8 + Redis 7 service, `pint --test`, `php artisan test`,
     `composer audit`.
   - `frontend`: Node 22, `npm ci`, `lint`, `typecheck`, `build`, `npm audit`.
   - `secret-scan`: `node scripts/scan-secrets.mjs`.
2. **Security baseline**
   - Backend: `App\Support\Http\Middleware\SecurityHeaders` (nosniff, DENY framing, `Referrer-Policy`,
     `Permissions-Policy`, CORP, HSTS hanya saat produksi) — dipasang global.
   - Frontend: `next.config.ts` `headers()` dengan CSP + header keamanan (CSP longgar di dev untuk HMR;
     `connect-src` dinamis dari `NEXT_PUBLIC_API_BASE_URL` + domain Firebase).
   - `scripts/scan-secrets.mjs` + `composer audit` / `npm audit` di CI.
3. **Docker (scaffold, belum go-live)**: `backend/Dockerfile` (+ `docker/entrypoint.sh`),
   `frontend/Dockerfile` (multi-stage), `docker/nginx/default.conf`, `docker-compose.staging.yml`,
   `.env.staging.example`. Docker belum tersedia di lingkungan ini sehingga **belum diuji jalan**.
4. **Backup/restore**: `scripts/backup.ps1` (mysqldump + zip `storage/app`) dan `scripts/restore.ps1`
   (mysql + expand zip), membaca `backend/.env`. Uji backup lokal berhasil (SQL ±107 KB). `.gitignore`
   mengecualikan `backups/`.
5. **Observability scaffold**: `LogRequests` mencatat `http.slow_request` (≥ `OBSERVABILITY_SLOW_REQUEST_MS`,
   default 1000 ms) dan `http.server_error` (5xx) dengan `request_id`/`user_id`; config `zentro.observability`;
   health check `/up`; env contoh ditambahkan.

---

## Verifikasi

```
cd backend  && php artisan test            →  245 passed (1180 assertions)
cd backend  && vendor/bin/pint --test       →  passed
cd frontend && npm run lint                 →  OK (tanpa emoji)
cd frontend && npm run typecheck            →  OK
cd frontend && npm run build                →  OK
cd frontend && npx playwright test          →  15 passed
```

Catatan E2E: backend dijalankan di `:8000` dengan `CACHE_STORE=array SESSION_DRIVER=array
QUEUE_CONNECTION=sync` (Redis tak wajib). Tanpa override ini, throttle `auth` (10/menit per IP)
persisten di Redis dan suite penuh (>10 login) akan kena `429`.

---

## Deviasi & Keputusan

- **Ulasan auto-publish** (`status=published`) untuk MVP; moderasi/admin ulasan ditunda.
- **Notifikasi tanpa worker**: dibuat sinkron di dalam aksi domain; job/queue notifikasi ditunda
  (selaras arah `outbox_events` yang belum diaktifkan).
- **Voucher platform** tidak memotong komisi; **voucher seller** memotong — diuji eksplisit.
- **Docker belum diuji** (Docker tidak tersedia di lingkungan ini) — diperlakukan sebagai scaffold.
- **CSP** belum memakai nonce (memakai `'unsafe-inline'` untuk bootstrap Next); penyempurnaan
  (nonce/SRI) masuk Phase 9.
- Order item mendapat field turunan `reviewed` (bukan tabel baru).

## File utama

**Backend baru:** `app/Enums/{VoucherType,VoucherScope,VoucherFundedBy,ReviewStatus}.php`;
`app/Modules/Promotion/**` (model, `VoucherService`, request/resource, `Admin/VoucherController`, routes);
`app/Modules/Review/**`; `app/Modules/Notification/**`;
`app/Modules/Order/Http/Controllers/Admin/OrderController.php`;
`app/Modules/Payment/Http/Controllers/Admin/PaymentController.php`;
`app/Modules/Platform/Http/Controllers/Admin/AuditLogController.php` (+ resource, routes);
`app/Modules/Finance/Http/Controllers/Seller/SellerCommissionController.php`;
`app/Support/Http/Middleware/{SecurityHeaders,LogRequests}.php`;
migrasi `2026_11_12_1600*` (vouchers, voucher_usages, reviews, notifications, FK orders.voucher_id);
test `tests/Feature/{Promotion/VoucherTest,Review/ReviewTest,Notification/NotificationTest,Admin/AdminReadOnlyTest}.php`.

**Frontend baru:** `features/{reviews,notifications,promotion}.ts`,
`components/shop/{ProductReviews,ReviewDialog}.tsx`, `components/layout/NotificationBell.tsx`,
halaman `(shop)/notifikasi`, `admin/{pesanan,pembayaran,audit-log,voucher}`, `seller/komisi`,
spec `e2e/specs/phase85.spec.ts`.

**Ops baru:** `.github/workflows/ci.yml`, `docker-compose.staging.yml`, `.env.staging.example`,
`docker/nginx/default.conf`, `backend/Dockerfile`, `backend/docker/entrypoint.sh`,
`frontend/Dockerfile`, `scripts/{scan-secrets.mjs,backup.ps1,restore.ps1}`, `.gitignore`.

## Langkah Berikutnya

Phase 9 (Hardening & Deployment) **diblokir** sampai kredensial Midtrans & Firebase produksi tersedia.
Setelah tersedia: aktifkan driver `midtrans`, isi kredensial, load test checkout/webhook, dan staging
production-like (TLS, CI/CD deploy, alert).
