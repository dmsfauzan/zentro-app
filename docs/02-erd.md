# 02 — Entity Relationship Diagram (Final)

**Product:** ZENTRO — *Everything in One Place*
**Database:** MySQL 8.0
**Convention:** InnoDB, `utf8mb4_unicode_ci`, PK `BIGINT UNSIGNED AUTO_INCREMENT`, FK `RESTRICT` untuk data transaksi/finansial, soft delete hanya pada entitas master yang punya riwayat.

---

## 1. Legenda

| Simbol | Arti |
|---|---|
| `PK` | Primary key |
| `FK` | Foreign key |
| `UQ` | Unique |
| `IDX` | Index non-unique |
| `NN` | NOT NULL |
| `→` | Relasi many-to-one |
| `↔` | Many-to-many via pivot |

Aturan umum:
- Semua tabel punya `id`, `created_at`, `updated_at` (kecuali disebut lain).
- Tabel ledger/append-only (`wallet_transactions`, `inventory_movements`, `order_status_histories`) tidak memiliki `updated_at` dan **tidak boleh** di-update/delete.
- Semua FK transaksi & finansial: `ON DELETE RESTRICT`, `ON UPDATE RESTRICT`.

---

## 2. Domain A — Identity & Access

```mermaid
erDiagram
    users ||--o{ user_roles : has
    roles ||--o{ user_roles : assigned
    roles ||--o{ role_permissions : grants
    permissions ||--o{ role_permissions : in
    users ||--o{ user_addresses : owns
    users ||--o{ user_devices : registers
    users ||--o{ audit_logs : performs

    users {
        bigint id PK
        string firebase_uid UQ "NN"
        string name NN
        string email UQ "NN"
        timestamp email_verified_at
        string phone
        string password "nullable, unused for Firebase login"
        enum status "active|inactive|suspended|banned"
        timestamp last_login_at
        timestamps
    }
    roles {
        bigint id PK
        string name UQ
        string guard_name
        string description
    }
    permissions {
        bigint id PK
        string name UQ
        string guard_name
        string description
    }
    user_roles {
        bigint user_id FK
        bigint role_id FK
    }
    role_permissions {
        bigint role_id FK
        bigint permission_id FK
    }    user_addresses {
        bigint id PK
        bigint user_id FK
        string label
        string recipient_name
        string phone
        text address_line
        string province
        string city
        string district
        string village
        string postal_code
        decimal latitude
        decimal longitude
        boolean is_default
    }
    user_devices {
        bigint id PK
        bigint user_id FK
        string fcm_token UQ
        string platform
        string device_name
        timestamp last_used_at
    }
```

**Catatan:** `users.password` boleh ada (dari skeleton Laravel) tetapi tidak dipakai untuk login Firebase; set `nullable` dan jangan pernah mengisinya untuk auth Firebase.

**Catatan implementasi (Phase 1):** `user_roles` dan `role_permissions` dibuat oleh `spatie/laravel-permission`. Karena itu `user_roles` bersifat **polymorphic** dengan kolom `role_id`, `model_type`, `model_id` (bukan `user_id`, `role_id`). Lihat [Data Dictionary §A4](03-data-dictionary.md).

---

## 3. Domain B — Seller

```mermaid
erDiagram
    users ||--o| sellers : becomes
    sellers ||--o{ seller_documents : uploads
    sellers ||--o{ seller_addresses : has

    sellers {
        bigint id PK
        bigint user_id FK UQ "NN"
        string store_name NN
        string slug UQ "NN"
        text description
        string logo_path
        string banner_path
        enum status "pending|under_review|active|rejected|suspended"
        timestamp submitted_at
        timestamp approved_at
        bigint approved_by FK
        timestamp rejected_at
        bigint rejected_by FK
        text rejection_reason
        timestamp suspended_at
        text suspension_reason
        decimal rating_avg "default 0"
        int rating_count "default 0"
        timestamps
    }
    seller_documents {
        bigint id PK
        bigint seller_id FK
        enum type "ktp|npwp|siup|nib|bank_statement|other"
        string file_path
        string file_hash
        enum status "pending|approved|rejected"
        bigint reviewed_by FK
        text review_note
        timestamp reviewed_at
    }
    seller_addresses {
        bigint id PK
        bigint seller_id FK
        enum type "origin|return|billing"
        string label
        string contact_name
        string phone
        text address_line
        string province
        string city
        string district
        string village
        string postal_code
        decimal latitude
        decimal longitude
        boolean is_default
    }
```

`sellers.user_id` UNIQUE → satu user maksimal satu toko. `approved_by`/`rejected_by` → `users.id`.

---

## 4. Domain C — Catalog

```mermaid
erDiagram
    categories ||--o{ categories : parent_of
    categories ||--o{ products : classifies
    sellers ||--o{ products : sells
    products ||--o{ product_variants : has
    products ||--o{ product_images : has
    product_variants ||--o| inventories : stock
    product_variants ||--o{ cart_items : "in cart"
    product_variants ||--o{ order_items : "ordered as"

    categories {
        bigint id PK
        bigint parent_id FK "nullable"
        string name
        string slug UQ
        text description
        string icon_path
        int sort_order
        boolean is_active
        timestamps
    }
    products {
        bigint id PK
        bigint seller_id FK
        bigint category_id FK
        string name
        string slug UQ "NN"
        text description
        decimal weight_gram
        enum status "draft|active|inactive|archived|rejected"
        boolean is_featured
        decimal rating_avg
        int rating_count
        int sold_count
        bigint min_price
        bigint max_price
        timestamp published_at
        timestamps
        softDeletes
    }
    product_variants {
        bigint id PK
        bigint product_id FK
        string sku UQ "NN"
        string variant_name
        bigint price NN
        decimal weight_gram
        enum status "active|inactive"
        timestamps
    }
    product_images {
        bigint id PK
        bigint product_id FK
        string path
        string alt_text
        int sort_order
        boolean is_primary
        timestamps
    }
```

`min_price`/`max_price` didenormalisasi untuk sorting/filter listing, di-update saat varian berubah. `products.slug` unik global.

---

## 5. Domain D — Inventory

```mermaid
erDiagram
    product_variants ||--|| inventories : tracks
    inventories ||--o{ inventory_movements : records
    inventories ||--o{ inventory_reservations : reserves
    orders ||--o{ inventory_reservations : "reserved by"

    inventories {
        bigint id PK
        bigint variant_id FK UQ
        int stock NN "default 0"
        int reserved_stock NN "default 0"
        int low_stock_threshold
        timestamps
    }
    inventory_movements {
        bigint id PK
        bigint inventory_id FK
        bigint variant_id FK
        enum type "in|out|adjustment|reservation|release|commit"
        int quantity "signed"
        int stock_before
        int stock_after
        int reserved_before
        int reserved_after
        string reference_type
        bigint reference_id
        bigint actor_id FK
        string note
        timestamp created_at
    }
    inventory_reservations {
        bigint id PK
        bigint inventory_id FK
        bigint variant_id FK
        bigint order_id FK
        bigint order_item_id FK
        int quantity
        enum status "active|committed|released|expired"
        timestamp expires_at
        timestamp committed_at
        timestamp released_at
        timestamps
    }
```

**Available stock = `stock - reserved_stock`.** Invariant dicek di aplikasi + conditional UPDATE atomik.

---

## 6. Domain E — Cart

```mermaid
erDiagram
    users ||--o| carts : owns
    carts ||--o{ cart_items : contains
    product_variants ||--o{ cart_items : referenced

    carts {
        bigint id PK
        bigint user_id FK UQ
        timestamps
    }
    cart_items {
        bigint id PK
        bigint cart_id FK
        bigint variant_id FK
        int quantity
        timestamps
    }
```

`UNIQUE(cart_id, variant_id)`. Cart menyimpan `variant_id` saja — **harga tidak disimpan**; selalu dibaca ulang saat preview/checkout.

---

## 7. Domain F — Orders

```mermaid
erDiagram
    users ||--o{ orders : places
    orders ||--o{ seller_orders : splits_into
    seller_orders ||--o{ order_items : contains
    orders ||--o{ order_items : aggregates
    orders ||--o{ order_addresses : snapshots
    orders ||--|| payments : "paid by"
    orders ||--o{ order_status_histories : logs
    seller_orders ||--o{ seller_order_status_histories : logs
    seller_orders ||--o| shipments : shipped_by

    orders {
        bigint id PK
        string order_number UQ
        bigint buyer_id FK
        bigint voucher_id FK "nullable"
        enum status "pending_payment|paid|processing|partially_shipped|shipped|completed|cancelled|refunded|expired"
        bigint subtotal
        bigint discount_total
        bigint shipping_total
        bigint grand_total
        string currency "IDR"
        timestamp paid_at
        timestamp expires_at
        timestamp completed_at
        timestamp cancelled_at
        boolean is_reviewed
        timestamps
    }
    seller_orders {
        bigint id PK
        bigint order_id FK
        bigint seller_id FK
        string seller_order_number UQ
        enum status "pending|confirmed|processing|shipped|delivered|completed|cancelled|refunded"
        bigint subtotal
        bigint discount_total
        bigint shipping_cost
        bigint grand_total
        bigint commission_base_amount
        bigint commission_rate_bp
        bigint commission_amount
        bigint seller_net_amount
        bigint voucher_discount_borne_by_seller
        bigint platform_funded_discount
        timestamp confirmed_at
        timestamp shipped_at
        timestamp delivered_at
        timestamp completed_at
        timestamp cancelled_at
        timestamps
    }
    order_items {
        bigint id PK
        bigint order_id FK
        bigint seller_order_id FK
        bigint seller_id FK
        bigint product_id FK "nullable, snapshot"
        bigint variant_id FK "nullable, snapshot"
        string product_name_snapshot
        string sku_snapshot
        string variant_name_snapshot
        bigint price_snapshot
        int quantity
        bigint discount_amount
        bigint line_total
        decimal weight_gram_snapshot
        timestamps
    }
    order_addresses {
        bigint id PK
        bigint order_id FK
        bigint seller_order_id FK "nullable"
        enum type "shipping|billing"
        string recipient_name
        string phone
        text address_line
        string province
        string city
        string district
        string village
        string postal_code
        decimal latitude
        decimal longitude
        timestamps
    }
    order_status_histories {
        bigint id PK
        bigint order_id FK
        string from_status
        string to_status
        bigint changed_by FK "nullable = system"
        string reason
        json metadata
        timestamp created_at
    }
    seller_order_status_histories {
        bigint id PK
        bigint seller_order_id FK
        string from_status
        string to_status
        bigint changed_by FK
        string reason
        json metadata
        timestamp created_at
    }
```

**Invariant:**
- `orders.subtotal = Σ(seller_orders.subtotal)`
- `orders.grand_total = orders.subtotal - orders.discount_total + orders.shipping_total`
- `seller_orders.grand_total = subtotal - discount + shipping_cost`
- `seller_orders.seller_net_amount = grand_total - commission_amount - seller_borne_fees`
- Snapshot **wajib**; FK produk/varian `nullable` dan `ON DELETE SET NULL` hanya agar produk bisa di-soft-delete tanpa memutus riwayat. Nilai transaksi tetap dari snapshot.

---

## 8. Domain G — Payment

```mermaid
erDiagram
    orders ||--|| payments : "one payment per checkout"
    payments ||--o{ payment_attempts : retries
    payments ||--o{ refunds : refunded
    payment_webhook_events }o--|| payments : "resolves to"

    payments {
        bigint id PK
        bigint order_id FK UQ
        string provider
        string provider_reference UQ "nullable"
        string payment_method
        enum status "pending|requires_action|paid|failed|expired|refunded|partially_refunded|chargeback"
        bigint amount
        string currency
        timestamp paid_at
        timestamp expires_at
        json provider_payload
        timestamps
    }
    payment_attempts {
        bigint id PK
        bigint payment_id FK
        string provider
        string provider_reference
        enum status "initiated|pending|succeeded|failed|expired"
        bigint amount
        json request_payload
        json response_payload
        string failure_code
        text failure_message
        timestamp created_at
    }
    payment_webhook_events {
        bigint id PK
        string provider
        string provider_event_id UQ
        string event_type
        string provider_reference
        enum status "received|processed|ignored|failed"
        json payload
        string signature
        text processing_error
        timestamp received_at
        timestamp processed_at
    }
    refunds {
        bigint id PK
        bigint payment_id FK
        bigint order_id FK
        bigint seller_order_id FK "nullable"
        string refund_number UQ
        bigint amount
        enum status "requested|approved|processing|succeeded|failed|rejected"
        text reason
        string provider_reference
        bigint requested_by FK
        bigint approved_by FK
        timestamp processed_at
        timestamps
    }
```

`payments.provider_reference` UNIQUE bila provider menjamin keunikan. `UNIQUE(provider, provider_reference)` dipakai saat referensi tersedia (nullable aman di MySQL karena NULL tidak bentrok).

---

## 9. Domain H — Shipping

```mermaid
erDiagram
    seller_orders ||--o| shipments : fulfilled_by
    shipments ||--o{ shipment_histories : logs

    shipments {
        bigint id PK
        bigint seller_order_id FK
        bigint seller_id FK
        string provider
        string service_code
        string tracking_number UQ "nullable"
        string provider_shipment_id
        enum status "pending|ready|dispatched|in_transit|delivered|failed|returned"
        bigint shipping_cost
        decimal weight_gram
        string origin_city
        string destination_city
        string label_path
        timestamp dispatched_at
        timestamp delivered_at
        timestamps
    }
    shipment_histories {
        bigint id PK
        bigint shipment_id FK
        string status
        string description
        string location
        timestamp occurred_at
        json metadata
        timestamp created_at
    }
```

Desain mengizinkan **partial shipment** di masa depan dengan menambah `shipment_items` (order_item_id, quantity) tanpa mengubah `shipments`. Untuk MVP, satu `seller_order → satu shipment`; kolom `shipments` tidak diberi UNIQUE sehingga partial shipment dapat ditambahkan nanti.

---

## 10. Domain I — Commission & Wallet (Ledger)

```mermaid
erDiagram
    categories ||--o{ commission_rules : "may scope"
    sellers ||--o{ commission_rules : "may scope"
    sellers ||--o{ commissions : earns
    seller_orders ||--o| commissions : generates
    sellers ||--|| seller_wallets : has
    seller_wallets ||--o{ wallet_transactions : ledger
    sellers ||--o{ seller_payouts : requests
    seller_payouts ||--o{ payout_items : itemizes
    wallet_transactions ||--o{ payout_items : "funded by"

    commission_rules {
        bigint id PK
        string name
        enum scope "global|category|seller"
        bigint category_id FK "nullable"
        bigint seller_id FK "nullable"
        bigint rate_bp NN "basis points, 1000 = 10%"
        boolean includes_shipping "default false"
        date effective_from
        date effective_to "nullable"
        int priority "default 0"
        boolean is_active
        timestamps
    }
    commissions {
        bigint id PK
        bigint seller_order_id FK UQ
        bigint seller_id FK
        bigint commission_rule_id FK "nullable"
        bigint base_amount NN
        bigint rate_bp NN
        bigint commission_amount NN
        bigint rounding_adjustment "default 0"
        enum status "pending|earned|reversed"
        timestamp earned_at
        timestamps
    }
    seller_wallets {
        bigint id PK
        bigint seller_id FK UQ
        bigint pending_balance "default 0"
        bigint available_balance "default 0"
        bigint paid_balance "default 0"
        datetime balance_updated_at
        timestamps
    }
    wallet_transactions {
        bigint id PK
        bigint seller_id FK
        bigint wallet_id FK
        bigint seller_order_id FK "nullable"
        bigint payout_id FK "nullable"
        bigint reversal_of_id FK "nullable"
        enum type "sale_pending|settlement_release|commission|payout|refund_debit|adjustment|reversal"
        enum balance_bucket "pending|available|paid"
        bigint amount "signed: +credit / -debit"
        bigint balance_before
        bigint balance_after
        string reference_type
        bigint reference_id
        string description
        bigint created_by FK "nullable = system"
        timestamp created_at
    }
    seller_payouts {
        bigint id PK
        string payout_number UQ
        bigint seller_id FK
        bigint amount NN
        enum status "requested|approved|processing|paid|rejected|failed|cancelled"
        string bank_name_snapshot
        string bank_account_number_snapshot "encrypted"
        string bank_account_name_snapshot
        string provider_reference
        bigint requested_by FK
        bigint processed_by FK "nullable"
        timestamp requested_at
        timestamp processed_at
        timestamp paid_at
        text rejection_reason
        timestamps
    }
    payout_items {
        bigint id PK
        bigint payout_id FK
        bigint wallet_transaction_id FK "nullable"
        bigint seller_order_id FK "nullable"
        bigint amount
        timestamps
    }
```

**Ledger rules:**
- `wallet_transactions` append-only. Koreksi = baris baru dengan `reversal_of_id`.
- `seller_wallets` hanyalah **cache saldo** yang di-update **dalam transaksi yang sama** dengan penulisan ledger; harus selalu konsisten terhadap SUM ledger per bucket.
- Tiga bucket: `pending`, `available`, `paid`.

---

## 11. Domain J — Promotion, Review, Notification, Platform

```mermaid
erDiagram
    vouchers ||--o{ voucher_usages : used
    users ||--o{ voucher_usages : redeems
    orders ||--o| voucher_usages : applies
    products ||--o{ reviews : reviewed
    orders ||--o{ reviews : from
    users ||--o{ reviews : writes
    users ||--o{ notifications : receives

    vouchers {
        bigint id PK
        string code UQ
        string name
        enum type "fixed|percent"
        bigint value "fixed: rupiah; percent: basis points"
        bigint min_purchase
        bigint max_discount "nullable"
        enum scope "platform|seller"
        bigint seller_id FK "nullable"
        enum funded_by "platform|seller"
        int usage_limit "nullable total"
        int usage_limit_per_user "nullable"
        int used_count "default 0"
        timestamp starts_at
        timestamp expires_at
        boolean is_active
        timestamps
    }
    voucher_usages {
        bigint id PK
        bigint voucher_id FK
        bigint user_id FK
        bigint order_id FK
        bigint seller_order_id FK "nullable"
        bigint discount_amount
        timestamp used_at
    }
    reviews {
        bigint id PK
        bigint order_id FK
        bigint order_item_id FK UQ
        bigint product_id FK
        bigint seller_id FK
        bigint user_id FK
        int rating "1..5"
        text comment
        json images
        enum status "pending|published|hidden"
        timestamp published_at
        timestamps
    }
    notifications {
        bigint id PK
        bigint user_id FK
        string type
        string title
        text body
        json data
        timestamp read_at
        timestamps
    }
    outbox_events {
        bigint id PK
        string aggregate_type
        bigint aggregate_id
        string event_type
        json payload
        enum status "pending|processing|published|failed"
        int attempts
        timestamp available_at
        timestamp published_at
        text last_error
        timestamp created_at
    }
    audit_logs {
        bigint id PK
        bigint actor_id FK "nullable = system"
        string action
        string entity_type
        bigint entity_id
        json before
        json after
        string ip_address
        string user_agent
        string request_id
        timestamp created_at
    }
    platform_settings {
        bigint id PK
        string key UQ
        json value
        string description
        timestamps
    }
    idempotency_keys {
        bigint id PK
        string key UQ
        bigint user_id FK "nullable"
        string scope
        string request_hash
        json response_snapshot
        int response_status
        timestamp expires_at
        timestamp created_at
    }
```

---

## 12. Global Relationship Overview (ringkas)

```
users 1─1 sellers 1─* products 1─* product_variants 1─1 inventories
users 1─* orders 1─* seller_orders 1─* order_items
orders 1─1 payments 1─* payment_attempts
orders 1─* refunds
seller_orders 1─1 shipments 1─* shipment_histories
seller_orders 1─1 commissions
sellers 1─1 seller_wallets 1─* wallet_transactions
sellers 1─* seller_payouts 1─* payout_items
sellers 1─* (products, orders via seller_orders)
roles *↔* permissions (role_permissions)
users *↔* roles (user_roles)
vouchers 1─* voucher_usages
orders 1─* reviews (per order_item)
```

---

## 13. Ownership Matrix (siapa boleh melihat/mengubah)

| Entitas | Customer | Seller | Admin |
|---|---|---|---|
| users (self) | read/update self | read/update self | full |
| user_addresses | CRUD own | — | read |
| sellers | read public profile | read/update own (terbatas) | full |
| seller_documents | — | upload own | read/review |
| products | read active | CRUD own | moderate |
| inventories | read | update own | read/update |
| carts | CRUD own | — | — |
| orders | read own | read where seller_id = own | full |
| payments | read own | — | full |
| shipments | read own order | CRUD own | full |
| wallets | — | read own | full |
| payouts | — | create/read own | full |
| vouchers | read/apply | read own scope | full |
| reviews | create own / read | reply (nanti) | moderate |
| audit_logs | — | — | read |

---

## 14. Referential Integrity Summary

| Relasi | On Delete | Alasan |
|---|---|---|
| `users → user_addresses` | CASCADE | Data non-transaksional milik user |
| `users → sellers` | RESTRICT | Seller punya riwayat |
| `sellers → products` | RESTRICT | Produk punya riwayat order |
| `products → product_variants` | CASCADE (hanya jika belum pernah di-order) | Praktik: soft delete/archived |
| `product_variants → inventories` | RESTRICT | Stok tidak boleh hilang |
| `orders → seller_orders` | RESTRICT | Transaksi |
| `orders → order_items` | RESTRICT | Transaksi |
| `payments → payment_attempts` | RESTRICT | Audit |
| `seller_orders → commissions` | RESTRICT | Finansial |
| `seller_wallets → wallet_transactions` | RESTRICT | Ledger immutable |
| `seller_payouts → payout_items` | RESTRICT | Finansial |
| `orders → order_addresses` | RESTRICT | Snapshot |

**Untuk semua tabel transaksi/finansial: tidak ada CASCADE DELETE.** Penghapusan hanya via soft delete/status, atau `archived`.
