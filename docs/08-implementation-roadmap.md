# 08 — Implementation Roadmap (Technical)

**Prinsip:** satu fase = satu tujuan yang dapat diverifikasi. Setiap fase berakhir dengan test lulus + ringkasan + instruksi menjalankan. Fase besar menunggu persetujuan.

Legend: `[ ]` belum · `[~]` berjalan · `[x]` selesai

---

## Phase 1 — Foundation

**Tujuan:** kerangka aplikasi yang dapat dijalankan, auth Firebase terverifikasi, RBAC, response envelope, dokumentasi API, dan test dasar.

**Deliverables**
- [x] Bootstrap Laravel 13 di `backend/`, konfigurasi `.env`, MySQL, Redis (predis).
- [x] Bootstrap Next.js 16 + TS 5.9 + Tailwind 4 di `frontend/`.
- [x] Struktur `app/Modules`, `Support`, `Contracts`, `Infrastructure`.
- [x] Migrasi & model: `users`, `roles`, `permissions`, `user_roles`, `role_permissions`, `user_addresses`, `user_devices`, `platform_settings`, `audit_logs`.
- [x] `VerifyFirebaseToken` middleware + `FirebaseTokenVerifier` (kreait 7.2.1) + fake driver untuk test.
- [x] RBAC seed (4 role + 13 permission) via `spatie/laravel-permission` 8.5.0.
- [x] `ApiResponse`, exception renderer (`bootstrap/app.php`), `X-Request-Id`.
- [x] Endpoint: `POST /auth/firebase`, `GET /me`, `PATCH /me`, CRUD `/me/addresses`.
- [x] Rate limit dasar (auth, umum).
- [x] CORS terbatas.
- [x] OpenAPI via scramble (`/docs/api`).
- [x] Test: auth valid/expired/invalid/disabled, sinkronisasi user, RBAC, address ownership.
- [x] Seeder admin & customer demo.

**Status: ✅ SELESAI** — lihat [Phase 1 Report](11-phase-1-report.md).

**Exit criteria:** login Firebase → `GET /me` mengembalikan user + roles/permissions; token invalid ditolak; test hijau; docs API dapat dibuka.

**Risiko utama:** kredensial Firebase belum tersedia → sediakan `FakeTokenVerifier` + test, siapkan integrasi nyata begitu kredensial ada.

---

## Phase 2 — Seller Management

- [x] Migrasi: `sellers`, `seller_documents`, `seller_addresses`.
- [x] `POST /seller-applications`, `GET /seller-applications/me`, upload dokumen (disk privat + validasi).
- [x] Admin: list/approve/reject/suspend/reactivate + audit log.
- [x] `GET/PATCH /seller/profile`, `GET /seller/dashboard`.
- [x] Policies: `SellerPolicy`, `SellerDocumentPolicy`; middleware `EnsureSellerExists` + `EnsureSellerActive`.
- [x] Test: pendaftaran, approval, rejection, suspend, akses lintas seller (403).

**Status: ✅ SELESAI** — lihat [Phase 2 Report](13-phase-2-report.md).

**Exit:** seller baru `pending` tidak bisa transaksi; approve → aktif; semua aksi admin teraudit. ✅

---

## Phase 3 — Catalog & Inventory

- [x] Migrasi: `categories`, `products`, `product_variants`, `product_images`, `inventories`, `inventory_movements`.
- [x] CRUD produk/varian/gambar (seller), moderasi admin.
- [x] Endpoint publik `GET /categories`, `GET /products`, `GET /products/{slug}` (cache Redis).
- [x] Update stok + `inventory_movements` (append-only) + row lock.
- [x] Validasi upload gambar (MIME, size, dimensi).
- [x] Test: ownership produk, publish rules, stok adjustment, validasi gambar.

**Status: ✅ SELESAI** — lihat [Phase 3 Report](14-phase-3-report.md).

**Exit:** seller dapat mengelola katalog; publik dapat melihat produk aktif; setiap perubahan stok tercatat. ✅

---

## Phase 4 — Cart & Checkout

- [x] Migrasi: `carts`, `cart_items`, `orders`, `seller_orders`, `order_items`, `order_addresses`, status histories, `inventory_reservations`, `idempotency_keys`.
- [x] Cart endpoints.
- [x] `POST /checkout/preview` (recompute harga, ongkir per seller, voucher ditunda).
- [x] `POST /checkout` dengan idempotency + reservasi stok atomik + split per seller + snapshot.
- [x] Job `ReleaseExpiredReservations`, `ExpireOrders`.
- [x] Test: order splitting, snapshot benar, **dua checkout stok terakhir bersamaan** (satu sukses, satu 422), idempotency key sama.

**Status: ✅ SELESAI** — lihat [Phase 4 Report](15-phase-4-report.md).

**Exit:** satu checkout multi-seller menghasilkan 1 order + N suborder; overselling tidak terjadi pada uji konkuren. ✅ (dibuktikan dengan 2 proses PHP bersamaan)

---

## Phase 5 — Payment

- [x] Migrasi: `payments`, `payment_attempts`, `payment_webhook_events`, `refunds`.
- [x] `PaymentGatewayInterface` + `MidtransGateway` (adapter) + fake gateway untuk test.
- [x] Buat payment setelah checkout commit; simpan provider reference; endpoint payment/retry.
- [x] Webhook: signature, durable event, idempotency, amount check, transisi aman, konfirmasi reservasi.
- [x] Job `ReconcilePayments` (+ `ExpirePayments`).
- [x] Test: webhook valid, **webhook ganda**, amount mismatch, signature invalid, expired.

**Status: ✅ SELESAI** — lihat [Phase 5 Report](16-phase-5-report.md).

**Exit:** pembayaran sukses mengubah order → paid dan stok terkonfirmasi tepat sekali. ✅ (dibuktikan dengan dua webhook paid dari 2 proses PHP bersamaan → commit stok tepat 1×)

---

## Phase 6 — Shipping

- [x] Migrasi: `shipments`, `shipment_histories` (+ `shipping_webhook_events`).
- [x] `GET /shipping/quotes` + adapter provider (tarif tetap; agregator menyusul).
- [x] Seller: list/detail shipment, confirm, dispatch, tracking (+ endpoint seller order).
- [x] Webhook tracking + job sync tracking.
- [x] Test: dispatch mengubah suborder → shipped; tracking → delivered; partial shipment (desain siap).

**Status: ✅ SELESAI** — lihat [Phase 6 Report](17-phase-6-report.md).

**Exit:** setiap seller mengirim pesanan sendiri dengan resi & tracking terpisah. ✅ (2 webhook `delivered` bersamaan dari 2 proses PHP → satu transisi)

---

## Phase 7 — Commission & Finance

- [x] Migrasi: `commission_rules`, `commissions`, `seller_wallets`, `seller_payouts`, `wallet_transactions`, `payout_items` (+ `seller_bank_accounts`).
- [x] `CommissionCalculator` (basis point, snapshot) + unit test.
- [x] Settlement job (hold days) → pending → available.
- [x] Wallet endpoints + ledger immutable + pembalik.
- [x] Payout request/approve/process + idempotency + lock saldo.
- [x] Refund adjustment (debit + reverse komisi).
- [x] Rekonsiliasi harian (SUM ledger vs cache saldo).
- [x] Test: komisi benar, settlement, **payout ganda ditolak**, saldo tidak negatif (kecuali refund pasca-payout), reversal.

**Status: ✅ SELESAI** — lihat [Phase 7 Report](18-phase-7-report.md).

**Exit:** dana seller mengikuti alur pending → available → paid; ledger konsisten dengan saldo. ✅ (dibuktikan 2 proses `mark_paid` bersamaan → satu transfer; SUM ledger = saldo)

---

## Phase 8 — Frontend

- [x] Fondasi: layout, UI kit, API client, TanStack Query, Firebase client (+dev auth), guard route (`proxy.ts`).
- [x] Customer: home, listing, detail, search, cart, checkout, payment, orders, profile, alamat. *(Review: ditunda — endpoint belum ada.)*
- [x] Seller: onboarding, dashboard, produk/varian/stok, pesanan, pengiriman, wallet, payout. *(Halaman komisi seller: ditunda — endpoint belum ada.)*
- [x] Admin: dashboard, seller approval, moderasi, refund, komisi, payout. *(Orders/payments/users/voucher/laporan/audit-log: ditunda — endpoint belum ada.)*
- [x] Semua state: loading/error/empty/validation, responsive, akses kontrol UI.
- [x] E2E smoke (Playwright) untuk alur utama.

**Status: ✅ SELESAI** — lihat [Phase 8 Report](19-phase-8-report.md).

**Exit:** tiga area berfungsi end-to-end terhadap API; tidak ada authz bergantung pada UI. ✅ (12 E2E smoke hijau; authz final tetap di backend)

---

## Phase 8.5 — Feature Gaps & Operational Baseline

**Tujuan:** menutup celah fungsional Phase 8 dan menyiapkan baseline operasional tanpa kredensial produksi.
Rencana & hasil: [20-phase-8.5-plan.md](20-phase-8.5-plan.md) · [21-phase-8.5-report.md](21-phase-8.5-report.md).

**A. Fitur pelengkap**
- [x] Voucher (platform/toko, kuota, window, min belanja) + aturan basis komisi; UI checkout & admin.
- [x] Ulasan produk (order `completed`, 1 per item, auto-publish) + recompute rating; UI PDP & detail pesanan.
- [x] Notifikasi in-app (payment/delivered/completed/payout/refund) + lonceng & halaman `/notifikasi`.
- [x] Read-only admin `/admin/orders`, `/admin/payments`, `/admin/audit-logs` + `/seller/commissions`.

**B. Baseline operasional**
- [x] CI (`.github/workflows/ci.yml`): Pint, Pest (MySQL+Redis service), lint, typecheck, build, secret scan.
- [x] Security baseline: header keamanan backend + Next (CSP), dependency audit, `scripts/scan-secrets.mjs`.
- [x] Docker scaffold: `backend/Dockerfile`, `frontend/Dockerfile`, `docker/nginx/default.conf`, `docker-compose.staging.yml` (belum go-live).
- [x] Backup/restore: `scripts/backup.ps1`, `scripts/restore.ps1`, panduan uji restore.
- [x] Observability scaffold: `LogRequests` (slow/5xx) + health `/up` + docs metrik/alarm.

**Status: ✅ SELESAI** — 245 test backend, 15 E2E hijau.

**Exit:** semua celah fungsional tertutup; baseline operasional terpasang tanpa kredensial produksi. Driver tetap `fake`/`manual`.

---

## Phase 9 — Hardening & Deployment

> **Diblokir** sampai kredensial Midtrans/Firebase produksi tersedia (keputusan pengguna).

- [ ] Security review (OWASP) menyeluruh, dependency audit lanjutan, secret scan di semua repo.
- [ ] Concurrency & load test (k6/Locust) untuk checkout & webhook terhadap gateway nyata.
- [ ] Observability lanjutan: metrics, alert queue & error rate, dashboard.
- [x] Backup MySQL + uji restore lokal (Phase 8.5); runbook insiden masih TODO.
- [~] Docker production, Nginx TLS, CI/CD, staging (scaffold Phase 8.5; TLS & staging nyata TODO).
- [ ] Dokumentasi operasional & on-call.

**Exit:** staging production-like lulus smoke + load test dasar; backup/restore terbukti.

---

## Estimasi Relatif (bukan komitmen waktu)

| Fase | Kompleksitas | Ketergantungan |
|---|---|---|
| 1 Foundation | Sedang | — |
| 2 Seller | Sedang | 1 |
| 3 Catalog/Inventory | Sedang-Tinggi | 2 |
| 4 Cart/Checkout | **Tinggi** | 3 |
| 5 Payment | **Tinggi** | 4 |
| 6 Shipping | Sedang | 5 |
| 7 Finance | **Sangat Tinggi** | 5,6 |
| 8 Frontend | Tinggi | 1–7 |
| 8.5 Feature gaps & ops baseline | Sedang | 1–8 |
| 9 Hardening | Tinggi | semua |

**Critical path:** 1 → 2 → 3 → 4 → 5 → 7. Shipping (6) dapat berjalan paralel dengan awal 7 bila provider belum siap (pakai mock).

---

## Definition of Done per Fase

1. Semua migrasi berjalan & rollback aman.
2. Test unit + feature hijau (termasuk authz & validasi).
3. Tidak ada `dd()`/debug, tidak ada secret.
4. Pint & static check lulus.
5. OpenAPI ter-update.
6. Ringkasan hasil + cara menjalankan + batasan diketahui.
7. Persetujuan sebelum fase berikutnya bila ada perubahan bisnis/arsitektur.
