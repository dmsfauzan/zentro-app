# 20 — Phase 8.5 Plan (Feature Gaps + Operational Baseline)

**Tujuan:** menutup celah fungsional Phase 8 dan menyiapkan baseline operasional **tanpa** menunggu kredensial Midtrans/Firebase produksi.
**Prasyarat:** Phase 1–8 selesai (225 test backend, 12 E2E).

---

## A. Fitur Pelengkap

### A1. Voucher (Promotion)
- Migrasi `vouchers`, `voucher_usages` (sesuai docs/03 J1–J2 + CHECK docs/04).
- Enum `VoucherType` (`fixed`,`percent`), `VoucherScope` (`platform`,`seller`), `VoucherFundedBy` (`platform`,`seller`).
- `VoucherService`:
  - validasi: aktif, window tanggal, min_purchase, usage_limit, limit per user.
  - hitung diskon: fixed = min(value, subtotal); percent = min(floor(subtotal × value/10000), max_discount).
  - scope seller: hanya item seller tsb; platform: seluruh order.
  - alokasi: `funded_by=seller` → mengurangi basis komisi seller (`seller_order.discount_total`); `funded_by=platform` → dicatat `platform_funded_discount`, **tidak** mengurangi basis komisi.
- Checkout: preview & place memakai voucher nyata (bukan `422 VOUCHER_NOT_AVAILABLE`), lock voucher saat place, `used_count++`, catat `voucher_usages` (UQ voucher+order), snapshot diskon di order/seller_orders.
- Admin: CRUD/list voucher (`manage_vouchers`).
- UI: input voucher di checkout (preview + submit), halaman admin voucher.

### A2. Review Produk (Review)
- Migrasi `reviews` (docs/03 J3, CHECK rating 1..5, UQ order_item).
- Endpoint:
  - `POST /orders/{orderNumber}/reviews` (buyer, order `completed`, item milik order, 1 ulasan/item).
  - `GET /products/{slug}/reviews` (publik, hanya `published`, pagination).
- Agregasi `products.rating_avg`/`rating_count` (recompute dari review published).
- UI: daftar ulasan di PDP + form ulasan pada detail pesanan yang selesai.

### A3. Notifikasi In-App (Notification)
- Migrasi `notifications` (UUID, docs/03 J4).
- `NotificationService::send()` + trigger: pembayaran sukses (buyer & seller), delivered (buyer), sub-order completed (seller), payout diproses (seller), refund sukses (buyer).
- Endpoint: `GET /notifications?unread=1`, `POST /notifications/{id}/read`, `POST /notifications/read-all`.
- UI: ikon lonceng + jumlah belum dibaca, halaman `/notifikasi`.

### A4. Admin Read-only + Komisi Seller
- `GET /admin/orders` (filter status/seller/buyer), `GET /admin/payments` (status/provider), `GET /admin/audit-logs` (action/entity/actor).
- `GET /seller/commissions` (milik seller).
- UI: `/admin/pesanan`, `/admin/pembayaran`, `/admin/audit-log`, `/seller/komisi`; nav ditambahkan.

---

## B. Baseline Operasional (tanpa deploy)

1. **CI** (`.github/workflows/ci.yml`): backend (composer, Pint, test dengan MySQL+Redis service), frontend (lint, typecheck, build; E2E opsional).
2. **Security baseline**: security headers backend + Next, dependency audit (`composer audit`, `npm audit`), secret scan sederhana.
3. **Docker**: `backend/Dockerfile`, `frontend/Dockerfile`, `docker-compose.staging.yml`, `docker/nginx/default.conf` (belum go-live).
4. **Backup/restore**: skrip `scripts/backup.ps1` + `scripts/restore.ps1` + panduan uji restore lokal.
5. **Observability scaffold**: log request lambat/5xx dengan konteks, health check, dokumentasi metrik/alarm.

---

## Definition of Done
- Backend: migrasi + test baru hijau; regresi 225+ tetap hijau; Pint lulus.
- Frontend: lint/typecheck/build hijau; E2E lama tetap hijau + smoke voucher/review.
- Dokumen: `docs/21-phase-8.5-report.md`, update `docs/03/04/06/07/08`, README.
- Tidak ada kredensial yang dibutuhkan; semua driver tetap `fake`/`manual`.
