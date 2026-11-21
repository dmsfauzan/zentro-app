# 07 — Folder Structure (Laravel & Next.js)

Struktur ini **disiapkan sebagai target** pada Phase 1, bukan hasil yang sudah dibuat. Prinsip: modular monolith dengan batas domain yang jelas.

---

## 1. Backend — Laravel 13

```
backend/
├─ app/
│  ├─ Enums/                          # Enum status yang stabil
│  │  ├─ UserStatus.php
│  │  ├─ SellerStatus.php
│  │  ├─ ProductStatus.php
│  │  ├─ OrderStatus.php
│  │  ├─ SellerOrderStatus.php
│  │  ├─ PaymentStatus.php
│  │  ├─ ShipmentStatus.php
│  │  ├─ PayoutStatus.php
│  │  ├─ WalletTransactionType.php
│  │  └─ BalanceBucket.php
│  │
│  ├─ Modules/                        # Bounded contexts (inti modular monolith)
│  │  ├─ Identity/
│  │  │  ├─ Http/{Controllers,Requests,Resources,Middleware}
│  │  │  ├─ Actions/
│  │  │  ├─ Services/
│  │  │  ├─ Policies/
│  │  │  ├─ Models/
│  │  │  ├─ DTO/
│  │  │  ├─ Events/ + Listeners/
│  │  │  ├─ Routes/api.php
│  │  │  └─ Tests/
│  │  ├─ Seller/
│  │  ├─ Catalog/
│  │  ├─ Inventory/
│  │  ├─ Cart/
│  │  ├─ Order/
│  │  ├─ Payment/
│  │  ├─ Shipping/
│  │  ├─ Finance/
│  │  ├─ Promotion/
│  │  ├─ Review/
│  │  ├─ Notification/
│  │  ├─ Region/                      # Direktori wilayah (provinsi → kelurahan)
│  │  └─ Platform/
│  │
│  ├─ Support/                        # Building blocks lintas modul
│  │  ├─ Money/Money.php
│  │  ├─ ValueObjects/
│  │  ├─ DTO/DataTransferObject.php
│  │  ├─ Enums/
│  │  ├─ Concerns/ (HasUuid, LogsAudit, HasMoneyCasts)
│  │  ├─ Exceptions/ (DomainException, InsufficientStockException, ...)
│  │  ├─ Http/ApiResponse.php
│  │  └─ Idempotency/IdempotencyService.php
│  │
│  ├─ Contracts/                      # Interface lintas modul (SOLID/DIP)
│  │  ├─ Payment/PaymentGatewayInterface.php
│  │  ├─ Payment/PaymentGatewayManager.php
│  │  ├─ Shipping/ShippingProviderInterface.php
│  │  ├─ Shipping/ShippingProviderManager.php
│  │  └─ Storage/FileStorageInterface.php
│  │
│  ├─ Infrastructure/                 # Implementasi konkret (adapters)
│  │  ├─ Payment/{MidtransGateway,XenditGateway}.php
│  │  ├─ Shipping/{ManualShippingProvider,FakeShippingProvider}.php
│  │  ├─ Firebase/FirebaseTokenVerifier.php
│  │  ├─ Storage/S3FileStorage.php
│  │  └─ Providers/ (ServiceProvider binding)
│  │
│  ├─ Jobs/
│  │  ├─ ReleaseExpiredReservationsJob.php
│  │  ├─ ExpireOrdersJob.php
│  │  ├─ PublishOutboxEventsJob.php
│  │  ├─ SendOrderNotificationJob.php
│  │  ├─ SyncShipmentTrackingJob.php
│  │  ├─ EvaluateSettlementJob.php
│  │  └─ ReconcilePaymentsJob.php
│  │
│  ├─ Console/Commands/
│  ├─ Models/                         # Model global (User, Role, ...) bila tidak modul-spesifik
│  └─ Providers/
│
├─ bootstrap/
├─ config/
│  ├─ services.php   (firebase, midtrans, xendit, storage)
│  ├─ zentro.php     (konfigurasi domain: settlement hold, timeout, dsb)
│  └─ ...
├─ database/
│  ├─ migrations/
│  ├─ seeders/       (RoleSeeder, PermissionSeeder, CategorySeeder, CommissionRuleSeeder, AdminSeeder)
│  └─ factories/
├─ routes/
│  ├─ api.php        (memuat Routes/api.php tiap modul, prefix /v1)
│  ├─ console.php
│  └─ web.php
├─ tests/
│  ├─ Unit/          (Money, CommissionCalculator, VoucherCalculator, StateGuards)
│  ├─ Feature/       (Auth, Seller, Catalog, Cart, Checkout, Order, Payment, Shipping, Wallet, Payout, Admin)
│  ├─ Concurrency/   (overselling, double webhook, idempotency, double payout, cross-seller access)
│  └─ TestCase.php
├─ docker/
│  ├─ nginx/default.conf
│  ├─ php/Dockerfile
│  └─ mysql/my.cnf
├─ docker-compose.yml
├─ .env.example
├─ composer.json
└─ pint.json
```

**Catatan pemuatan modul:** pada Phase 1 cukup gunakan `composer.json` PSR-4 tambahan untuk `App\Modules\` → `app/Modules/`, lalu daftarkan route tiap modul di `bootstrap/app.php`. Tidak perlu paket modul pihak ketiga.

### 2.2 Implementasi aktual (Phase 8.5)

Struktur yang benar-benar dibuat sedikit berbeda dari target awal:

```
frontend/src/
├─ app/
│  ├─ (shop)/        home, produk, kategori, cari, keranjang, checkout,
│  │                 pembayaran/[orderNumber], pesanan, akun, notifikasi
│  ├─ (auth)/        login, register
│  ├─ seller/        dashboard, onboarding, produk, inventori, pesanan,
│  │                 pengiriman, dompet, komisi, payout
│  ├─ admin/         dashboard, seller, produk, pesanan, pembayaran, refund,
│  │                 komisi, voucher, payout, audit-log
│  └─ dev/design/    galeri token & komponen (non-produksi)
├─ components/       brand, ui, layout, shop, seller
├─ features/         catalog, cart, checkout, orders, account, seller,
│                    wallet, admin, shipping, reviews, notifications,
│                    promotion, http (API + hook per domain)
├─ lib/              api, auth, design, firebase, query, format, idempotency,
│                    use-url-filters, cn
├─ providers/        AuthProvider, QueryProvider, ToastProvider
└─ proxy.ts          guard UX (pengganti middleware Next 16)
```

**Catatan Next 16:** `middleware` diganti `proxy.ts`; `params`/`searchParams` async; `cacheComponents` **dimatikan** untuk MVP frontend ini (data per-user via TanStack Query, build deterministik). Detail: `docs/19`.

**Backend tambahan (Phase 8.5):** modul `Promotion` (`vouchers`), `Review`, `Notification`,
serta endpoint read-only admin (`/admin/orders`, `/admin/payments`, `/admin/audit-logs`)
dan `/seller/commissions`. Middleware lintas modul: `AssignRequestId`, `SecurityHeaders`,
`LogRequests` (observability).

---

## 2. Frontend — Next.js 16 (App Router)

Dua opsi; **default: monorepo dengan aplikasi tunggal + route group** (paling sederhana, aman untuk skala awal).

```
frontend/
├─ src/
│  ├─ app/
│  │  ├─ (marketing)/                 # halaman publik opsional
│  │  ├─ (shop)/                      # Customer Marketplace
│  │  │  ├─ page.tsx                  # Homepage
│  │  │  ├─ products/page.tsx
│  │  │  ├─ products/[slug]/page.tsx
│  │  │  ├─ categories/[slug]/page.tsx
│  │  │  ├─ search/page.tsx
│  │  │  ├─ cart/page.tsx
│  │  │  ├─ checkout/page.tsx
│  │  │  ├─ orders/page.tsx
│  │  │  ├─ orders/[orderNumber]/page.tsx
│  │  │  └─ profile/ (addresses, reviews)
│  │  ├─ (auth)/
│  │  │  ├─ login/page.tsx
│  │  │  ├─ register/page.tsx
│  │  │  └─ forgot-password/page.tsx
│  │  ├─ seller/                      # Seller Dashboard (protected)
│  │  │  ├─ layout.tsx
│  │  │  ├─ page.tsx                  # summary
│  │  │  ├─ onboarding/page.tsx       # registrasi + upload dokumen
│  │  │  ├─ products/ (list, new, [id], variants, images)
│  │  │  ├─ inventory/page.tsx
│  │  │  ├─ orders/ (list, [id], shipments)
│  │  │  ├─ wallet/page.tsx
│  │  │  ├─ commissions/page.tsx
│  │  │  └─ payouts/page.tsx
│  │  ├─ admin/                       # Admin Dashboard (protected)
│  │  │  ├─ layout.tsx
│  │  │  ├─ page.tsx
│  │  │  ├─ users/page.tsx
│  │  │  ├─ sellers/page.tsx + [id]/page.tsx
│  │  │  ├─ products/page.tsx
│  │  │  ├─ orders/page.tsx
│  │  │  ├─ payments/page.tsx
│  │  │  ├─ refunds/page.tsx
│  │  │  ├─ commissions/page.tsx
│  │  │  ├─ payouts/page.tsx
│  │  │  ├─ vouchers/page.tsx
│  │  │  ├─ reports/page.tsx
│  │  │  └─ audit-logs/page.tsx
│  │  ├─ layout.tsx
│  │  └─ globals.css                  # Tailwind v4 @theme
│  │
│  ├─ components/
│  │  ├─ ui/            (Button, Input, Modal, Table, Badge, Pagination, Skeleton, EmptyState)
│  │  ├─ shop/
│  │  ├─ seller/
│  │  └─ admin/
│  │
│  ├─ features/         (logic per domain: hooks + api + types)
│  │  ├─ auth/
│  │  ├─ catalog/
│  │  ├─ cart/
│  │  ├─ checkout/
│  │  ├─ orders/
│  │  ├─ seller/
│  │  ├─ wallet/
│  │  └─ admin/
│  │
│  ├─ lib/
│  │  ├─ api/client.ts          # centralized fetch wrapper + auth + error handling
│  │  ├─ api/endpoints.ts
│  │  ├─ firebase/client.ts     # inisialisasi Firebase CLIENT (public keys only)
│  │  ├─ auth/session.ts        # token handling
│  │  ├─ query/client.ts        # TanStack Query provider
│  │  ├─ validation/ (zod schemas)
│  │  ├─ format.ts (currency Rupiah, date)
│  │  └─ constants.ts
│  │
│  ├─ providers/        (AuthProvider, QueryProvider, ToastProvider)
│  ├─ middleware.ts     # proteksi route (redirect bila belum login) — bukan authz final
│  └─ types/
│
├─ public/
├─ .env.local.example
├─ next.config.ts
├─ tailwind (via CSS)
├─ tsconfig.json
└─ package.json
```

### 2.1 Aturan Frontend- **Authz final tetap di Laravel.** `middleware.ts` hanya untuk UX (redirect), bukan penentu akses data.
- **Token handling:** Firebase ID Token disimpan di memori/`sessionStorage` sadar-XSS, **bukan** `localStorage` untuk token jangka panjang; refresh lewat Firebase SDK. Untuk SSR, gunakan **Firebase session cookie** HttpOnly bila diperlukan (opsional, Phase 8).
- **API client terpusat** (`lib/api/client.ts`): menyisipkan Bearer token, menangani 401 (refresh/redirect), format error konsisten, timeout.
- **Semua halaman** punya loading, error, empty state, dan validasi form (zod + pesan dari 422).
- Kredensial Firebase **client** (`NEXT_PUBLIC_FIREBASE_*`) boleh publik; **service account** tidak pernah ada di frontend.

---

## 3. Env yang Dibutuhkan (ringkas)

**Backend (`backend/.env`)**
```
APP_NAME=ZENTRO
APP_URL=
DB_*=
REDIS_*
CACHE_STORE=redis
QUEUE_CONNECTION=redis
FIREBASE_CREDENTIALS=          # path/JSON via secret manager
FIREBASE_PROJECT_ID=
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
XENDIT_SECRET_KEY=
AWS_ACCESS_KEY_ID= / AWS_SECRET_ACCESS_KEY= / AWS_BUCKET= / AWS_ENDPOINT=
CORS_ALLOWED_ORIGINS=
```

**Frontend (`frontend/.env.local`)**
```
NEXT_PUBLIC_API_BASE_URL=
NEXT_PUBLIC_FIREBASE_API_KEY=
NEXT_PUBLIC_FIREBASE_AUTH_DOMAIN=
NEXT_PUBLIC_FIREBASE_PROJECT_ID=
NEXT_PUBLIC_FIREBASE_APP_ID=
```

Tidak ada secret service-account di repo. `.env*` di-`.gitignore`; sediakan `.env.example` tanpa nilai rahasia.

---

## 4. Repo Layout

```
onlineshop/                (root workspace)
├─ backend/                Laravel API
├─ frontend/               Next.js
├─ docs/                   Dokumen arsitektur ini
├─ scripts/                scan-secrets, backup/restore (PowerShell)
├─ docker/nginx/           reverse proxy staging
├─ docker-compose.staging.yml
├─ .github/workflows/      CI (Pint, Pest, lint, typecheck, build, secret scan)
└─ README.md
```

Alternatif bila Anda ingin dua repo terpisah: pisahkan `backend/` dan `frontend/` menjadi repo sendiri. Rekomendasi awal: **satu repo** agar kontrak API & dokumen sinkron.
