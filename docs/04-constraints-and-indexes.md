# 04 — Foreign Keys, Unique Constraints & Index Plan

**Database:** MySQL 8.0 · **Engine:** InnoDB

---

## 1. Kebijakan Referential Integrity

| Kelas data | ON DELETE | ON UPDATE | Alasan |
|---|---|---|---|
| Data master non-transaksional (alamat, gambar, device) | CASCADE | RESTRICT | Aman dihapus bersama induknya |
| Data master yang punya riwayat (sellers, products, categories) | RESTRICT | RESTRICT | Riwayat order harus utuh |
| Data transaksi (orders, seller_orders, order_items, payments) | **RESTRICT** | RESTRICT | Tidak boleh hilang |
| Data finansial (commissions, wallet_transactions, payouts, payout_items) | **RESTRICT** | RESTRICT | Audit & kepatuhan |
| Ledger/append-only (inventory_movements, status histories) | **RESTRICT** | RESTRICT | Immutable |
| Pivot RBAC (`user_roles`, `role_permissions`) | CASCADE | CASCADE | Aman |
| Reference opsional non-kritis (order_items.product_id) | SET NULL | RESTRICT | Snapshot tetap ada |

**Soft delete dipakai pada:** `users`, `products` (dan kandidat `sellers` bila diperlukan). **Tidak pernah** pada tabel transaksi/finansial.

---

## 2. Daftar Foreign Key

| Tabel | Kolom | Referensi | On Delete |
|---|---|---|---|
| user_roles | role_id | roles.id | CASCADE |
| user_roles | model_id | users.id (polymorphic `model_type`) | CASCADE |
| role_permissions | role_id | roles.id | CASCADE |
| role_permissions | permission_id | permissions.id | CASCADE |
| user_addresses | user_id | users.id | CASCADE |
| user_devices | user_id | users.id | CASCADE |
| sellers | user_id | users.id | RESTRICT |
| sellers | approved_by | users.id | SET NULL |
| sellers | rejected_by | users.id | SET NULL |
| seller_documents | seller_id | sellers.id | RESTRICT |
| seller_documents | reviewed_by | users.id | SET NULL |
| seller_addresses | seller_id | sellers.id | CASCADE |
| categories | parent_id | categories.id | RESTRICT |
| products | seller_id | sellers.id | RESTRICT |
| products | category_id | categories.id | RESTRICT |
| product_variants | product_id | products.id | RESTRICT |
| product_images | product_id | products.id | CASCADE |
| inventories | variant_id | product_variants.id | RESTRICT |
| inventory_movements | inventory_id | inventories.id | RESTRICT |
| inventory_movements | variant_id | product_variants.id | RESTRICT |
| inventory_movements | actor_id | users.id | SET NULL |
| inventory_reservations | inventory_id | inventories.id | RESTRICT |
| inventory_reservations | variant_id | product_variants.id | RESTRICT |
| inventory_reservations | order_id | orders.id | RESTRICT |
| inventory_reservations | order_item_id | order_items.id | RESTRICT |
| carts | user_id | users.id | CASCADE |
| cart_items | cart_id | carts.id | CASCADE |
| cart_items | variant_id | product_variants.id | RESTRICT |
| orders | buyer_id | users.id | RESTRICT |
| orders | voucher_id | vouchers.id | SET NULL |
| seller_orders | order_id | orders.id | RESTRICT |
| seller_orders | seller_id | sellers.id | RESTRICT |
| order_items | order_id | orders.id | RESTRICT |
| order_items | seller_order_id | seller_orders.id | RESTRICT |
| order_items | seller_id | sellers.id | RESTRICT |
| order_items | product_id | products.id | SET NULL |
| order_items | variant_id | product_variants.id | SET NULL |
| order_addresses | order_id | orders.id | RESTRICT |
| order_addresses | seller_order_id | seller_orders.id | RESTRICT |
| order_status_histories | order_id | orders.id | RESTRICT |
| order_status_histories | changed_by | users.id | SET NULL |
| seller_order_status_histories | seller_order_id | seller_orders.id | RESTRICT |
| seller_order_status_histories | changed_by | users.id | SET NULL |
| payments | order_id | orders.id | RESTRICT |
| payment_attempts | payment_id | payments.id | RESTRICT |
| refunds | payment_id | payments.id | RESTRICT |
| refunds | order_id | orders.id | RESTRICT |
| refunds | seller_order_id | seller_orders.id | RESTRICT |
| refunds | requested_by | users.id | RESTRICT |
| refunds | approved_by | users.id | SET NULL |
| shipments | seller_order_id | seller_orders.id | RESTRICT |
| shipments | seller_id | sellers.id | RESTRICT |
| shipment_histories | shipment_id | shipments.id | RESTRICT |
| commission_rules | category_id | categories.id | RESTRICT |
| commission_rules | seller_id | sellers.id | RESTRICT |
| commissions | seller_order_id | seller_orders.id | RESTRICT |
| commissions | seller_id | sellers.id | RESTRICT |
| commissions | commission_rule_id | commission_rules.id | SET NULL |
| seller_wallets | seller_id | sellers.id | RESTRICT |
| wallet_transactions | seller_id | sellers.id | RESTRICT |
| wallet_transactions | wallet_id | seller_wallets.id | RESTRICT |
| wallet_transactions | seller_order_id | seller_orders.id | RESTRICT |
| wallet_transactions | payout_id | seller_payouts.id | RESTRICT |
| wallet_transactions | reversal_of_id | wallet_transactions.id | RESTRICT |
| wallet_transactions | created_by | users.id | SET NULL |
| seller_payouts | seller_id | sellers.id | RESTRICT |
| seller_payouts | requested_by | users.id | RESTRICT |
| seller_payouts | processed_by | users.id | SET NULL |
| payout_items | payout_id | seller_payouts.id | RESTRICT |
| payout_items | wallet_transaction_id | wallet_transactions.id | RESTRICT |
| payout_items | seller_order_id | seller_orders.id | RESTRICT |
| seller_bank_accounts | seller_id | sellers.id | RESTRICT |
| wallet_transactions | refund_id | refunds.id | RESTRICT |
| vouchers | seller_id | sellers.id | RESTRICT |
| voucher_usages | voucher_id | vouchers.id | RESTRICT |
| voucher_usages | user_id | users.id | RESTRICT |
| voucher_usages | order_id | orders.id | RESTRICT |
| voucher_usages | seller_order_id | seller_orders.id | RESTRICT |
| reviews | order_id | orders.id | RESTRICT |
| reviews | order_item_id | order_items.id | RESTRICT |
| reviews | product_id | products.id | RESTRICT |
| reviews | seller_id | sellers.id | RESTRICT |
| reviews | user_id | users.id | RESTRICT |
| notifications | user_id | users.id | CASCADE |
| audit_logs | actor_id | users.id | SET NULL |
| idempotency_keys | user_id | users.id | SET NULL |

**Circular FK note:** `wallet_transactions.payout_id → seller_payouts.id` dan `payout_items.wallet_transaction_id → wallet_transactions.id` saling merujuk. Solusi migrasi: buat `seller_payouts` lebih dulu, lalu `wallet_transactions` (dengan FK payout_id), lalu `payout_items`. Keduanya `nullable` di sisi yang memungkinkan.

---

## 3. Unique Constraints

| Tabel | Kolom | Nama constraint | Catatan |
|---|---|---|---|
| users | firebase_uid | `users_firebase_uid_unique` | Kunci identitas |
| users | email | `users_email_unique` | Unik; bila perlu multi-identitas, ubah ke composite dengan provider |
| sellers | user_id | `sellers_user_id_unique` | 1 user 1 toko |
| sellers | slug | `sellers_slug_unique` | |
| seller_documents | (seller_id, type, file_hash) | `seller_documents_dedup_unique` | Cegah upload identik berulang |
| categories | slug | `categories_slug_unique` | |
| products | slug | `products_slug_unique` | |
| product_variants | sku | `product_variants_sku_unique` | |
| inventories | variant_id | `inventories_variant_id_unique` | 1 inventory per varian |
| cart_items | (cart_id, variant_id) | `cart_items_cart_variant_unique` | |
| carts | user_id | `carts_user_id_unique` | 1 cart per user |
| orders | order_number | `orders_order_number_unique` | |
| seller_orders | seller_order_number | `seller_orders_number_unique` | |
| seller_orders | (order_id, seller_id) | `seller_orders_order_seller_unique` | 1 suborder per seller per checkout |
| payments | order_id | `payments_order_id_unique` | 1 payment per checkout |
| payments | (provider, provider_reference) | `payments_provider_reference_unique` | NULL aman |
| payment_webhook_events | provider_event_id | `webhook_provider_event_unique` | **Idempotency kunci** |
| payments | (order_id) | lihat di atas | |
| refunds | refund_number | `refunds_number_unique` | |
| shipments | tracking_number | `shipments_tracking_unique` | NULL aman |
| shipment_histories | provider_event_id | `shipment_hist_provider_event_unique` | Idempotensi timeline |
| shipping_webhook_events | provider_event_id | `shipping_webhook_provider_event_unique` | Idempotensi webhook |
| commissions | seller_order_id | `commissions_seller_order_unique` | 1 komisi per suborder |
| seller_wallets | seller_id | `seller_wallets_seller_unique` | |
| seller_payouts | payout_number | `seller_payouts_number_unique` | |
| vouchers | code | `vouchers_code_unique` | Uppercase |
| voucher_usages | (voucher_id, order_id) | `voucher_usages_voucher_order_unique` | |
| reviews | order_item_id | `reviews_order_item_unique` | 1 ulasan per item |
| user_devices | fcm_token | `user_devices_token_unique` | |
| idempotency_keys | key | `idempotency_keys_key_unique` | Global; scope disimpan di kolom |
| platform_settings | key | `platform_settings_key_unique` | |
| role_permissions | (permission_id, role_id) | PK | |
| user_roles | (role_id, model_type, model_id) | PK | Pivot polymorphic Spatie |

---

## 4. Index Plan

### 4.1 Index wajib (sesuai kebutuhan bisnis)

| Tabel | Index | Kolom | Query yang dilayani |
|---|---|---|---|
| users | `users_firebase_uid_unique` | firebase_uid | Auth lookup (hot path) |
| users | `users_email_unique` | email | Sinkronisasi/log |
| users | `users_status_idx` | status | Admin filter |
| sellers | `sellers_status_idx` | (status, created_at) | Antrian approval |
| sellers | `sellers_slug_unique` | slug | Storefront |
| products | `products_seller_status_idx` | (seller_id, status) | Dashboard seller |
| products | `products_category_status_idx` | (category_id, status) | Listing kategori |
| products | `products_status_created_idx` | (status, created_at) | Feed terbaru |
| products | `products_status_price_idx` | (status, min_price) | Sorting harga |
| products | `products_status_sold_idx` | (status, sold_count) | Terlaris |
| products | `products_fulltext_idx` | FULLTEXT(name, description) | Search (lihat §4.4) |
| product_variants | `product_variants_sku_unique` | sku | Lookup SKU |
| product_variants | `product_variants_product_status_idx` | (product_id, status) | Detail produk |
| inventories | `inventories_variant_id_unique` | variant_id | Lock stok |
| inventory_movements | `inv_movements_inventory_created_idx` | (inventory_id, created_at) | Audit stok |
| inventory_movements | `inv_movements_reference_idx` | (reference_type, reference_id) | Trace order |
| inventory_reservations | `inv_res_order_idx` | (order_id, status) | Konfirmasi/lepas |
| inventory_reservations | `inv_res_status_expiry_idx` | (status, expires_at) | **Job cleanup** |
| cart_items | `cart_items_cart_variant_unique` | (cart_id, variant_id) | Cart |
| orders | `orders_order_number_unique` | order_number | Detail order |
| orders | `orders_buyer_created_idx` | (buyer_id, created_at) | Riwayat buyer |
| orders | `orders_status_expires_idx` | (status, expires_at) | **Job order expiry** |
| orders | `orders_status_created_idx` | (status, created_at) | Admin monitoring |
| seller_orders | `seller_orders_seller_status_created_idx` | (seller_id, status, created_at) | Dashboard seller |
| seller_orders | `seller_orders_order_idx` | (order_id) | Detail parent |
| order_items | `order_items_seller_order_idx` | (seller_order_id) | Detail suborder |
| order_items | `order_items_product_idx` | (product_id) | Statistik produk |
| order_status_histories | `order_status_hist_order_created_idx` | (order_id, created_at) | Timeline |
| seller_order_status_histories | `sosh_seller_order_created_idx` | (seller_order_id, created_at) | Timeline |
| payments | `payments_provider_reference_unique` | (provider, provider_reference) | Webhook lookup |
| payments | `payments_status_created_idx` | (status, created_at) | Monitoring |
| payment_attempts | `payment_attempts_payment_created_idx` | (payment_id, created_at) | Retry log |
| payment_webhook_events | `webhook_provider_event_unique` | provider_event_id | Idempotency |
| payment_webhook_events | `webhook_status_received_idx` | (status, received_at) | Reprocess |
| refunds | `refunds_status_created_idx` | (status, created_at) | Admin |
| shipments | `shipments_seller_status_idx` | (seller_id, status) | Dashboard |
| shipments | `shipments_tracking_unique` | tracking_number | Tracking lookup |
| shipment_histories | `shipment_hist_shipment_occurred_idx` | (shipment_id, occurred_at) | Timeline |
| shipping_webhook_events | `shipping_webhook_provider_event_unique` | provider_event_id | Idempotensi webhook |
| shipping_webhook_events | `shipping_webhook_status_received_idx` | (status, received_at) | Reprocess |
| commissions | `commissions_seller_status_idx` | (seller_id, status, created_at) | Laporan komisi |
| wallet_transactions | `wallet_tx_seller_created_idx` | (seller_id, created_at) | Mutasi wallet |
| wallet_transactions | `wallet_tx_bucket_created_idx` | (seller_id, balance_bucket, created_at) | Rekonsiliasi |
| wallet_transactions | `wallet_tx_reference_idx` | (reference_type, reference_id) | Trace |
| seller_payouts | `payouts_seller_status_idx` | (seller_id, status, created_at) | Riwayat payout |
| seller_payouts | `payouts_status_created_idx` | (status, created_at) | Antrian admin |
| voucher_usages | `voucher_usages_user_idx` | (user_id, voucher_id) | Cek limit per user |
| reviews | `reviews_product_status_idx` | (product_id, status, created_at) | Listing ulasan |
| reviews | `reviews_seller_idx` | (seller_id, status) | Rating toko |
| notifications | `notifications_user_read_idx` | (user_id, read_at, created_at) | Inbox |
| outbox_events | `outbox_status_available_idx` | (status, available_at) | Publisher |
| audit_logs | `audit_entity_idx` | (entity_type, entity_id, created_at) | Investigasi |
| audit_logs | `audit_actor_created_idx` | (actor_id, created_at) | Jejak admin |
| idempotency_keys | `idempotency_expires_idx` | expires_at | Cleanup |

### 4.2 Prinsip urutan composite index

1. Kolom dengan **equality** lebih dulu (mis. `seller_id`, `status`).
2. Kolom **range/sort** berikutnya (mis. `created_at`).
3. Hindari index redundan (prefix index yang sudah tercakup index komposit).
4. Semua FK otomatis punya index; jangan duplikasi.
5. Jumlah index per tabel dijaga wajar (< 8) untuk menjaga biaya write.

### 4.3 Kolom yang TIDAK perlu index terpisah

- `created_at` tunggal di tabel besar **kecuali** dipakai untuk filter/retensi (mis. `outbox_events`).
- `updated_at` (jarang dipakai untuk filter).
- Boolean low-cardinality tunggal (`is_active`, `is_featured`) — gabungkan dengan kolom selektif.

### 4.4 Pencarian Produk

**Rencana awal (MVP):**
```sql
CREATE FULLTEXT INDEX products_fulltext_idx ON products (name, description);
```
Query: `MATCH(name, description) AGAINST (? IN BOOLEAN MODE)` digabung filter `status = 'active'`.

**Skala lanjut:** pindahkan pencarian ke Meilisearch/Typesense/OpenSearch bila volume & kebutuhan relevansi meningkat. Ini **tidak** mengubah skema inti.

### 4.5 Partial/Functional index

MySQL 8.0 tidak mendukung partial index seperti PostgreSQL. Untuk "hanya yang aktif", gunakan:
- Index komposit dengan `status` di depan (sudah direncanakan), atau
- Generated column + index bila perlu (mis. `is_active_variant`), **hanya jika** terbukti dibutuhkan oleh query plan.

---

## 5. CHECK Constraints (MySQL 8.0.16+)

| Tabel | Constraint | Ekspresi |
|---|---|---|
| inventories | `chk_inv_reserved_lte_stock` | `reserved_stock <= stock` |
| inventories | `chk_inv_stock_nonneg` | `stock >= 0` |
| inventories | `chk_inv_reserved_nonneg` | `reserved_stock >= 0` |
| inventories | `chk_inv_low_stock_nonneg` | `low_stock_threshold >= 0` |
| product_variants | `chk_variant_price_nonneg` | `price >= 0` |
| orders | `chk_orders_amounts_nonneg` | `subtotal >= 0 AND discount_total >= 0 AND shipping_total >= 0 AND grand_total >= 0` |
| seller_orders | `chk_so_amounts_nonneg` | semua nominal >= 0 |
| payments | `chk_payments_amount_positive` | `amount > 0` |
| commissions | `chk_comm_rate_range` | `rate_bp >= 0 AND rate_bp <= 10000` |
| commission_rules | `chk_rule_rate_range` | `rate_bp >= 0 AND rate_bp <= 10000` |
| wallet_transactions | `chk_wallet_tx_balance` | `balance_after = balance_before + amount` |
| wallet_transactions | `chk_wallet_tx_bucket` | `balance_bucket IN ('pending','available','payout_reserved','risk_hold','debt','paid')` |
| seller_payouts | `chk_payout_amount_positive` | `amount > 0` |
| reviews | `chk_review_rating` | `rating BETWEEN 1 AND 5` |
| vouchers | `chk_voucher_value` | `value > 0 AND (type <> 'percent' OR value <= 10000)` |
| vouchers | `chk_voucher_dates` | `expires_at IS NULL OR expires_at > starts_at` |
| inventory_reservations | `chk_res_quantity_positive` | `quantity > 0` |
| cart_items | `chk_cart_qty_positive` | `quantity > 0` |
| order_items | `chk_order_item_qty_positive` | `quantity > 0` |
| commission_rules | `chk_rule_scope_target` | `(scope='global' AND category_id IS NULL AND seller_id IS NULL) OR (scope='category' AND category_id IS NOT NULL AND seller_id IS NULL) OR (scope='seller' AND seller_id IS NOT NULL AND category_id IS NULL)` |
| vouchers | `chk_voucher_scope_target` | `scope='platform' OR (scope='seller' AND seller_id IS NOT NULL)` |

**Catatan:** constraint yang melibatkan banyak tabel (mis. total order = Σ suborder) **tidak dapat** dinyatakan sebagai CHECK; harus dijaga di Action + test.

---

## 6. Constraint Aplikasi (tidak bisa di DB)

| Invariant | Di mana dijaga |
|---|---|
| `orders.grand_total = subtotal - discount + shipping` | `PlaceCheckoutAction` + unit test |
| `orders.subtotal = Σ seller_orders.subtotal` | `PlaceCheckoutAction` + test |
| `seller_orders.seller_net_amount = grand_total - commission - seller_borne` | `CommissionCalculator` + test |
| Refund tidak melebihi sisa per komponen (produk/ongkir) | `CreateRefundAction` (row lock) + `RefundAllocation` + test |
| `available` tidak pernah negatif; kekurangan refund → `debt` | `WalletLedgerService` (debit overflow) + test |
| Reserve risiko dilepas tepat sekali setelah `risk_hold_release_at` | `ReleaseRiskHoldJob` + `ReleaseRiskHoldAction` (lock + idempotent) |
| Stok tidak minus saat checkout bersamaan | `SELECT ... FOR UPDATE` / conditional `UPDATE ... WHERE stock - reserved_stock >= ?` + concurrency test |
| Pembayaran tidak dobel | `payments.status` dicek dalam lock + `provider_reference` unik + test webhook ganda |
| Payout tidak dobel | lock `seller_wallets` + cek status payout + test |
| Seller tidak bisa akses order seller lain | Policy + query scope + feature test |
| Hanya satu alamat default per user/seller | Service + partial unique (MySQL: generated column `default_flag` = `IF(is_default, 1, NULL)` dengan UNIQUE(user_id, default_flag)) |
| Hanya satu gambar utama per produk | Service + generated column unique |
| Voucher usage limit | lock voucher row + cek `used_count < usage_limit` |

**Contoh generated column untuk default address (MySQL 8.0):**
```sql
ALTER TABLE user_addresses
  ADD COLUMN default_uniq TINYINT GENERATED ALWAYS AS (IF(is_default = 1, 1, NULL)) STORED,
  ADD UNIQUE KEY uq_user_default_address (user_id, default_uniq);
```

---

## 7. Migration Ordering (aman terhadap FK)

```
1.  users (modifikasi)
2.  roles, permissions, user_roles, role_permissions
3.  user_addresses, user_devices
4.  categories
5.  sellers, seller_documents, seller_addresses
6.  products, product_variants, product_images
7.  inventories, inventory_movements
8.  carts, cart_items
9.  vouchers
10. orders, seller_orders, order_items, order_addresses,
    order_status_histories, seller_order_status_histories
11. inventory_reservations   (butuh orders & order_items)
12. payments, payment_attempts, payment_webhook_events
13. refunds
14. shipments, shipment_histories
15. shipping_webhook_events (tambahan Phase 6)
16. commission_rules, commissions
17. seller_wallets, seller_bank_accounts, seller_payouts, wallet_transactions, payout_items
18. (Phase 7) kolom `refunds.rejection_reason`
15. commission_rules, commissions
16. seller_wallets, seller_payouts, wallet_transactions, payout_items
    (payouts dulu, lalu wallet_transactions, lalu payout_items; FK payout_id nullable)
17. voucher_usages
18. reviews
19. notifications
20. outbox_events, audit_logs, platform_settings, idempotency_keys
```

---

## 8. Estimasi Volume & Strategi Retensi (asumsi awal)

| Tabel | Estimasi 1 tahun | Retensi | Strategi |
|---|---|---|---|
| orders | 500k | permanen | arsip setelah 2 tahun |
| order_items | 2M | permanen | arsip bersama order |
| payment_webhook_events | 1M | 12 bulan | partisi/arsip ke cold storage |
| inventory_movements | 5M | 24 bulan | arsip |
| wallet_transactions | 3M | permanen | wajib untuk audit |
| audit_logs | 5M | 24 bulan | arsip |
| notifications | 10M | 6 bulan | hapus terjadwal |
| outbox_events | 2M | 3 bulan setelah published | hapus terjadwal |

**Catatan partisi:** pertimbangkan `RANGE` partition by month pada `audit_logs`, `payment_webhook_events`, `notifications` **setelah** volume terbukti; jangan di awal.
