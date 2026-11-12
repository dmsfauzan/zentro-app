# 03 — Data Dictionary (Lengkap)

**Database:** MySQL 8.0 · **Engine:** InnoDB · **Charset:** `utf8mb4` · **Collation:** `utf8mb4_unicode_ci`

**Konvensi tipe:**
- `BIGINT UNSIGNED` untuk PK/FK dan semua nominal uang (integer rupiah).
- `BIGINT` untuk nominal (signed, agar mudah audit), atau `BIGINT UNSIGNED` konsisten — **dipilih `BIGINT` (signed)** untuk ledger agar nilai negatif eksplisit.
- `rate_bp` = basis point (1% = 100 bp).
- `DECIMAL(10,7)` untuk koordinat; `DECIMAL(10,2)` untuk `weight_gram` bila perlu pecahan (default `DECIMAL(10,2)`), tetapi **berat disimpan sebagai gram**.
- Enum MySQL dipakai hanya untuk status yang sangat stabil; untuk yang mungkin berkembang, gunakan `VARCHAR` + validasi aplikasi + constraint aplikasi.

> **Catatan implementasi:** Laravel `enum` cast dipakai di aplikasi; kolom DB boleh `VARCHAR(32)` agar penambahan status baru tidak memerlukan `ALTER TABLE` yang mahal. Dokumen ini menuliskan nilai yang diizinkan.

---

## A. IDENTITY & ACCESS

### A1. `users`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| firebase_uid | VARCHAR(128) UQ | no | — | UID dari Firebase (`sub` token) |
| name | VARCHAR(150) | no | — | Nama tampilan |
| email | VARCHAR(191) UQ | no | — | Unik; sinkron dengan Firebase |
| email_verified_at | TIMESTAMP | yes | null | Di-set dari `email_verified` token |
| phone | VARCHAR(30) | yes | null | Format E.164 ideal |
| password | VARCHAR(255) | yes | null | Warisan skeleton; tidak dipakai login Firebase |
| status | VARCHAR(20) | no | `active` | `active`,`inactive`,`suspended`,`banned` |
| last_login_at | TIMESTAMP | yes | null | |
| remember_token | VARCHAR(100) | yes | null | Tidak dipakai untuk API token |
| created_at / updated_at | TIMESTAMP | yes | null | |
| deleted_at | TIMESTAMP | yes | null | Soft delete (hati-hati: user bertransaksi jangan dihapus) |

### A2. `roles`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| name | VARCHAR(255) | no | — | `super_admin`,`admin`,`customer`,`seller` |
| guard_name | VARCHAR(255) | no | `web` | Spatie guard |
| created_at / updated_at | TIMESTAMP | yes | null | |

### A3. `permissions`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| name | VARCHAR(255) | no | — | `manage_users`, dst. |
| guard_name | VARCHAR(255) | no | `web` | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### A4. `user_roles` (pivot polymorphic — Spatie)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| role_id | BIGINT UNSIGNED FK→roles | no | — | ON DELETE CASCADE |
| model_type | VARCHAR(255) | no | — | Morph type, mis. `App\Modules\Identity\Models\User` |
| model_id | BIGINT UNSIGNED | no | — | ID user (morph id) |
| PK | (role_id, model_type, model_id) | | | |

> **Catatan implementasi (Phase 1):** memakai pivot polymorphic milik `spatie/laravel-permission` yang dinamai `user_roles` (dikonfigurasi di `config/permission.php`). Kolomnya `role_id`, `model_type`, `model_id` — bukan `(user_id, role_id)`. Fungsinya setara: satu user dapat memiliki banyak role.

### A5. `role_permissions` (pivot — Spatie)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| permission_id | BIGINT UNSIGNED FK→permissions | no | — | ON DELETE CASCADE |
| role_id | BIGINT UNSIGNED FK→roles | no | — | ON DELETE CASCADE |
| PK | (permission_id, role_id) | | | |

### A6. `user_addresses`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| user_id | BIGINT UNSIGNED FK→users | no | — | CASCADE |
| label | VARCHAR(50) | yes | null | `Rumah`, `Kantor` |
| recipient_name | VARCHAR(150) | no | — | |
| phone | VARCHAR(30) | no | — | |
| address_line | TEXT | no | — | Jalan, nomor, RT/RW, patokan |
| province | VARCHAR(100) | no | — | |
| city | VARCHAR(100) | no | — | |
| district | VARCHAR(100) | yes | null | Kecamatan |
| village | VARCHAR(100) | yes | null | Kelurahan/desa |
| postal_code | VARCHAR(10) | no | — | |
| latitude | DECIMAL(10,7) | yes | null | |
| longitude | DECIMAL(10,7) | yes | null | |
| is_default | BOOLEAN | no | 0 | Maksimal satu default per user (enforced app) |
| created_at / updated_at | TIMESTAMP | yes | null | |

### A7. `user_devices`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| user_id | BIGINT UNSIGNED FK→users | no | — | CASCADE |
| fcm_token | VARCHAR(255) UQ | no | — | Token FCM |
| platform | VARCHAR(20) | no | — | `android`,`ios`,`web` |
| device_name | VARCHAR(100) | yes | null | |
| last_used_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

---

## B. SELLER

### B1. `sellers`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| user_id | BIGINT UNSIGNED FK→users UQ | no | — | RESTRICT; 1 user = 1 toko |
| store_name | VARCHAR(150) | no | — | |
| slug | VARCHAR(191) UQ | no | — | URL-friendly |
| description | TEXT | yes | null | |
| logo_path | VARCHAR(255) | yes | null | Path S3 |
| banner_path | VARCHAR(255) | yes | null | |
| status | VARCHAR(20) | no | `pending` | `pending`,`under_review`,`active`,`rejected`,`suspended` |
| submitted_at | TIMESTAMP | yes | null | |
| approved_at | TIMESTAMP | yes | null | |
| approved_by | BIGINT UNSIGNED FK→users | yes | null | Admin |
| rejected_at | TIMESTAMP | yes | null | |
| rejected_by | BIGINT UNSIGNED FK→users | yes | null | |
| rejection_reason | TEXT | yes | null | |
| suspended_at | TIMESTAMP | yes | null | |
| suspension_reason | TEXT | yes | null | |
| rating_avg | DECIMAL(3,2) | no | 0.00 | 0–5 |
| rating_count | INT UNSIGNED | no | 0 | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### B2. `seller_documents`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| type | VARCHAR(30) | no | — | `ktp`,`npwp`,`siup`,`nib`,`bank_statement`,`other` |
| file_path | VARCHAR(255) | no | — | S3 private |
| file_hash | VARCHAR(64) | yes | null | SHA-256 untuk deteksi duplikat |
| status | VARCHAR(20) | no | `pending` | `pending`,`approved`,`rejected` |
| reviewed_by | BIGINT UNSIGNED FK→users | yes | null | |
| review_note | TEXT | yes | null | |
| reviewed_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### B3. `seller_addresses`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | CASCADE |
| type | VARCHAR(20) | no | `origin` | `origin`,`return`,`billing` |
| label | VARCHAR(50) | yes | null | |
| contact_name | VARCHAR(150) | no | — | |
| phone | VARCHAR(30) | no | — | |
| address_line | TEXT | no | — | |
| province | VARCHAR(100) | no | — | |
| city | VARCHAR(100) | no | — | |
| district | VARCHAR(100) | yes | null | |
| village | VARCHAR(100) | yes | null | |
| postal_code | VARCHAR(10) | no | — | |
| latitude | DECIMAL(10,7) | yes | null | |
| longitude | DECIMAL(10,7) | yes | null | |
| is_default | BOOLEAN | no | 0 | |
| created_at / updated_at | TIMESTAMP | yes | null | |

---

## C. CATALOG

### C1. `categories`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| parent_id | BIGINT UNSIGNED FK→categories | yes | null | Self-reference; RESTRICT |
| name | VARCHAR(150) | no | — | |
| slug | VARCHAR(191) UQ | no | — | |
| description | TEXT | yes | null | |
| icon_path | VARCHAR(255) | yes | null | |
| sort_order | INT | no | 0 | |
| is_active | BOOLEAN | no | 1 | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### C2. `products`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| category_id | BIGINT UNSIGNED FK→categories | no | — | RESTRICT |
| name | VARCHAR(200) | no | — | |
| slug | VARCHAR(191) UQ | no | — | Unik global |
| description | LONGTEXT | yes | null | Sanitasi HTML |
| weight_gram | DECIMAL(10,2) | yes | null | Default varian |
| status | VARCHAR(20) | no | `draft` | `draft`,`active`,`inactive`,`archived`,`rejected` |
| is_featured | BOOLEAN | no | 0 | |
| rating_avg | DECIMAL(3,2) | no | 0.00 | |
| rating_count | INT UNSIGNED | no | 0 | |
| sold_count | INT UNSIGNED | no | 0 | |
| min_price | BIGINT | yes | null | Denormalisasi dari varian |
| max_price | BIGINT | yes | null | |
| published_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |
| deleted_at | TIMESTAMP | yes | null | Soft delete |

### C3. `product_variants`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| product_id | BIGINT UNSIGNED FK→products | no | — | RESTRICT |
| sku | VARCHAR(100) UQ | no | — | Unik global |
| variant_name | VARCHAR(150) | no | — | Mis. `Merah / XL` |
| price | BIGINT UNSIGNED | no | — | Rupiah |
| weight_gram | DECIMAL(10,2) | yes | null | |
| status | VARCHAR(20) | no | `active` | `active`,`inactive` |
| created_at / updated_at | TIMESTAMP | yes | null | |

### C4. `product_images`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| product_id | BIGINT UNSIGNED FK→products | no | — | CASCADE |
| path | VARCHAR(255) | no | — | S3 key |
| alt_text | VARCHAR(200) | yes | null | |
| sort_order | INT | no | 0 | |
| is_primary | BOOLEAN | no | 0 | Maks 1 per produk (app-enforced) |
| created_at / updated_at | TIMESTAMP | yes | null | |

---

## D. INVENTORY

### D1. `inventories`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| variant_id | BIGINT UNSIGNED FK→product_variants UQ | no | — | RESTRICT |
| stock | INT UNSIGNED | no | 0 | Stok fisik |
| reserved_stock | INT UNSIGNED | no | 0 | Stok tereservasi |
| low_stock_threshold | INT UNSIGNED | no | 5 | |
| created_at / updated_at | TIMESTAMP | yes | null | |

**CHECK (app + DB):** `reserved_stock <= stock`.

### D2. `inventory_movements` (append-only)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| inventory_id | BIGINT UNSIGNED FK→inventories | no | — | RESTRICT |
| variant_id | BIGINT UNSIGNED FK→product_variants | no | — | RESTRICT |
| type | VARCHAR(20) | no | — | `in`,`out`,`adjustment`,`reservation`,`release`,`commit` |
| quantity | INT | no | — | Signed |
| stock_before | INT | no | — | |
| stock_after | INT | no | — | |
| reserved_before | INT | no | — | |
| reserved_after | INT | no | — | |
| reference_type | VARCHAR(50) | yes | null | `order`,`adjustment`,`checkout` |
| reference_id | BIGINT UNSIGNED | yes | null | |
| actor_id | BIGINT UNSIGNED FK→users | yes | null | null = system |
| note | VARCHAR(255) | yes | null | |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | Tanpa updated_at |

### D3. `inventory_reservations`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| inventory_id | BIGINT UNSIGNED FK→inventories | no | — | RESTRICT |
| variant_id | BIGINT UNSIGNED FK→product_variants | no | — | RESTRICT |
| order_id | BIGINT UNSIGNED FK→orders | no | — | RESTRICT |
| order_item_id | BIGINT UNSIGNED FK→order_items | no | — | RESTRICT |
| quantity | INT UNSIGNED | no | — | |
| status | VARCHAR(20) | no | `active` | `active`,`committed`,`released`,`expired` |
| expires_at | TIMESTAMP | no | — | |
| committed_at | TIMESTAMP | yes | null | |
| released_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

---

## E. CART

### E1. `carts`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| user_id | BIGINT UNSIGNED FK→users UQ | no | — | CASCADE |
| created_at / updated_at | TIMESTAMP | yes | null | |

### E2. `cart_items`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| cart_id | BIGINT UNSIGNED FK→carts | no | — | CASCADE |
| variant_id | BIGINT UNSIGNED FK→product_variants | no | — | RESTRICT |
| quantity | INT UNSIGNED | no | 1 | |
| created_at / updated_at | TIMESTAMP | yes | null | |
| UQ | (cart_id, variant_id) | | | |

---

## F. ORDERS

### F1. `orders`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| order_number | VARCHAR(32) UQ | no | — | Mis. `ZTR-20261109-000001` |
| buyer_id | BIGINT UNSIGNED FK→users | no | — | RESTRICT |
| voucher_id | BIGINT UNSIGNED FK→vouchers | yes | null | SET NULL |
| status | VARCHAR(24) | no | `pending_payment` | Lihat state machine |
| subtotal | BIGINT | no | 0 | Σ seller subtotal |
| discount_total | BIGINT | no | 0 | |
| shipping_total | BIGINT | no | 0 | Σ ongkir seller |
| grand_total | BIGINT | no | 0 | |
| currency | CHAR(3) | no | `IDR` | |
| paid_at | TIMESTAMP | yes | null | |
| expires_at | TIMESTAMP | yes | null | Batas bayar |
| completed_at | TIMESTAMP | yes | null | |
| cancelled_at | TIMESTAMP | yes | null | |
| is_reviewed | BOOLEAN | no | 0 | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### F2. `seller_orders`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| order_id | BIGINT UNSIGNED FK→orders | no | — | RESTRICT |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| seller_order_number | VARCHAR(40) UQ | no | — | |
| status | VARCHAR(24) | no | `pending` | Lihat state machine |
| subtotal | BIGINT | no | 0 | Sebelum diskon |
| discount_total | BIGINT | no | 0 | |
| shipping_cost | BIGINT | no | 0 | |
| grand_total | BIGINT | no | 0 | |
| commission_base_amount | BIGINT | no | 0 | Snapshot dasar komisi |
| commission_rate_bp | BIGINT | no | 0 | Snapshot rate |
| commission_amount | BIGINT | no | 0 | |
| seller_net_amount | BIGINT | no | 0 | |
| voucher_discount_borne_by_seller | BIGINT | no | 0 | |
| platform_funded_discount | BIGINT | no | 0 | |
| refunded_product_amount | BIGINT | no | 0 | Kumulatif refund produk (mata uang pembeli) — Fase 8A |
| refunded_shipping_amount | BIGINT | no | 0 | Kumulatif refund ongkir — Fase 8A |
| seller_refunded_amount | BIGINT | no | 0 | Kumulatif debit seller atas refund — Fase 8A |
| commission_reversed_amount | BIGINT | no | 0 | Kumulatif pembalik komisi — Fase 8A |
| risk_hold_amount | BIGINT | no | 0 | Reserve risiko sub-order — Fase 8B |
| risk_hold_release_at | TIMESTAMP | yes | null | Kapan reserve boleh dilepas — Fase 8B |
| risk_hold_released_at | TIMESTAMP | yes | null | Kapan reserve dilepas — Fase 8B |
| confirmed_at | TIMESTAMP | yes | null | |
| shipped_at | TIMESTAMP | yes | null | |
| delivered_at | TIMESTAMP | yes | null | |
| completed_at | TIMESTAMP | yes | null | |
| cancelled_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |
| UQ | (order_id, seller_id) | | | 1 seller 1 suborder per checkout |

### F3. `order_items`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| order_id | BIGINT UNSIGNED FK→orders | no | — | RESTRICT |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders | no | — | RESTRICT |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| product_id | BIGINT UNSIGNED FK→products | yes | null | SET NULL |
| variant_id | BIGINT UNSIGNED FK→product_variants | yes | null | SET NULL |
| product_name_snapshot | VARCHAR(200) | no | — | |
| sku_snapshot | VARCHAR(100) | no | — | |
| variant_name_snapshot | VARCHAR(150) | yes | null | |
| price_snapshot | BIGINT | no | — | Harga satuan saat checkout |
| quantity | INT UNSIGNED | no | — | |
| discount_amount | BIGINT | no | 0 | |
| line_total | BIGINT | no | — | `price*qty - discount` |
| weight_gram_snapshot | DECIMAL(10,2) | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### F4. `order_addresses`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| order_id | BIGINT UNSIGNED FK→orders | no | — | RESTRICT |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders | yes | null | RESTRICT (bila per seller) |
| type | VARCHAR(10) | no | `shipping` | `shipping`,`billing` |
| recipient_name | VARCHAR(150) | no | — | |
| phone | VARCHAR(30) | no | — | |
| address_line | TEXT | no | — | |
| province | VARCHAR(100) | no | — | |
| city | VARCHAR(100) | no | — | |
| district | VARCHAR(100) | yes | null | |
| village | VARCHAR(100) | yes | null | |
| postal_code | VARCHAR(10) | no | — | |
| latitude | DECIMAL(10,7) | yes | null | |
| longitude | DECIMAL(10,7) | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### F5. `order_status_histories` (append-only)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| order_id | BIGINT UNSIGNED FK→orders | no | — | RESTRICT |
| from_status | VARCHAR(24) | yes | null | null = awal |
| to_status | VARCHAR(24) | no | — | |
| changed_by | BIGINT UNSIGNED FK→users | yes | null | null = system |
| reason | VARCHAR(255) | yes | null | |
| metadata | JSON | yes | null | |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

### F6. `seller_order_status_histories` (append-only)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders | no | — | RESTRICT |
| from_status | VARCHAR(24) | yes | null | |
| to_status | VARCHAR(24) | no | — | |
| changed_by | BIGINT UNSIGNED FK→users | yes | null | |
| reason | VARCHAR(255) | yes | null | |
| metadata | JSON | yes | null | |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

---

## G. PAYMENT

### G1. `payments`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| order_id | BIGINT UNSIGNED FK→orders UQ | no | — | RESTRICT; 1 order = 1 payment |
| provider | VARCHAR(30) | no | — | `midtrans`,`xendit` |
| provider_reference | VARCHAR(191) | yes | null | UNIQUE(provider, provider_reference) |
| payment_method | VARCHAR(50) | yes | null | `qris`,`va_bca`,`gopay` |
| status | VARCHAR(24) | no | `pending` | |
| amount | BIGINT | no | — | Harus = order.grand_total |
| currency | CHAR(3) | no | `IDR` | |
| paid_at | TIMESTAMP | yes | null | |
| expires_at | TIMESTAMP | yes | null | |
| provider_payload | JSON | yes | null | Snapshot aman (tanpa secret) |
| created_at / updated_at | TIMESTAMP | yes | null | |

### G2. `payment_attempts`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| payment_id | BIGINT UNSIGNED FK→payments | no | — | RESTRICT |
| provider | VARCHAR(30) | no | — | |
| provider_reference | VARCHAR(191) | yes | null | |
| status | VARCHAR(20) | no | `initiated` | `initiated`,`pending`,`succeeded`,`failed`,`expired` |
| amount | BIGINT | no | — | |
| request_payload | JSON | yes | null | Redact sensitive |
| response_payload | JSON | yes | null | |
| failure_code | VARCHAR(100) | yes | null | |
| failure_message | TEXT | yes | null | |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

### G3. `payment_webhook_events`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| provider | VARCHAR(30) | no | — | |
| provider_event_id | VARCHAR(191) UQ | no | — | Idempotency |
| event_type | VARCHAR(100) | no | — | |
| provider_reference | VARCHAR(191) | yes | null | |
| status | VARCHAR(20) | no | `received` | `received`,`processed`,`ignored`,`failed` |
| payload | JSON | no | — | Raw body |
| signature | VARCHAR(255) | yes | null | |
| processing_error | TEXT | yes | null | |
| received_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| processed_at | TIMESTAMP | yes | null | |

### G4. `refunds`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| refund_number | VARCHAR(40) UQ | no | — | |
| payment_id | BIGINT UNSIGNED FK→payments | no | — | RESTRICT |
| order_id | BIGINT UNSIGNED FK→orders | no | — | RESTRICT |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders | yes | null | RESTRICT |
| amount | BIGINT | no | — | |
| status | VARCHAR(20) | no | `requested` | |
| reason | TEXT | yes | null | |
| rejection_reason | TEXT | yes | null | Ditambahkan Phase 7 |
| provider_reference | VARCHAR(191) | yes | null | |
| requested_by | BIGINT UNSIGNED FK→users | no | — | |
| approved_by | BIGINT UNSIGNED FK→users | yes | null | |
| processed_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

---

## H. SHIPPING

### H1. `shipments`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders | no | — | RESTRICT |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| provider | VARCHAR(30) | no | — | `jne`,`jnt`,`sicepat`,dst |
| service_code | VARCHAR(50) | yes | null | |
| tracking_number | VARCHAR(100) UQ | yes | null | Unik bila ada |
| provider_shipment_id | VARCHAR(191) | yes | null | |
| status | VARCHAR(20) | no | `pending` | `pending`,`ready`,`dispatched`,`in_transit`,`delivered`,`failed`,`returned` |
| shipping_cost | BIGINT | no | 0 | |
| weight_gram | DECIMAL(10,2) | yes | null | |
| origin_city | VARCHAR(100) | yes | null | |
| destination_city | VARCHAR(100) | yes | null | |
| label_path | VARCHAR(255) | yes | null | |
| dispatched_at | TIMESTAMP | yes | null | |
| delivered_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### H2. `shipment_histories` (append-only)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| shipment_id | BIGINT UNSIGNED FK→shipments | no | — | RESTRICT |
| status | VARCHAR(20) | no | — | |
| description | VARCHAR(255) | yes | null | |
| location | VARCHAR(150) | yes | null | |
| occurred_at | TIMESTAMP | no | — | Waktu dari provider |
| provider_event_id | VARCHAR(191) | yes | null | **UNIQUE** — kunci idempotensi timeline (Phase 6) |
| metadata | JSON | yes | null | |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

### H3. `shipping_webhook_events` (tambahan Phase 6 — deviasi terdokumentasi)

Docs/06 §13 mensyaratkan webhook disimpan **durable sebelum diproses**. Karena tidak ada tabel webhook untuk domain shipping, ditambahkan tabel yang mencerminkan `payment_webhook_events`:

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| provider | VARCHAR(30) | no | — | `manual`,`fake`, agregator |
| provider_event_id | VARCHAR(191) | no | — | **UNIQUE** — idempotensi |
| event_type | VARCHAR(100) | no | — | |
| provider_shipment_id | VARCHAR(191) | yes | null | |
| tracking_number | VARCHAR(100) | yes | null | |
| status | VARCHAR(20) | no | `received` | `received`,`processed`,`ignored`,`failed` |
| payload | JSON | no | — | Raw body |
| signature | VARCHAR(255) | yes | null | |
| processing_error | TEXT | yes | null | |
| received_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| processed_at | TIMESTAMP | yes | null | |

---

## I. COMMISSION & WALLET

### I1. `commission_rules`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| name | VARCHAR(150) | no | — | |
| scope | VARCHAR(20) | no | `global` | `global`,`category`,`seller` |
| category_id | BIGINT UNSIGNED FK→categories | yes | null | Wajib bila scope=category (app) |
| seller_id | BIGINT UNSIGNED FK→sellers | yes | null | Wajib bila scope=seller (app) |
| rate_bp | BIGINT UNSIGNED | no | — | 1000 = 10% |
| includes_shipping | BOOLEAN | no | 0 | Ongkir masuk basis komisi? |
| effective_from | DATE | no | — | |
| effective_to | DATE | yes | null | null = tanpa batas |
| priority | INT | no | 0 | Rule paling spesifik menang |
| is_active | BOOLEAN | no | 1 | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### I2. `commissions`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders UQ | no | — | RESTRICT |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| commission_rule_id | BIGINT UNSIGNED FK→commission_rules | yes | null | SET NULL |
| base_amount | BIGINT | no | — | Snapshot |
| rate_bp | BIGINT | no | — | Snapshot |
| commission_amount | BIGINT | no | — | |
| rounding_adjustment | BIGINT | no | 0 | |
| status | VARCHAR(20) | no | `pending` | `pending`,`earned`,`reversed` |
| earned_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### I2b. `refunds` (komponen & idempotensi — Fase 8A)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| amount | BIGINT | no | — | Total refund (mata uang pembeli) |
| product_amount | BIGINT | no | 0 | Porsi produk dari refund |
| shipping_amount | BIGINT | no | 0 | Porsi ongkir dari refund |
| seller_debit_amount | BIGINT | no | 0 | Debit seller aktual (hasil alokasi) |
| commission_reversal_amount | BIGINT | no | 0 | Pembalik komisi aktual |
| provider_reference | VARCHAR(191) | yes | null | Bukti sukses provider |
| provider_refund_key | VARCHAR(64) | yes | null | Idempotency key gateway (dibuat sebelum panggil) |
| provider_refunded_at | TIMESTAMP | yes | null | Waktu provider sukses |

### I3. `seller_wallets`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_id | BIGINT UNSIGNED FK→sellers UQ | no | — | RESTRICT |
| pending_balance | BIGINT | no | 0 | Belum memenuhi settlement |
| available_balance | BIGINT | no | 0 | Siap dicairkan |
| payout_reserved_balance | BIGINT | no | 0 | Dikunci untuk payout berjalan (Fase 8A) |
| risk_hold_balance | BIGINT | no | 0 | Reserve risiko, dilepas setelah masa tahan (Fase 8B) |
| debt_balance | BIGINT | no | 0 | Utang seller (refund melebihi saldo) — eksplisit (Fase 8B) |
| paid_balance | BIGINT | no | 0 | Sudah dibayarkan |
| balance_updated_at | DATETIME | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### I3b. `seller_risk_profiles` (Fase 8B)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_id | BIGINT UNSIGNED FK→sellers UQ | no | — | 1 profil per seller |
| reserve_rate_bp | INT UNSIGNED | no | 0 | 500 = 5% reserve risiko |
| hold_days | INT UNSIGNED | no | 0 | Masa tahan sebelum reserve dilepas |
| reason | VARCHAR(191) | yes | null | Alasan penetapan |
| updated_by | BIGINT UNSIGNED FK→users | yes | null | SET NULL |
| created_at / updated_at | TIMESTAMP | yes | null | |

### I4. `wallet_transactions` (append-only, immutable)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| wallet_id | BIGINT UNSIGNED FK→seller_wallets | no | — | RESTRICT |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders | yes | null | RESTRICT |
| payout_id | BIGINT UNSIGNED FK→seller_payouts | yes | null | RESTRICT |
| refund_id | BIGINT UNSIGNED FK→refunds | yes | null | RESTRICT (Phase 7) |
| reversal_of_id | BIGINT UNSIGNED FK→wallet_transactions | yes | null | RESTRICT |
| type | VARCHAR(32) | no | — | `sale_pending`,`settlement_release`,`commission`,`payout`,`payout_hold`,`payout_release`,`risk_hold`,`risk_hold_release`,`refund_debit`,`debt_offset`,`adjustment`,`reversal` |
| balance_bucket | VARCHAR(24) | no | — | `pending`,`available`,`payout_reserved`,`risk_hold`,`debt`,`paid` |
| amount | BIGINT | no | — | Signed |
| balance_before | BIGINT | no | — | |
| balance_after | BIGINT | no | — | |
| reference_type | VARCHAR(50) | yes | null | |
| reference_id | BIGINT UNSIGNED | yes | null | |
| description | VARCHAR(255) | yes | null | |
| created_by | BIGINT UNSIGNED FK→users | yes | null | null = system |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

`CHECK (balance_after = balance_before + amount)` dapat ditambahkan di MySQL 8.0.16+.

### I5. `seller_payouts`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| payout_number | VARCHAR(40) UQ | no | — | |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| amount | BIGINT | no | — | |
| status | VARCHAR(20) | no | `requested` | |
| bank_name_snapshot | VARCHAR(100) | no | — | |
| bank_account_number_snapshot | TEXT | no | — | **Encrypted** |
| bank_account_name_snapshot | VARCHAR(150) | no | — | |
| provider_reference | VARCHAR(191) | yes | null | |
| requested_by | BIGINT UNSIGNED FK→users | no | — | |
| processed_by | BIGINT UNSIGNED FK→users | yes | null | |
| requested_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| processed_at | TIMESTAMP | yes | null | |
| paid_at | TIMESTAMP | yes | null | |
| rejection_reason | TEXT | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### I6. `payout_items`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| payout_id | BIGINT UNSIGNED FK→seller_payouts | no | — | RESTRICT |
| wallet_transaction_id | BIGINT UNSIGNED FK→wallet_transactions | yes | null | RESTRICT |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders | yes | null | RESTRICT |
| amount | BIGINT | no | — | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### I7. `seller_bank_accounts` (tambahan Phase 7 — deviasi terdokumentasi)

Docs/06 memakai `bank_account_id` pada payout, tetapi belum ada entitas rekening bank seller. Ditambahkan tabel (1 utama per seller, siap multi-rekening):

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| bank_name | VARCHAR(100) | no | — | |
| account_number | TEXT | no | — | **Encrypted** |
| account_name | VARCHAR(150) | no | — | |
| is_default | BOOLEAN | no | 0 | |
| created_at / updated_at | TIMESTAMP | yes | null | |

---

## J. SUPPORTING MODULES

### J1. `vouchers`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| code | VARCHAR(50) UQ | no | — | Disimpan uppercase |
| name | VARCHAR(150) | no | — | |
| type | VARCHAR(10) | no | — | `fixed`,`percent` |
| value | BIGINT UNSIGNED | no | — | fixed=rupiah, percent=basis point |
| min_purchase | BIGINT | no | 0 | |
| max_discount | BIGINT | yes | null | Untuk percent |
| scope | VARCHAR(10) | no | `platform` | `platform`,`seller` |
| seller_id | BIGINT UNSIGNED FK→sellers | yes | null | Wajib bila scope=seller |
| funded_by | VARCHAR(10) | no | `platform` | `platform`,`seller` |
| usage_limit | INT UNSIGNED | yes | null | null = unlimited |
| usage_limit_per_user | INT UNSIGNED | yes | null | |
| used_count | INT UNSIGNED | no | 0 | |
| starts_at | TIMESTAMP | no | — | |
| expires_at | TIMESTAMP | yes | null | |
| is_active | BOOLEAN | no | 1 | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### J2. `voucher_usages`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| voucher_id | BIGINT UNSIGNED FK→vouchers | no | — | RESTRICT |
| user_id | BIGINT UNSIGNED FK→users | no | — | RESTRICT |
| order_id | BIGINT UNSIGNED FK→orders | no | — | RESTRICT |
| seller_order_id | BIGINT UNSIGNED FK→seller_orders | yes | null | RESTRICT |
| discount_amount | BIGINT | no | — | |
| used_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| UQ | (voucher_id, order_id) | | | |

### J3. `reviews`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| order_id | BIGINT UNSIGNED FK→orders | no | — | RESTRICT |
| order_item_id | BIGINT UNSIGNED FK→order_items UQ | no | — | 1 ulasan per item |
| product_id | BIGINT UNSIGNED FK→products | no | — | RESTRICT |
| seller_id | BIGINT UNSIGNED FK→sellers | no | — | RESTRICT |
| user_id | BIGINT UNSIGNED FK→users | no | — | RESTRICT |
| rating | TINYINT UNSIGNED | no | — | 1..5 |
| comment | TEXT | yes | null | |
| images | JSON | yes | null | Array path |
| status | VARCHAR(10) | no | `pending` | `pending`,`published`,`hidden` |
| published_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### J4. `notifications`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | CHAR(36) PK | no | — | UUID |
| user_id | BIGINT UNSIGNED FK→users | no | — | CASCADE |
| type | VARCHAR(100) | no | — | |
| title | VARCHAR(200) | no | — | |
| body | TEXT | no | — | |
| data | JSON | yes | null | |
| read_at | TIMESTAMP | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### J5. `outbox_events`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| aggregate_type | VARCHAR(100) | no | — | |
| aggregate_id | BIGINT UNSIGNED | no | — | |
| event_type | VARCHAR(100) | no | — | |
| payload | JSON | no | — | |
| status | VARCHAR(12) | no | `pending` | `pending`,`processing`,`published`,`failed` |
| attempts | INT UNSIGNED | no | 0 | |
| available_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| published_at | TIMESTAMP | yes | null | |
| last_error | TEXT | yes | null | |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

### J6. `audit_logs` (append-only)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| actor_id | BIGINT UNSIGNED FK→users | yes | null | null = system |
| action | VARCHAR(100) | no | — | `seller.approve`, `payout.process` |
| entity_type | VARCHAR(100) | no | — | |
| entity_id | BIGINT UNSIGNED | no | — | |
| before | JSON | yes | null | |
| after | JSON | yes | null | Redact PII/secrets |
| ip_address | VARCHAR(45) | yes | null | IPv6-safe |
| user_agent | VARCHAR(255) | yes | null | |
| request_id | CHAR(36) | yes | null | |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

### J7. `platform_settings`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| key | VARCHAR(100) UQ | no | — | `settlement.hold_days` |
| value | JSON | no | — | |
| description | VARCHAR(255) | yes | null | |
| created_at / updated_at | TIMESTAMP | yes | null | |

### J8. `idempotency_keys`

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK | no | auto | |
| key | VARCHAR(100) UQ | no | — | Dari header `Idempotency-Key` |
| user_id | BIGINT UNSIGNED FK→users | yes | null | |
| scope | VARCHAR(100) | no | — | Mis. `checkout`, `payout.create` |
| request_hash | CHAR(64) | no | — | SHA-256 body+user+scope |
| response_snapshot | JSON | yes | null | |
| response_status | SMALLINT UNSIGNED | yes | null | |
| expires_at | TIMESTAMP | no | — | Mis. 24 jam |
| created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |

---

## Ringkasan Jumlah Tabel

| Domain | Jumlah |
|---|---|
| Identity & Access | 7 |
| Seller | 3 |
| Catalog | 4 |
| Inventory | 3 |
| Cart | 2 |
| Orders | 6 |
| Payment | 4 |
| Shipping | 2 |
| Commission & Wallet | 6 |
| Promotion/Review/Notification/Platform | 8 |
| **TOTAL** | **45** |

Catatan: `sessions`, `cache`, `jobs`, `failed_jobs`, `personal_access_tokens` (bila Sanctum dipakai) adalah tabel infrastruktur Laravel dan tidak dihitung di atas.
