# 06 — API Specification (v1)

**Base URL:** `https://api.zentro.example/api/v1`
**Format:** JSON (`application/json`)
**Auth:** `Authorization: Bearer <Firebase ID Token>`
**Docs:** OpenAPI 3.1 via `dedoc/scramble` (`/docs/api`, `/openapi.json`)
**Versioning:** path-based (`/api/v1`). Perubahan breaking → `/api/v2`.

---

## 1. Konvensi Umum

### 1.1 Response Envelope

```json
// 200/201 success
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": { },
  "meta": { }
}

// error
{
  "success": false,
  "message": "Validation failed",
  "errors": { "field": ["pesan"] }
}
```

`meta` dipakai untuk pagination & info tambahan.

### 1.2 Pagination (cursor & offset)

Offset (untuk admin/report):
```
GET /admin/orders?page=2&per_page=25&sort=-created_at
```
```json
"meta": {
  "pagination": {
    "page": 2, "per_page": 25, "total": 1330, "last_page": 54,
    "has_more": true
  }
}
```

Cursor (untuk feed & ledger besar):
```
GET /orders?cursor=eyJpZCI6MTIzfQ&limit=20
```
```json
"meta": { "cursor": { "next": "eyJpZCI6MTAwfQ", "prev": null, "has_more": true } }
```

### 1.3 Filtering, Sorting, Sparse Fields

- Filter: `?status=active&seller_id=12&price_min=10000&price_max=50000`
- Sort: `?sort=price` / `?sort=-created_at` (prefix `-` = desc). Whitelist per endpoint.
- Fields: `?fields=id,name,slug`
- Include: `?include=seller,images,variants` (whitelist per endpoint)

### 1.4 Header Standar

| Header | Arah | Keterangan |
|---|---|---|
| `Authorization: Bearer <token>` | request | Firebase ID Token |
| `Accept-Language: id` | request | Lokalisasi pesan |
| `X-Request-Id` | request/response | Korelasi |
| `Idempotency-Key` | request | Wajib untuk mutasi kritis |
| `X-Idempotent-Replayed: true` | response | Menandai respons hasil replay |

### 1.5 HTTP Status

| Status | Pemakaian |
|---|---|
| 200 | OK |
| 201 | Created |
| 202 | Accepted (async, mis. proses payout) |
| 204 | No Content (delete) |
| 400 | Bad Request |
| 401 | Unauthenticated (token invalid/expired) |
| 403 | Forbidden (authz gagal) |
| 404 | Not Found |
| 409 | Conflict (state/idempotency) |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

### 1.6 Kode Error Aplikasi

Envelope error menyertakan `code` opsional:

| Code | Arti |
|---|---|
| `AUTH_TOKEN_INVALID` | Token Firebase tidak valid |
| `AUTH_TOKEN_EXPIRED` | Token kedaluwarsa |
| `AUTH_ACCOUNT_DISABLED` | Akun Firebase disabled |
| `SELLER_NOT_ACTIVE` | Aksi butuh seller aktif |
| `PRODUCT_UNAVAILABLE` | Produk/varian tidak aktif |
| `INSUFFICIENT_STOCK` | Stok tidak cukup |
| `PAYMENT_AMOUNT_MISMATCH` | Nominal tidak cocok |
| `PAYMENT_ALREADY_PAID` | Sudah dibayar |
| `IDEMPOTENCY_CONFLICT` | Key sama, body beda |
| `INSUFFICIENT_BALANCE` | Saldo wallet kurang |
| `PAYOUT_IN_PROGRESS` | Ada payout aktif |
| `STATE_TRANSITION_INVALID` | Transisi status tidak sah |

### 1.7 Rate Limiting

| Scope | Limit |
|---|---|
| Auth (`POST /auth/firebase`) | 10/menit per IP |
| Umum (terautentikasi) | 120/menit per user |
| Checkout | 10/menit per user |
| Payout request | 3/jam per seller |
| Webhook | 300/menit per provider (IP allowlist) |
| Upload | 30/menit per user |

Header respons: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`.

---

## 2. Authentication

### `POST /auth/firebase`
Menukar Firebase ID Token dengan konteks user terlokalisasi + sinkronisasi.

```json
// request
{ "id_token": "<firebase-id-token>" }
```
```json
// 200
{
  "success": true,
  "message": "Authenticated successfully",
  "data": {
    "user": {
      "id": 10, "firebase_uid": "abc123", "name": "Budi", "email": "budi@example.com",
      "email_verified": true, "status": "active", "roles": ["customer"],
      "permissions": ["view_reports"],
      "seller": null
    }
  }
}
```

### `GET /me`
Profil user terautentikasi + roles + permissions + ringkasan seller.

### `PATCH /me`
```json
{ "name": "Budi Santoso", "phone": "+628123456789" }
```

### `GET /me/addresses`
### `POST /me/addresses`
```json
{
  "label": "Rumah", "recipient_name": "Budi", "phone": "+62812...",
  "address_line": "Jl. Merdeka No. 1", "province": "DKI Jakarta",
  "city": "Jakarta Pusat", "district": "Menteng", "village": "Menteng",
  "postal_code": "10310", "is_default": true
}
```
### `PATCH /me/addresses/{id}`
### `DELETE /me/addresses/{id}` → 204

**Authz:** hanya pemilik alamat (`AddressPolicy`).

**Error:** `401 AUTH_TOKEN_*`.

---

## 3. Seller

### `POST /seller-applications`
Mendaftar sebagai seller (status awal `pending`/`under_review`).
```json
{
  "store_name": "Toko Budi",
  "description": "...",
  "address": {
    "contact_name": "Budi", "phone": "+62812...", "address_line": "Jl. ...",
    "province": "DKI Jakarta", "city": "Jakarta Pusat", "postal_code": "10310"
  }
}
```
```json
// 201
{ "success": true, "message": "Application submitted", "data": { "id": 3, "status": "pending" } }
```

### `GET /seller-applications/me`
### `POST /seller-applications/{id}/documents` (multipart)
```
type: ktp|npwp|siup|nib|bank_statement|other
file: <binary>   (jpg|jpeg|png|pdf, maks 5 MB)
```
Guard: hanya pemilik toko (`SellerPolicy@uploadDocument`); status toko `pending`/`under_review`/`rejected`; unggahan duplikat (type + hash sama) ditolak.
Efek: unggahan pertama memindahkan status `pending`/`rejected` → `under_review` dan menandai `submitted_at`.

```json
// 201
"data": {
  "document": { "id": 1, "type": "ktp", "status": "pending", "download_url": null },
  "seller":   { "id": 1, "status": "under_review", "kyc": { "required": ["ktp"], "missing": [], "complete": true } }
}
```

> **Keamanan:** dokumen disimpan di disk **privat**. `download_url` hanya diberikan kepada pengguna dengan permission `manage_sellers`; seller tidak menerima URL unduhan.

### `GET /seller/dashboard`
```json
"data": {
  "orders_today": 12, "revenue_this_month": 4200000,
  "pending_orders": 5, "low_stock_variants": 3,
  "wallet": { "pending": 500000, "available": 1200000, "paid": 3000000 }
}
```

### `GET /seller/profile` | `PATCH /seller/profile`

### `GET /seller/orders?status=pending&cursor=...`
### `GET /seller/orders/{id}`
### `POST /seller/orders/{id}/confirm`
```json
{ "note": "Pesanan diproses" }
```
Guard: seller pemilik, status `pending`.

### `POST /seller/orders/{id}/shipments`
```json
{ "provider": "jne", "service_code": "REG", "tracking_number": "JNE123" }
```
→ shipment `dispatched`, seller_order → `shipped`.

---

## 4. Catalog

### `GET /categories` (publik, cached)
### `GET /products` (publik)
Filter: `q`, `category`, `seller`, `price_min`, `price_max`, `sort` (`newest`, `price_asc`, `price_desc`, `popular`), pagination `page`/`per_page`.
Filter `category` menerima **slug kategori mana pun**: hasilnya mencakup seluruh sub-kategori di bawahnya
(mis. `category=fashion` juga mengembalikan produk di `pria`/`wanita`). Slug tak dikenal → hasil kosong.

### `GET /products/{slug}` (publik)
Menyertakan `variants`, `images`, `seller_summary`, `reviews_summary`.

### `GET /seller/products?status=active&cursor=...`
### `POST /seller/products`
```json
{
  "name": "Kaos Polos", "category_id": 5, "description": "<p>...</p>",
  "weight_gram": 200,
  "variants": [
    { "sku": "KP-BLK-M", "variant_name": "Hitam / M", "price": 75000, "weight_gram": 200, "stock": 10 }
  ]
}
```
### `GET /seller/products/{id}` | `PATCH /seller/products/{id}` | `DELETE /seller/products/{id}` (soft delete/archived)
### `POST /seller/products/{id}/images` (multipart: `file`, `alt_text?`, `is_primary?`)
### `DELETE /seller/products/{id}/images/{image}`
### `POST /seller/products/{id}/variants`
### `PATCH /seller/variants/{id}`
### `PATCH /seller/variants/{id}/inventory`
```json
{ "mode": "set|adjust", "quantity": 50, "note": "Restock" }
```

**Admin (moderasi, permission `manage_products`)**
### `GET /admin/products?status=&seller_id=&q=`
### `PATCH /admin/products/{id}/status` → `{ "status": "active|inactive|rejected", "reason": "..." }`

**Authz:** `ProductPolicy`/`ProductVariantPolicy`/`ProductImagePolicy` — seller hanya akses miliknya (`403` untuk seller lain).

**Implementasi (Phase 3) yang perlu diketahui klien:**

| Aspek | Perilaku |
|---|---|
| Pagination | Offset (`page`, `per_page` maks 100) + `meta.pagination` |
| Pencarian | `q` memakai `LIKE` pada nama & deskripsi (FULLTEXT ditunda) |
| Publish | Produk dibuat `draft`; `PATCH status=active` butuh ≥1 varian aktif **dan** ≥1 gambar → jika tidak: `422 PRODUCT_NOT_PUBLISHABLE` |
| Harga | Integer rupiah; `min_price`/`max_price` dihitung otomatis dari varian aktif |
| Stok | `available_stock = stock - reserved_stock`; `mode=set` menetapkan, `mode=adjust` menambah/mengurangi; gagal bila < reserved atau negatif → `422 INVENTORY_INVALID` |
| Gambar | jpg/jpeg/png/webp, maks 5 MB, min 200×200 px, maks 5 gambar/produk (`422 IMAGE_LIMIT_REACHED`); gambar pertama otomatis `is_primary` |
| Upload file | `multipart/form-data` (bukan JSON) |
| Slug | Stabil saat nama produk berubah |
| Hapus produk | Soft delete + status `archived` + varian `inactive` |

---

## 5. Cart & Checkout

### `GET /cart`
```json
"data": {
  "items": [
    { "id": 1, "variant_id": 9, "product_name": "Kaos Polos", "variant_name": "Hitam/M",
      "price": 75000, "quantity": 2, "line_total": 150000, "seller": { "id": 3, "store_name": "Toko Budi" },
      "available_stock": 8, "is_available": true }
  ],
  "summary": { "subtotal": 150000, "item_count": 2, "seller_count": 1 }
}
```

### `POST /cart/items`
```json
{ "variant_id": 9, "quantity": 2 }
```
Guard: varian aktif, seller aktif, stok cukup.

### `PATCH /cart/items/{id}` → `{ "quantity": 3 }`
### `DELETE /cart/items/{id}` → 204

### `POST /checkout/preview`
Menghitung ulang harga, diskon, ongkir per seller, grand total. **Tidak** membuat order.
```json
{ "items": [{"variant_id":9,"quantity":2}], "address_id": 4, "voucher_code": "ZENTRO10" }
```
```json
"data": {
  "seller_groups": [
    { "seller": {"id":3,"store_name":"Toko Budi"}, "subtotal": 150000, "shipping_cost": 18000,
      "discount": 15000, "total": 153000, "shipping_options": [{"code":"jne-REG","name":"JNE REG","cost":18000,"etd":"2-3 hari"}] }
  ],
  "summary": { "subtotal":150000, "discount_total":15000, "shipping_total":18000, "grand_total":153000 },
  "available_methods": [ { "key":"bca_va", "label":"BCA Virtual Account", "group":"Virtual Account" } ],
  "expires_at": "2026-11-09T20:00:00Z"
}
```
`available_methods` dipakai halaman checkout untuk menampilkan pemilih metode (tab Transfer Bank /
e-Wallet-QRIS). Bila kosong (driver `fake`), pemilihan metode dilakukan di halaman pembayaran.

### `POST /checkout`
**Header:** `Idempotency-Key: <uuid>` (wajib)
```json
{ "items":[{"variant_id":9,"quantity":2}], "address_id":4, "voucher_code":"ZENTRO10",
  "shipping_selections":[{"seller_id":3,"service_code":"jne-REG"}] }
```
```json
// 201
"data": {
  "order_number": "ZTR-20261109-000001",
  "status": "pending_payment",
  "grand_total": 153000,
  "expires_at": "2026-11-10T10:00:00Z",
  "payment": {
    "provider": "midtrans", "status": "pending", "method": null, "amount": 153000,
    "available_methods": [
      { "key":"qris", "label":"QRIS", "group":"QRIS" },
      { "key":"gopay", "label":"GoPay", "group":"E-wallet" },
      { "key":"bca_va", "label":"BCA Virtual Account", "group":"Virtual Account" }
    ]
  }
}
```

### `POST /orders/{orderNumber}/payment/retry` (pilih/ganti metode)
```json
{ "method": "bca_va" }
```
```json
// 200
"data": { "provider":"midtrans", "method":"bca_va", "status":"requires_action",
          "amount":153000, "va_number":"88284030534092354640484",
          "qr_code_url":null, "qr_string":null, "deeplink_url":null,
          "biller_code":null, "bill_key":null, "expires_at":"2026-11-10T10:00:00Z" }
```
Instruksi yang mungkin diisi provider: `va_number` (VA), `qr_code_url`/`qr_string` (QRIS/e-wallet),
`deeplink_url` (GoPay), `biller_code`+`bill_key` (Mandiri). Instruksi metode lama otomatis dibuang saat ganti metode.
```json
// 422
{ "success": false, "errors": { "code": "PAYMENT_METHOD_INVALID", "available_methods": ["qris","gopay","bca_va"] } }
```
**Guard:** idempotency, validasi ulang semua harga/stok/seller/voucher, reservasi inventory.
**Error:** `409 IDEMPOTENCY_CONFLICT`, `422 INSUFFICIENT_STOCK`.

**Implementasi (Phase 4) — Cart, Checkout & Order:**

| Aspek | Perilaku |
|---|---|
| Cart | Satu cart per user; harga selalu dibaca ulang; item duplikat digabung; kuantitas dibatasi stok tersedia |
| Preview | `items` opsional (default: isi cart); grup per seller + opsi ongkir + ringkasan; tidak membuat order |
| Checkout | **Wajib** header `Idempotency-Key`; 1 order induk + N sub-order per seller; reservasi stok atomik (`SELECT ... FOR UPDATE`); snapshot produk & alamat |
| Ongkir | Tarif tetap per seller (Phase 4); diganti agregator pada Phase 6 |
| Voucher | Aktif (Phase 8.5): validasi window/kuota/min belanja; diskon platform **tidak** memotong komisi, diskon toko **memotong** komisi; `used_count++` + catat `voucher_usages` (UQ voucher+order) |
| Harga | Nilai dari klien (`price`, `line_total`) diabaikan total |
| Stok | `reserved_stock += qty`; reservasi `active` dengan `expires_at`; tersedia = `stock - reserved_stock` |
| Idempotency | Key sama + payload sama → replay (`X-Idempotent-Replayed: true`); payload beda → `409 IDEMPOTENCY_CONFLICT` |
| Order number | `ZTR-YYYYMMDD-NNNNNN` (retry bila bentrok) |
| Expiry | Order `pending_payment` kedaluwarsa → `expired` + reservasi dilepas (scheduler tiap menit) |
| Cancel | Hanya `pending_payment`; melepas reservasi; selain itu `409 ORDER_NOT_CANCELLABLE` |
| Orders | `GET /orders` (buyer, pagination) & `GET /orders/{orderNumber}` (pemilik/admin) |

---

## 6. Orders & Payment

### `GET /orders?status=paid&cursor=...` (buyer)
### `GET /orders/{orderNumber}`
Menyertakan `seller_orders`, `items`, `payment`, `shipments`, `status_history`.

### `GET /orders/{orderNumber}/invoice`
Nota/invoice marketplace untuk satu order (item dikelompokkan per seller).
Guard: `OrderPolicy@view` (pemilik atau admin) **dan** order sudah dibayar — bila belum → `409 INVOICE_NOT_AVAILABLE`.
Nomor invoice deterministik: `INV/<YYYYMMDD tanggal lunas>/<orderNumber>` (tanpa tabel tambahan).
Mengembalikan data pembeli, alamat kirim, item per seller, ringkasan, dan info pembayaran.

### `POST /orders/{orderNumber}/cancel`
```json
{ "reason": "Salah pilih produk" }
```
Guard: `pending_payment` atau suborder belum diproses; lepas reservasi.

### `GET /orders/{orderNumber}/payment`
### `POST /orders/{orderNumber}/payment/retry`
Guard: payment belum `paid`; membuat `payment_attempts` baru.
### `POST /orders/{orderNumber}/confirm-received`
Tandai suborder `delivered → completed` (per seller) atau seluruh order.

### `POST /webhooks/payments/{provider}`
**Tanpa auth bearer.** Verifikasi signature + IP allowlist.
- Simpan durable ke `payment_webhook_events` (unique `provider_event_id`).
- Idempotent: event terproses → 200 replay.
- Validasi amount & status.
- Selalu balas `200` bila signature valid (agar provider tidak retry tak perlu); error internal dicatat & dijob retry.

**Implementasi (Phase 5) — Payment:**

| Aspek | Perilaku |
|---|---|
| Pembuatan payment | Dilakukan **setelah** transaksi checkout commit (tanpa jaringan di dalam transaksi); `amount = order.grand_total`; 1 order = 1 payment |
| Response checkout | `data.payment` berisi daftar metode (`available_methods`) + status `pending`; **charge** dibuat saat pembeli memilih metode (Core API `/v2/charge`) |
| Replay idempotency | Tidak membuat percobaan baru; instruksi/metode yang sudah ada dipakai ulang |
| `GET /orders/{orderNumber}/payment` | Pemilik/admin; `404 PAYMENT_NOT_FOUND` bila payment belum ada; menyertakan `available_methods` |
| `POST /orders/{orderNumber}/payment/retry` | Body `method` (opsional); buat `payment_attempts` baru; `422 PAYMENT_METHOD_INVALID` bila metode tidak aktif; `409 PAYMENT_ALREADY_PAID` / `PAYMENT_NOT_RETRYABLE`; rate limit 10/menit |
| Webhook | Verifikasi signature → simpan event durable → cek `processed` → cari order → amount check → `SELECT ... FOR UPDATE` pada `payments` → transisi |
| Signature | Midtrans: `sha512(order_id + status_code + gross_amount + signature_secret_key)` (`MIDTRANS_SIGNATURE_KEY`; fallback server key); driver `fake`: HMAC-SHA256 dengan `PAYMENT_FAKE_SECRET` |
| Notifikasi transaksi lama | Event non-paid dari transaksi yang sengaja ditutup saat pembeli ganti metode (`provider_payload.swept_transaction_id`) → `ignored` |
| Efek `settlement`/`capture` | Order → `paid` + `paid_at`; semua reservasi order → `committed`; `stock -= qty`, `reserved_stock -= qty` |
| Efek `deny`/`cancel`/`failure` | Payment → `failed`; order & stok tidak berubah |
| Efek `expire` | Payment → `expired`; reservasi tetap `active` (dilepas oleh job order) |
| Webhook ganda / event id sama | 200 tanpa efek; commit stok tidak pernah terjadi dua kali |
| Amount mismatch | Event `failed` + `PAYMENT_AMOUNT_MISMATCH`; order & stok tidak berubah |
| Signature tidak valid | `401 WEBHOOK_SIGNATURE_INVALID`; event dicatat `failed` |
| Provider tidak dikenal | `404 WEBHOOK_PROVIDER_UNKNOWN` |
| Order tidak dikenal / sudah expired | 200 + event `ignored` (tanpa efek) |
| Driver | `PAYMENT_GATEWAY=fake` (default lokal & test) atau `midtrans` (Core API, tanpa Snap) |
| Simulator | `POST /dev/payments/{orderNumber}/simulate` — hanya `APP_ENV=local` + driver fake |

---

## 7. Shipping

### `GET /shipping/quotes`
```json
// request query
?seller_id=3&destination_city=Bandung&weight_gram=1000&item_value=150000
```
```json
"data": [ { "code":"jne-REG","name":"JNE REG","cost":18000,"etd":"2-3 hari","provider":"jne" } ]
```

### `GET /seller/shipments?status=ready&cursor=...`
### `GET /seller/shipments/{id}`
### `POST /seller/shipments/{id}/dispatch`
```json
{ "tracking_number": "JNE123", "tracking_url": "https://..." }
```
### `GET /shipments/{id}/tracking`
### `POST /webhooks/shipping/{provider}` (signature verified, durable, idempotent)

**Implementasi (Phase 6) — Shipping:**

| Aspek | Perilaku |
|---|---|
| Shipment dibuat | Otomatis (`pending`) saat pembayaran sukses, satu per `seller_order`; `shipping_cost`/`weight_gram`/`origin_city`/`destination_city` disalin sebagai snapshot |
| `GET /shipping/quotes` | Membungkus `ShippingRateProvider` existing; saat ini tarif tetap (`flat`); 503 `SHIPPING_QUOTE_UNAVAILABLE` bila gagal |
| `GET /seller/orders` & `/{id}` | Hanya sub-order milik seller (`SellerOrderPolicy`); 403 lintas seller |
| `POST /seller/orders/{id}/confirm` | `pending → confirmed`; `409 ORDER_NOT_CONFIRMABLE` bila order belum paid |
| `POST /seller/orders/{id}/shipments` | Buat (bila perlu) + dispatch dalam satu panggilan |
| `POST /seller/shipments/{id}/dispatch` | Butuh `tracking_number`; `409 TRACKING_NUMBER_TAKEN`/`SHIPMENT_ALREADY_DISPATCHED`; dispatch memindahkan sub-order `confirmed → processing → shipped` |
| `GET /shipments/{id}/tracking` | Seller pemilik / buyer pemilik order / admin; berisi timeline |
| `POST /orders/{orderNumber}/confirm-received` | Buyer; `delivered → completed` (semua atau `seller_order_id` tertentu) |
| Webhook tracking | Signature → durable `shipping_webhook_events` → idempotent (`provider_event_id`) → transisi guarded |
| Signature | `manual`/`fake`: HMAC-SHA256 (`SHIPPING_WEBHOOK_SECRET`); agregator menyesuaikan |
| Efek `delivered` | Shipment `delivered` + `delivered_at`; sub-order `delivered`; parent diagregasi |
| Efek `in_transit` | Shipment `in_transit` (sub-order tetap `shipped`) |
| Efek `failed`/`returned` | Status shipment saja; refund ditunda ke Phase 7 |
| Job | `SyncShipmentTrackingJob` (15 menit), `AutoCompleteShipmentsJob` (harian, `auto_complete_days`) |
| Driver | `SHIPPING_DRIVER=manual` (default) atau `fake` (test/simulator) |
| Simulator | `POST /dev/shipments/{id}/track` — hanya `APP_ENV=local` + driver fake |

---

## 8. Wallet & Payout

### `GET /seller/wallet`
```json
"data": { "pending_balance":500000, "available_balance":1200000, "paid_balance":3000000,
  "currency":"IDR", "balance_updated_at":"2026-11-09T09:00:00Z" }
```
### `GET /seller/wallet/transactions?cursor=...&bucket=available&type=payout`
### `POST /seller/payouts`
**Header:** `Idempotency-Key` (wajib)
```json
{ "amount": 1000000, "bank_account_id": 2 }
```
Guard: `amount ≤ available_balance`, tidak ada payout aktif, rekening valid.
```json
// 202
"data": { "payout_number":"PO-20261109-0001", "status":"requested", "amount":1000000 }
```
Error: `422 INSUFFICIENT_BALANCE`, `409 PAYOUT_IN_PROGRESS`.

### `GET /seller/payouts?status=requested&cursor=...`

**Implementasi (Phase 7) — Wallet, Payout & Refund:**

| Aspek | Perilaku |
|---|---|
| Komisi | Dibuat saat pembayaran sukses: `base_amount` = subtotal produk (ongkir tidak termasuk), `rate_bp` default `500`, pembulatan ke bawah + `rounding_adjustment`; snapshot di `seller_orders` |
| Resolusi rule | seller > kategori (bila satu kategori) > global; `effective_from/to`, `is_active`, `priority` |
| Saldo awal | `seller_net_amount` masuk bucket `pending` (`sale_pending`) |
| Settlement | `completed` + `settlement_hold_days` (default 7) → `pending → available`; komisi `pending → earned`; dua baris ledger |
| `GET /seller/wallet` | pending/available/paid + `balance_updated_at` |
| `GET /seller/wallet/transactions` | Filter `bucket`/`type`, pagination; ledger read-only |
| Bank account | `GET/POST /seller/bank-accounts`; nomor **encrypted** & dimask di response |
| `POST /seller/payouts` | **Wajib `Idempotency-Key`**; `amount ≤ available`, minimal `PAYOUT_MIN_AMOUNT`; **satu payout aktif** per seller (`409 PAYOUT_IN_PROGRESS`); `422 INSUFFICIENT_BALANCE`; `409 BANK_ACCOUNT_REQUIRED`; status `202` |
| `POST /seller/payouts/{no}/cancel` | Hanya `requested` |
| `GET /admin/commissions` | Filter `seller_id`, `status`, `period=YYYY-MM` |
| `POST /admin/payouts/{id}/process` | `approve|reject|mark_paid`; `mark_paid` memindahkan `available → paid` (2 baris) + `payout_items`; idempoten; audit log |
| `POST /admin/refunds` | Ajukan refund per sub-order (maks `grand_total`) |
| `POST /admin/refunds/{id}/approve` | Panggil gateway refund → debit seller (pending/available) → komisi `reversed` → sub-order `refunded` → payment `partially_refunded`/`refunded` → parent via agregator |
| `POST /admin/refunds/{id}/reject` | Wajib alasan |
| Ledger | Append-only & immutable (model menolak update/delete); CHECK `balance_after = balance_before + amount` |
| Rekonsiliasi | Job harian membandingkan cache saldo vs SUM ledger; selisih dicatat di log + audit |
| Disbursement | Manual (`mark_paid`); provider payout belum diintegrasikan |

---

## 9. Admin

### `GET /admin/sellers?status=under_review&cursor=...`
### `POST /admin/sellers/{id}/approve`
### `POST /admin/sellers/{id}/reject` → `{ "reason": "Dokumen tidak jelas" }`
### `POST /admin/sellers/{id}/suspend` → `{ "reason": "Pelanggaran" }`
### `POST /admin/sellers/{id}/reactivate`
Mengaktifkan kembali seller `suspended` → `active` (permission `suspend_sellers`).
### `GET /admin/seller-documents/{document}`
Mengunduh berkas dokumen seller dari disk privat (permission `manage_sellers`). Mengembalikan berkas (bukan JSON).

### `GET /admin/orders?status=&seller_id=&buyer_id=`
### `GET /admin/payments?status=&provider=`
### `GET /admin/commissions?seller_id=&period=2026-11`
### `GET /admin/payouts?status=requested`
### `POST /admin/payouts/{id}/process`
```json
{ "action": "approve|reject|mark_paid", "provider_reference": "TRX123", "reason": null }
```
**Wajib:** permission `manage_payouts`, audit log, idempotent (lock baris payout).

### `GET /admin/vouchers?scope=&q=&active=`
### `POST /admin/vouchers`
### `PATCH /admin/vouchers/{voucher}`
Permission `manage_vouchers`. `funded_by` diturunkan dari `scope` (`platform`/`seller`);
voucher toko wajib `seller_id`. Persen memakai basis point (10000 = 100%).

### `GET /admin/audit-logs?action=&entity_type=&actor_id=`
Permission `view_reports`. Read-only.

### `GET /seller/commissions?status=`
Komisi milik toko yang sedang login (read-only).

---

## 9b. Voucher, Ulasan & Notifikasi (Phase 8.5)

### Voucher (Promotion)
`POST /checkout` & `POST /checkout/preview` menerima `voucher_code` opsional. Validasi:
aktif, `starts_at ≤ now ≤ expires_at`, `min_purchase`, `usage_limit`, `usage_limit_per_user`.
Perhitungan: `fixed` → `min(value, subtotal)`; `percent` → `min(floor(subtotal × value / 10000), max_discount)`.
Alokasi berbasis `funded_by`:
- `seller` → mengurangi basis komisi (`seller_orders.discount_total`) dan memotong `grand_total`.
- `platform` → dicatat `seller_orders.platform_funded_discount`, ikut memotong `grand_total`,
  **tidak** mengurangi basis komisi.
Error: `422 VOUCHER_NOT_AVAILABLE`. Saat sukses: catat `voucher_usages` (UQ `voucher_id`+`order_id`) dan `used_count++`.
`POST /checkout/preview` juga mengembalikan `available_vouchers` — daftar voucher yang benar-benar
berlaku untuk isi keranjang (sudah lolos window/kuota/min belanja/per-user), berisi
`{code,name,type,scope,funded_by,min_purchase,discount}`. UI checkout memakai daftar ini untuk
menampilkan pilihan diskon berbentuk checkbox (satu voucher aktif per order).

### Ulasan produk (Review)
`POST /orders/{orderNumber}/reviews` (buyer, order `completed`, 1 ulasan per `order_item`).
Status auto `published` untuk MVP. Efek: recompute `products.rating_avg`/`rating_count`
dan `sellers.rating_avg`/`rating_count` dari ulasan `published`.
`GET /products/{slug}/reviews` publik, pagination, hanya `published`.
Error: `409 STATE_TRANSITION_INVALID` (belum selesai), `409 VALIDATION_FAILED` (duplikat), `403` (bukan pemilik).

### Notifikasi in-app (Notification)
Dibuat otomatis di dalam aksi domain (tanpa jaringan): pembayaran sukses (buyer + seller),
sub-order diterima kurir (buyer), sub-order selesai (seller), payout dibayar/ditolak (seller),
refund berhasil (buyer). Endpoint: `GET /notifications?unread=1`, `GET /notifications/{id}` (detail +
payload `data` untuk tautan ke pesanan/sub-pesanan/payout), `POST /notifications/{id}/read`,
`POST /notifications/read-all`.

---

## 10. Daftar Endpoint Lengkap (sesuai spesifikasi)

| Method | Path | Auth | Permission/Policy |
|---|---|---|---|
| POST | `/auth/firebase` | — | — |
| GET | `/me` | ✅ | — |
| PATCH | `/me` | ✅ | — |
| GET | `/me/addresses` | ✅ | own |
| POST | `/me/addresses` | ✅ | own |
| PATCH | `/me/addresses/{id}` | ✅ | AddressPolicy |
| DELETE | `/me/addresses/{id}` | ✅ | AddressPolicy |
| POST | `/seller-applications` | ✅ | — |
| GET | `/seller-applications/me` | ✅ | — |
| POST | `/seller-applications/{id}/documents` | ✅ | own |
| GET | `/seller/dashboard` | ✅ | seller active |
| GET | `/seller/profile` | ✅ | own seller |
| PATCH | `/seller/profile` | ✅ | own seller |
| GET | `/seller/orders` | ✅ | own seller |
| GET | `/seller/orders/{id}` | ✅ | SellerOrderPolicy |
| POST | `/seller/orders/{id}/confirm` | ✅ | SellerOrderPolicy |
| POST | `/seller/orders/{id}/shipments` | ✅ | SellerOrderPolicy |
| GET | `/categories` | — | — |
| GET | `/products` | — | — |
| GET | `/products/{slug}` | — | — |
| GET | `/seller/products` | ✅ | own seller |
| POST | `/seller/products` | ✅ | seller active |
| GET | `/seller/products/{id}` | ✅ | ProductPolicy |
| PATCH | `/seller/products/{id}` | ✅ | ProductPolicy |
| DELETE | `/seller/products/{id}` | ✅ | ProductPolicy |
| POST | `/seller/products/{id}/images` | ✅ | ProductPolicy |
| POST | `/seller/products/{id}/variants` | ✅ | ProductPolicy |
| DELETE | `/seller/products/{id}/images/{image}` | ✅ | ProductImagePolicy |
| GET | `/admin/products` | ✅ | manage_products |
| PATCH | `/admin/products/{id}/status` | ✅ | manage_products |
| PATCH | `/seller/variants/{id}` | ✅ | ProductPolicy |
| PATCH | `/seller/variants/{id}/inventory` | ✅ | ProductPolicy |
| GET | `/cart` | ✅ | own |
| POST | `/cart/items` | ✅ | own |
| PATCH | `/cart/items/{id}` | ✅ | own |
| DELETE | `/cart/items/{id}` | ✅ | own |
| POST | `/checkout/preview` | ✅ | — |
| POST | `/checkout` | ✅ | idempotency |
| GET | `/orders` | ✅ | own |
| GET | `/orders/{orderNumber}` | ✅ | OrderPolicy |
| GET | `/orders/{orderNumber}/invoice` | ✅ | OrderPolicy (paid) |
| POST | `/orders/{orderNumber}/cancel` | ✅ | OrderPolicy |
| GET | `/orders/{orderNumber}/payment` | ✅ | OrderPolicy |
| POST | `/orders/{orderNumber}/payment/retry` | ✅ | OrderPolicy |
| POST | `/webhooks/payments/{provider}` | signature | — |
| POST | `/orders/{orderNumber}/confirm-received` | ✅ | OrderPolicy |
| GET | `/shipping/quotes` | ✅ | — |
| GET | `/seller/shipments` | ✅ | own seller |
| GET | `/seller/shipments/{id}` | ✅ | ShipmentPolicy |
| POST | `/seller/shipments/{id}/dispatch` | ✅ | ShipmentPolicy |
| GET | `/shipments/{id}/tracking` | ✅ | own order/seller |
| POST | `/webhooks/shipping/{provider}` | signature | — |
| GET | `/seller/wallet` | ✅ | own seller |
| GET | `/seller/wallet/transactions` | ✅ | own seller |
| POST | `/seller/payouts` | ✅ | own seller + idempotency |
| GET | `/seller/payouts` | ✅ | own seller |
| GET | `/admin/sellers` | ✅ | manage_sellers |
| POST | `/admin/sellers/{id}/approve` | ✅ | approve_sellers |
| POST | `/admin/sellers/{id}/reject` | ✅ | approve_sellers |
| POST | `/admin/sellers/{id}/suspend` | ✅ | suspend_sellers |
| POST | `/admin/sellers/{id}/reactivate` | ✅ | suspend_sellers |
| GET | `/admin/seller-documents/{document}` | ✅ | manage_sellers |
| GET | `/admin/orders` | ✅ | manage_orders |
| GET | `/admin/payments` | ✅ | manage_payments |
| GET | `/admin/commissions` | ✅ | manage_commissions |
| GET | `/admin/payouts` | ✅ | manage_payouts |
| POST | `/admin/payouts/{id}/process` | ✅ | manage_payouts |
| GET | `/admin/vouchers` | ✅ | manage_vouchers |
| POST | `/admin/vouchers` | ✅ | manage_vouchers |
| PATCH | `/admin/vouchers/{voucher}` | ✅ | manage_vouchers |
| GET | `/admin/audit-logs` | ✅ | view_reports |
| GET | `/seller/commissions` | ✅ | own seller |
| GET | `/products/{slug}/reviews` | — | — |
| POST | `/orders/{orderNumber}/reviews` | ✅ | own + order `completed` |
| GET | `/notifications` | ✅ | own |
| GET | `/notifications/{id}` | ✅ | own |
| POST | `/notifications/{id}/read` | ✅ | own |
| POST | `/notifications/read-all` | ✅ | own |

---

## 11. Permission Catalogue (seed)

`manage_users`, `manage_sellers`, `approve_sellers`, `suspend_sellers`, `manage_products`, `manage_categories`, `manage_orders`, `manage_payments`, `manage_refunds`, `manage_commissions`, `manage_payouts`, `manage_vouchers`, `view_reports`.

| Role | Permissions |
|---|---|
| super_admin | semua (via `Gate::before`) |
| admin | semua kecuali `manage_users` (dapat dikonfigurasi) |
| seller | (policy-based; tidak butuh permission admin) |
| customer | — |

---

## 12. Contoh Error Lengkap

```json
// 422
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "items.0.quantity": ["The quantity must be at least 1."],
    "address_id": ["The selected address is invalid."]
  }
}
```
```json
// 409 idempotency
{
  "success": false,
  "message": "Idempotency key reused with different payload",
  "errors": { "code": "IDEMPOTENCY_CONFLICT" }
}
```
```json
// 403
{ "success": false, "message": "You are not allowed to access this order.", "errors": {} }
```
```json
// 401
{ "success": false, "message": "Token has expired.", "errors": { "code": "AUTH_TOKEN_EXPIRED" } }
```

---

## 13. Webhook Contract (provider → Laravel)

| Aspek | Aturan |
|---|---|
| Signature | Diverifikasi memakai secret provider (HMAC/натив) |
| IP | Allowlist IP provider bila tersedia |
| Durability | Simpan raw body **sebelum** memproses |
| Idempotency | `provider_event_id` unik |
| Amount check | Wajib `==` payment.amount |
| Response | 200 cepat; retry internal via job |
| Replay protection | Event yang sudah `processed` di-ack tanpa efek |
| Logging | Tidak menyimpan secret/PII sensitif |
