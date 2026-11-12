# 01 — Software Architecture Document (SAD)

**Product:** ZENTRO — *Everything in One Place*
**Type:** Multi-vendor Marketplace Platform
**Document version:** 1.0
**Companion docs:** ERD (02), Data Dictionary (03), Constraints & Index (04), State Machines (05), API Spec (06), Folder Structure (07), Roadmap (08), Risk Register (09), Assumptions & Decisions (10)

---

## 1. Purpose & Scope

Dokumen ini mendefinisikan arsitektur ZENTRO: marketplace multi-vendor di mana:

- Banyak seller membuka toko dan mengelola produk, stok, pesanan, serta pengiriman masing-masing.
- Satu checkout dapat mencakup produk dari banyak seller dan menghasilkan **satu parent order** serta **beberapa seller_order**.
- Satu checkout dibayar dengan **satu transaksi payment gateway**.
- Platform mengambil **komisi persentase** dan mengelola **wallet, settlement, dan payout** seller.
- Admin mengontrol penuh operasional marketplace.

**In scope dokumen ini:** arsitektur logis, batas modul, alur data, keamanan, skalabilitas, dan keputusan teknis.

**Out of scope:** kode implementasi (dibahas per fase), copywriting/UX detail, kontrak hukum & perpajakan.

---

## 2. Architectural Principles

| Prinsip | Penerapan konkret |
|---|---|
| Modular Monolith | Satu codebase Laravel, batas modul jelas, komunikasi antar-modul lewat Service/Contract internal, bukan lintas tabel langsung |
| Pragmatic DDD | Bounded context: Identity, Seller, Catalog, Inventory, Cart, Order, Payment, Shipping, Finance, Promotion, Review, Notification, Reporting |
| Clean Architecture | Controller tipis → FormRequest (validasi) → Action/Service (use case) → Domain (rule/value object) → Repository/Eloquent (persistence) |
| SOLID | Interface untuk payment/shipping/notification; dependency injection; no god class |
| RESTful API | Resource-oriented, verb via HTTP, response envelope konsisten |
| Secure by default | Authz server-side, input validation, signature verification, idempotency, audit log |
| Integer money | Semua uang sebagai integer rupiah (BIGINT), tanpa float |
| Immutable ledger | Wallet transaction tidak pernah di-update/di-delete, koreksi lewat entri pembalik |
| Fail-safe finance | Dana seller berpindah status hanya setelah kondisi settlement terpenuhi |
| Test business invariants | Concurrency test untuk overselling, double webhook, idempotency, double payout |
| No premature distribution | Tanpa microservices/K8s sampai ada kebutuhan operasional nyata |

---

## 3. System Context

```
                         ┌───────────────────────────────┐
                         │            ZENTRO             │
                         │                               │
  ┌──────────┐           │  ┌─────────────┐              │
  │ Customer │──HTTPS────┼─▶│  Next.js 16 │              │
  └──────────┘           │  │  (3 areas)  │              │
  ┌──────────┐           │  └──────┬──────┘              │
  │  Seller  │──HTTPS────┼─────────┤                     │
  └──────────┘           │         │ REST /api/v1        │
  ┌──────────┐           │         │ Bearer ID Token     │
  │  Admin   │──HTTPS────┼─────────┤                     │
  └──────────┘           │         ▼                     │
                         │  ┌─────────────┐   ┌────────┐ │
                         │  │   Nginx     │──▶│Laravel │ │
                         │  └─────────────┘   │  API   │ │
                         │                    └───┬────┘ │
                         └────────────────────────┼──────┘
                                                  │
        ┌──────────────┬──────────────┬───────────┼───────────┬───────────────┐
        ▼              ▼              ▼           ▼           ▼               ▼
   ┌─────────┐   ┌──────────┐   ┌─────────┐ ┌─────────┐ ┌──────────┐  ┌──────────────┐
   │MySQL 8.0│   │  Redis   │   │  S3     │ │Firebase │ │ Payment  │  │  Shipping    │
   │ (SoT)   │   │cache/q/L │   │ storage │ │  Auth   │ │ Gateway  │  │  Provider    │
   └─────────┘   └──────────┘   └─────────┘ └─────────┘ └──────────┘  └──────────────┘
```

**External systems & tanggung jawab:**

| Sistem | Peran | Catatan keamanan |
|---|---|---|
| Firebase Auth | Identity provider (login, Google, verify email, reset password) | ID token diverifikasi server-side penuh |
| Firebase FCM | Push notification | Kirim via queue; simpan device token di `user_devices` |
| Payment Gateway | Authorize/capture dana, sumber webhook | Signature verification + idempotency + amount check |
| Shipping Provider | Quote ongkir, buat resi, tracking | Adapter per provider |
| S3-compatible | Simpan gambar produk & dokumen seller | Presigned URL, validasi MIME/size |

---

## 4. Bounded Contexts (Modul Monolit)

Setiap modul punya: Controller, FormRequest, Route file, Service/Action, Model (milik modul), Policy, Event, Job, Test. Modul **tidak** mengakses tabel milik modul lain secara langsung; komunikasi lewat Service publik / Contract.

| # | Modul | Tanggung jawab | Entitas utama |
|---|---|---|---|
| M1 | Identity & Access | User, auth Firebase, RBAC, alamat user | users, roles, permissions, user_roles, role_permissions, user_addresses |
| M2 | Seller | Aplikasi seller, verifikasi, profil toko | sellers, seller_documents, seller_addresses |
| M3 | Catalog | Kategori, produk, varian, gambar | categories, products, product_variants, product_images |
| M4 | Inventory | Stok, pergerakan, reservasi | inventories, inventory_movements, inventory_reservations |
| M5 | Cart | Keranjang lintas seller | carts, cart_items |
| M6 | Order | Parent order, seller order, snapshot, status | orders, seller_orders, order_items, order_addresses, order_status_histories, seller_order_status_histories |
| M7 | Payment | Payment utama, attempt, webhook, refund | payments, payment_attempts, payment_webhook_events, refunds |
| M8 | Shipping | Quote, shipment per seller, tracking | shipments, shipment_histories |
| M9 | Finance | Komisi, wallet, ledger, payout | commission_rules, commissions, seller_wallets, wallet_transactions, seller_payouts, payout_items |
| M10 | Promotion | Voucher | vouchers, voucher_usages |
| M11 | Review | Ulasan produk/order | reviews |
| M12 | Notification | Notifikasi in-app, FCM, device | notifications, user_devices |
| M13 | Platform | Audit log, outbox, settings, laporan | audit_logs, outbox_events, platform_settings |

---

## 5. Layering di Dalam Modul (Clean Architecture)

```
HTTP Layer        : Controller, FormRequest, Resource (API Resource), Middleware
Application Layer : Action / UseCase (single responsibility), DTO, Service orchestrator
Domain Layer      : Entity rules, Value Objects (Money, Quantity, CommissionRate),
                    Enums (status), Domain Events, Policy (business rules)
Infrastructure    : Eloquent Model, Repository, External Gateway adapters, Jobs
```

**Aturan:**

- Controller **tidak** menyimpan business logic. Maksimal: validasi (FormRequest), panggil Action, kembalikan Resource.
- Action menangani satu use case (mis. `PlaceCheckoutAction`, `ProcessPaymentWebhookAction`, `ApproveSellerAction`).
- Domain Event dipakai untuk efek samping non-kritis (notifikasi). Efek finansial kritis **selalu** berada dalam transaksi database yang sama dengan perubahan statusnya, atau melalui outbox.
- Repository optional; gunakan bila query kompleks & perlu menyembunyikan persistence.

---

## 6. Cross-Cutting Concerns

### 6.1 Response Envelope

Semua endpoint `/api/v1` mengembalikan bentuk konsisten:

```json
// Success
{ "success": true, "message": "Data retrieved successfully", "data": {}, "meta": {} }

// Error
{ "success": false, "message": "Validation failed", "errors": { "field": ["..."] } }
```

Diimplementasikan sebagai `ApiResponse` helper + `Handler` exception renderer.

### 6.2 Authentication Flow (Firebase → Laravel)

```
1. Next.js login via Firebase JS SDK (email/password atau Google)
2. Firebase mengembalikan ID Token (JWT)
3. Next.js kirim: Authorization: Bearer <ID Token>
4. VerifyFirebaseToken middleware:
   a. Verifikasi signature, iss, aud, exp (via Firebase Admin SDK)
   b. Cek token belum revoked & akun tidak disabled
   c. Ambil firebase_uid (sub) dari token terverifikasi
   d. Cari user by firebase_uid; sinkronkan bila perlu
   e. Set user ke request context
5. Authorization middleware / Policy / Gate dijalankan
```

**Jaminan:**
- UID **tidak pernah** diambil dari body/query.
- Role tidak pernah dipercaya dari frontend.
- ID Token ≠ Laravel Sanctum token. Sanctum hanya dipakai (opsional) untuk token sesi internal bila diperlukan.
- Sertakan `token_version`/custom claim bila perlu force re-auth.

### 6.3 Authorization Model

- **RBAC:** `roles`, `permissions`, `user_roles`, `role_permissions` (Spatie `laravel-permission` 8.5.0).
- **Policy** untuk kepemilikan resource (mis. `ProductPolicy::update` memastikan `product.seller_id == user's seller id`).
- **Gate** untuk aksi lintas-resource (mis. `processPayout`).
- Permission tidak pernah dibaca dari request. Diambil dari DB per request (dengan cache Redis per-user, di-invalidate saat role berubah).

### 6.4 Idempotency

- Endpoint mutasi kritis (`POST /checkout`, `POST /seller/payouts`, webhook) menerima/menghasilkan **idempotency key**.
- Tabel `idempotency_keys` (key, user_id, scope, request_hash, response_snapshot, created_at, expires_at) mencegah duplikasi.
- Webhook memakai `payment_webhook_events.provider_event_id` (UNIQUE) sebagai kunci idempotency + status `processed`.

### 6.5 Money & Arithmetic

- Semua nilai uang: `BIGINT` integer rupiah (tanpa sen) — sesuai praktik umum IDR.
- Value Object `Money` (immutable) membungkus integer + operasi aman.
- Komisi: `commission_amount = intdiv(base * rate_bp, 10000)` memakai basis point (rate_bp = persen × 100) untuk presisi; sisanya dibulatkan ke bawah dan dicatat sebagai `rounding_adjustment`.
- Tidak ada `float` di jalur uang.

### 6.6 Audit & Observability

- `audit_logs`: actor_id, action, entity_type, entity_id, before, after, ip, user_agent, created_at.
- Structured logging (JSON), correlation id per request (`X-Request-Id`).
- Metrics: latency endpoint, queue depth, webhook success rate, payment success rate.

### 6.7 Caching Strategy

| Data | Cache | TTL | Invalidate saat |
|---|---|---|---|
| Kategori | Redis | 1 jam | CRUD kategori |
| Produk populer/listing | Redis | 5 menit | Update produk/stok |
| Permission user | Redis | 10 menit | Role/permission berubah |
| Commission rule aktif | Redis | 5 menit | Rule diubah |
| Settings platform | Redis | 1 jam | Settings diubah |

**Prinsip:** DB adalah source of truth. Cache tidak pernah menyimpan status transaksi atau saldo sebagai otoritas.

---

## 7. Alur Bisnis Kritis (High-Level)

### 7.1 Checkout (satu order, banyak seller)

```
POST /api/v1/checkout   (idempotency-key required)
  │
  ├─ 1. Validasi request (items: variant_id, qty, alamat, voucher opsional)
  ├─ 2. Baca ulang semua variant + harga + seller + status dari DB (abaikan harga frontend)
  ├─ 3. Validasi: produk aktif, seller aktif, varian aktif
  ├─ 4. Hitung subtotal per seller; validasi & hitung diskon voucher
  ├─ 5. Hitung ongkir per seller (via ShippingQuoteService; cache/token)
  ├─ 6. Hitung grand_total & rincian
  ├─ 7. BEGIN TRANSACTION
  │     ├─ Lock & reservasi inventory (SELECT ... FOR UPDATE / conditional UPDATE)
  │     │   reserved_stock += qty, tolak bila stock - reserved_stock < qty
  │     ├─ Buat orders (order_number unik, snapshot buyer)
  │     ├─ Buat seller_orders per seller (snapshot komisi, ongkir)
  │     ├─ Buat order_items (snapshot name, sku, variant, price, qty, discount)
  │     ├─ Buat order_addresses (snapshot alamat pengiriman)
  │     ├─ Buat payments (status pending, amount = grand_total)
  │     ├─ Tulis outbox_events (OrderPlaced) & idempotency_keys
  │     └─ Commit
  ├─ 8. Setelah commit: buat transaksi ke payment gateway (di luar lock; retry-safe)
  │     └─ Simpan provider_reference pada payment + payment_attempts
  └─ 9. Return order + payment instruction (snap redirect/VA/QRIS)
```

**Aturan besi:**
- Tidak ada panggilan jaringan eksternal **di dalam** transaksi yang memegang lock inventory.
- Harga, total, komisi, seller_id **tidak pernah** dipercaya dari frontend.
- Reservasi punya `expires_at`; job `ReleaseExpiredReservations` melepas yang kedaluwarsa.

### 7.2 Payment Webhook (idempotent)

```
POST /api/v1/webhooks/payments/{provider}
  ├─ 1. Verifikasi signature (provider secret)
  ├─ 2. Simpan mentah ke payment_webhook_events (UNIQUE provider_event_id) — durable, sebelum respons
  ├─ 3. Jika event sudah pernah diproses → return 200 (idempotent), stop
  ├─ 4. Validasi provider_reference ada & status valid
  ├─ 5. BEGIN TRANSACTION
  │     ├─ Lock payment row (FOR UPDATE)
  │     ├─ Cek payment belum paid (cegah double-pay)
  │     ├─ Validasi amount == payment.amount
  │     ├─ Update payment.status = paid, paid_at
  │     ├─ Konfirmasi inventory reservation → kurangi stock fisik, reserved_stock -= qty
  │     ├─ Buat commissions (snapshot rate & base)
  │     ├─ Kredit seller_wallets.pending_balance + wallet_transactions
  │     ├─ Update order_status (paid)
  │     ├─ Tulis outbox_events (PaymentSucceeded)
  │     └─ Commit
  └─ 6. Return 200 cepat; pekerjaan berat (notifikasi) via queue
```

### 7.3 Sendiri: Settlement → Payout

```
Order Selesai (confirmed received / auto-complete setelah X hari)
  → SettlementEvaluator: cek syarat (mis. +7 hari setelah selesai, tanpa refund/dispute)
  → pendi → available balance, tulis wallet_transaction (type: settlement_release)
  → Seller ajukan payout → seller_payouts (status: requested)
  → Admin/sistem proses → status: processing → paid
  → wallet_transactions (type: payout, debit available_balance) + payout_items
```

---

## 8. Non-Functional Requirements

| Aspek | Target awal | Strategi |
|---|---|---|
| Availability | 99.5% | Stateless API, health check, graceful restart |
| Latency API | p95 < 400 ms (non-report) | Index, cache, eager loading, pagination |
| Throughput | 100 RPS burst | Horizontal scale app, Redis queue |
| Konsistensi finansial | Strong | DB transaction + row lock + immutability |
| Observability | Logs + metrics + alert | Structured logging, queue monitoring |
| Backup | Harian + PITR | Snapshot MySQL + binlog, uji restore berkala |
| Recovery | RTO 1 jam, RPO 15 menit | Backup terjadwal + runbook |
| Security | OWASP-aligned | Lihat §Security & Risk Register |

---

## 9. Scalability Path (tanpa rombak arsitektur)

1. **Awal:** 1 app server, 1 MySQL, 1 Redis, 1 queue worker, Nginx, Docker.
2. **Tumbuh:** horizontal app instances (stateless), pisahkan queue worker per jenis (payments, notifications, reports), tambah index, query optimization.
3. **Lebih besar:** read replica untuk report, Redis cluster/cache tier terpisah, CDN aset.
4. **Ekstrem:** ekstrak modul yang benar-benar panas (mis. Notification, Reporting) menjadi service terpisah — hanya dengan trigger operasional nyata.

**Prinsip:** siap untuk diekstrak, tapi tidak diekstrak sebelum perlu.

---

## 10. Security Architecture (ringkasan; detail di Risk Register)

1. Firebase ID Token verification penuh (signature, iss, aud, exp, revocation).
2. Server-side authorization (Policy/Gate/RBAC dari DB).
3. Request validation ketat (FormRequest) + whitelist field.
4. Rate limiting per user/IP/endpoint (Redis).
5. HTTPS wajib; HSTS.
6. Secrets via environment/secret manager; tidak ada service account JSON di repo.
7. Eloquent parameter binding (anti SQL injection); no raw user input.
8. Output escaping (anti XSS) di Next.js (React auto-escape) + sanitasi rich text.
9. CORS dibatasi origin frontend resmi.
10. Upload validation: MIME, ekstensi, ukuran, dimensi gambar, scan opsional.
11. Webhook signature verification + amount check + idempotency.
12. Audit log untuk aksi sensitif (approve seller, refund, payout, ubah role).
13. Admin MFA (Firebase MFA) + IP allowlist opsional.
14. Seller ownership enforcement di setiap query produk/order/payout.
15. Akses data sensitif (dokumen seller, bank account) dibatasi & dienkripsi at-rest (Laravel `encrypted` cast untuk nomor rekening).
16. Firebase App Check sebagai lapisan tambahan (bukan pengganti authz Laravel).

---

## 11. Technology Stack (final, terverifikasi)

**Backend:** PHP 8.3.32 · Laravel 13.x · MySQL 8.0 · Redis (+predis/phpredis) · Pest 4.7.8 · Laravel Pint · dedoc/scramble (OpenAPI) · kreait/laravel-firebase 7.2.1 · spatie/laravel-permission 8.5.0 · league/flysystem-aws-s3-v3.

**Frontend:** Next.js 16.4.5 (App Router) · React 19.3.1 · TypeScript 5.9.x · Tailwind CSS 4.3.5 · TanStack Query 5.x · Firebase JS SDK · zod.

**Infra awal:** Docker + Docker Compose + Nginx · MySQL · Redis · queue worker.

---

## 12. Architecture Decision Records (ringkas)

| ID | Keputusan | Alasan | Konsekuensi |
|---|---|---|---|
| ADR-001 | Modular Monolith, bukan microservices | Kompleksitas tim & skala belum menuntut distribusi | Batas modul wajib disiplin; ekstraksi nanti mungkin |
| ADR-002 | Firebase Auth sebagai IdP saja | Mengurangi beban auth; Firebase kuat di identity | Laravel wajib verifikasi penuh & sinkronisasi user |
| ADR-003 | Integer rupiah untuk uang | Menghindari galat float pada uang | Butuh konsistensi di semua layer |
| ADR-004 | Ledger wallet immutable + entri pembalik | Auditabilitas & keamanan finansial | Koreksi tidak boleh UPDATE/DELETE |
| ADR-005 | Reservasi stok dengan expiry | Mencegah overselling saat checkout | Butuh job pembersih & idempotensi |
| ADR-006 | Payment gateway via interface | Ganti provider tanpa ubah business logic | Perlu adapter & pemetaan status |
| ADR-007 | Snapshot data pada order | Riwayat tidak berubah walau produk/alamat berubah | Redundansi data yang disengaja |
| ADR-008 | Commission snapshot pada transaksi | Perubahan rate tidak mengubah transaksi lama | Simpan rate + base di `commissions` |
| ADR-009 | Transactional outbox | Event finansial andal & exactly-once effect | Butuh tabel outbox + publisher job |
| ADR-010 | Pest 4 (bukan 5), TS 5.9 (bukan 7) | Kompatibilitas lingkungan & stabilitas | Sedikit tertinggal fitur terbaru |

---

## 13. Definition of Done (per fitur)

1. Migration + seeder + factory.
2. Model + relasi + cast + enum.
3. FormRequest + Action/Service + Resource.
4. Policy/Gate + permission seed.
5. Feature test (happy path + authz + validation error).
6. Unit test untuk kalkulasi (bila ada).
7. OpenAPI ter-generate (scramble).
8. Audit log (bila aksi sensitif).
9. Tidak ada secret di kode.
10. Lulus Pint + seluruh test.
