# 11 — Phase 1 Implementation Report (Foundation)

**Date:** 2026-11-09
**Status:** ✅ Selesai & terverifikasi (backend test hijau, frontend build hijau)
**Scope:** Fondasi aplikasi, autentikasi Firebase, RBAC, response envelope, dokumentasi API, test dasar.

---

## 1. Objective

Menyediakan kerangka ZENTRO yang dapat dijalankan:

- Laravel 13 API + Next.js 16 frontend ter-bootstrap dan saling terhubung.
- Autentikasi via Firebase ID Token **diverifikasi server-side**.
- User disinkronkan ke MySQL berdasarkan `firebase_uid` (bukan input klien).
- RBAC (role + permission dari database) siap dipakai fase berikutnya.
- Response envelope konsisten + penanganan error terpusat.
- Dokumentasi OpenAPI dapat diakses.
- Test dasar untuk auth, RBAC, ownership alamat, dan anti-spoofing.

## 2. Architecture Decision (yang dieksekusi)

| Keputusan | Implementasi |
|---|---|
| Modular monolith | `app/Modules/Identity`, `app/Modules/Platform`; route per modul dimuat dari `app/Modules/*/Routes/api.php` |
| Clean layering | Controller tipis → FormRequest → Action (`SyncFirebaseUserAction`) → Model/Policy |
| Firebase = IdP saja | `FirebaseTokenVerifier` contract + `KreaitFirebaseTokenVerifier` (produksi) + `FakeFirebaseTokenVerifier` (lokal/test) |
| Authz server-side | `VerifyFirebaseToken` middleware, Spatie RBAC, `Gate::before` untuk `super_admin` |
| Envelope konsisten | `ApiResponse` + `ExceptionRenderer` |
| Verifikasi penuh | signature/issuer/audience/expiry + `checkIfRevoked: true` |
| Versi | Laravel 13.40.0 · PHP 8.3.32 · Pest 4.7.8 · Next 16.4.5 · React 19.3.0 · TypeScript 5.9.3 · Tailwind 4.3.5 |

## 3. Files Created / Modified

### Backend — dibuat
```
app/Enums/UserStatus.php
app/Support/Exceptions/{ErrorCode,ApiException}.php
app/Support/Http/{ApiResponse,ExceptionRenderer}.php
app/Support/Http/Middleware/AssignRequestId.php
app/Contracts/Auth/FirebaseTokenVerifier.php
app/Infrastructure/Firebase/{FirebaseVerificationException,FakeFirebaseTokenVerifier,KreaitFirebaseTokenVerifier}.php
app/Modules/Identity/DTO/FirebaseIdentity.php
app/Modules/Identity/Actions/SyncFirebaseUserAction.php
app/Modules/Identity/Models/{User,UserAddress,UserDevice}.php
app/Modules/Identity/Http/Middleware/VerifyFirebaseToken.php
app/Modules/Identity/Http/Controllers/{AuthController,MeController,AddressController}.php
app/Modules/Identity/Http/Requests/{FirebaseLoginRequest,UpdateProfileRequest,StoreAddressRequest,UpdateAddressRequest}.php
app/Modules/Identity/Http/Resources/{UserResource,UserAddressResource}.php
app/Modules/Identity/Policies/UserAddressPolicy.php
app/Modules/Identity/Routes/api.php
app/Modules/Platform/Models/{PlatformSetting,AuditLog}.php
routes/api.php
app/Support/Http/ExceptionRenderer.php
config/cors.php
config/scramble.php (dipublikasikan + security scheme)
database/migrations/2026_11_09_1000{01..04}_*.php
database/factories/{UserFactory,UserAddressFactory}.php
database/seeders/{RolePermissionSeeder,DemoUsersSeeder}.php
tests/Pest.php
tests/Feature/Auth/FirebaseAuthTest.php
tests/Feature/Identity/{MeTest,AddressTest}.php
tests/Unit/FakeFirebaseTokenVerifierTest.php
```

### Backend — dimodifikasi
```
composer.json (dependency baru)
.env / .env.example        (MySQL zentro, Redis, Firebase, CORS)
phpunit.xml                (MySQL zentro_test, fake verifier)
config/auth.php            (model User → App\Modules\Identity\Models\User)
config/firebase.php        (+ fake_verifier)
config/permission.php      (tabel pivot → user_roles / role_permissions)
bootstrap/app.php          (api routing, middleware, exception renderer)
app/Providers/AppServiceProvider.php (binding verifier, Gate, rate limiter)
database/migrations/0001_01_01_000000_create_users_table.php
database/seeders/DatabaseSeeder.php
```

### Frontend — dibuat
```
src/lib/config.ts
src/lib/api/{client,types}.ts
src/lib/format.ts
src/components/ui/Button.tsx
src/components/brand/Logo.tsx
src/app/icon.png              (logo penuh, transparan)
src/app/apple-icon.png
src/app/favicon.ico           (16/32/48)
public/brand/zentro-logo.jpg  (logo resmi)
public/brand/zentro-mark.png  (crop mark Z)
.env.local.example
```

### Frontend — dimodifikasi
```
src/app/layout.tsx   (metadata ZENTRO, lang=id)
src/app/page.tsx     (landing branded)
```

### Environment — diubah
```
C:\laragon\bin\php\php-8.3.32-Win32-vs16-x64\php.ini
  ;extension=sodium  →  extension=sodium   (dibutuhkan Firebase JWT)
```
Redis server dinyalakan (`redis-server.exe`) karena `CACHE_STORE=redis`.

## 4. Database Changes

Database: `zentro` (dev) dan `zentro_test` (test). **8 migrasi** berhasil.

| Tabel | Keterangan |
|---|---|
| `users` | + `firebase_uid` (UQ), `phone`, `status` (idx), `last_login_at`, soft delete; `password` nullable |
| `roles`, `permissions` | standar Spatie (guard `web`) |
| `user_roles` | pivot polymorphic (`role_id`, `model_type`, `model_id`) |
| `role_permissions` | pivot (`permission_id`, `role_id`) |
| `user_addresses` | + generated column `default_uniq` + UNIQUE(user_id, default_uniq) → **satu alamat default per user** |
| `user_devices` | `fcm_token` UNIQUE |
| `platform_settings` | `key` UNIQUE, `value` JSON |
| `audit_logs` | append-only (`created_at` saja) + index entity/actor |

Seed: 13 permission, 4 role (super_admin, admin, customer, seller), 4 akun demo (local).

**Deviasi dari dokumen desain (disengaja):**
- `user_roles` menggunakan pivot polymorphic Spatie, bukan `(user_id, role_id)`. Lihat `docs/02` & `docs/03 §A4`.
- `roles`/`permissions` tidak memiliki kolom `description` (standar Spatie).

## 5. Implementation Highlights

- **Middleware auth reusable** `auth.firebase`: ambil Bearer token → verifikasi → sync user → set `Auth::setUser` + `request->setUserResolver`.
- **Anti-spoofing**: `firebase_uid` hanya dari token terverifikasi; body `firebase_uid`/`roles` diabaikan (ada test khusus).
- **Sinkronisasi idempotent**: user dicari by `firebase_uid`; update nama/email/verified/last_login; user baru otomatis role `customer`.
- **Account status**: user `suspended`/`banned`/soft-deleted ditolak `403 AUTH_ACCOUNT_DISABLED`.
- **Email conflict**: email milik akun lain → `409 EMAIL_ALREADY_LINKED` (tanpa auto-merge).
- **Rate limit**: `auth` 10/menit per IP; `api` 120/menit per user/IP.
- **Request ID**: `X-Request-Id` dibuat/diteruskan & dikembalikan di response.
- **CORS**: hanya origin dari `CORS_ALLOWED_ORIGINS`.
- **OpenAPI**: `/docs/api` & `/docs/api.json` (OpenAPI 3.1.2) dengan security scheme `bearer` / `Firebase ID Token`.

## 6. Security Considerations

| Kontrol | Status |
|---|---|
| Verifikasi signature/iss/aud/exp + revoked | ✅ (Kreait) |
| UID tidak dari body | ✅ + test |
| Role tidak dari frontend | ✅ + test |
| RBAC dari DB | ✅ |
| Policy ownership alamat | ✅ + test (403 lintas user) |
| Rate limiting | ✅ |
| CORS terbatas | ✅ |
| Secret tidak di repo | ✅ `.env.example` kosong |
| Fake verifier hanya non-produksi | ⚠️ dikontrol env `FIREBASE_FAKE_VERIFIER`; **wajib false di produksi** |
| Admin MFA | ⏳ Phase 9 |

## 7. Testing Results

```
Backend : 27 passed (82 assertions) — Pest 4.7.8
Frontend: eslint OK, `next build` OK
Migrasi : migrate:fresh --seed OK (MySQL 8.0.30)
Smoke   : POST /auth/firebase, GET /me (401 & 200), POST/GET /me/addresses OK
OpenAPI : /docs/api.json OK (4 path, security scheme bearer)
```

Cakupan test:
- Auth: register, role default, invalid/expired/disabled token, validasi, user suspended, email conflict, sinkronisasi tanpa duplikat.
- `/me`: 401 tanpa token, profil + roles + permissions, update, validasi, **body tidak dipercaya**.
- Alamat: isolasi per user, default otomatis, hanya satu default, **403 lintas user**, delete, validasi.
- Unit: pemetaan klaim verifier fake (JSON & base64url), invalid/expired/disabled.

## 8. How to Run

```powershell
# 1) Redis (sekali)
Start-Process "C:\laragon\bin\redis\redis-x64-5.0.14.1\redis-server.exe" -WorkingDirectory "C:\laragon\bin\redis\redis-x64-5.0.14.1"

# 2) Backend
cd C:\laragon\www\onlineshop\backend
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
# Docs: http://127.0.0.1:8000/docs/api

# 3) Test
php artisan test

# 4) Frontend
cd C:\laragon\www\onlineshop\frontend
copy .env.local.example .env.local
npm run dev        # http://localhost:3000
```

**Login lokal (fake verifier):** buat token base64url dari JSON klaim, mis.
`{"uid":"demo-customer","email":"customer@zentro.test","exp":<epoch+3600>}` lalu kirim:

```http
POST /api/v1/auth/firebase
{ "id_token": "<token>" }
```

## 9. Known Limitations

1. Kredensial Firebase asli belum tersedia → integrasi produksi belum diuji; gunakan `FIREBASE_FAKE_VERIFIER=false` + service account saat tersedia.
2. `FIREBASE_FAKE_VERIFIER` **tidak boleh** aktif di produksi (belum ada guard yang memaksa gagal saat `APP_ENV=production`; akan ditambahkan di Phase 9).
3. Redirect `DELETE` mengembalikan `200` + envelope (bukan `204`) demi konsistensi envelope. Akan didokumentasikan di kontrak final.
4. Rate limiter memakai Redis; Redis harus berjalan.
5. Frontend baru fondasi (config, API client, util, satu komponen, landing). Tiga area penuh di Phase 8.
6. Sanctum belum dipasang (belum diperlukan; auth murni Firebase ID Token).
7. Belum ada CI/CD, Docker, dan admin MFA.

## 10. Next Phase

**Phase 2 — Seller Management**: `sellers`, `seller_documents`, `seller_addresses`, aplikasi seller, upload dokumen, approval/reject/suspend admin, dashboard seller, dan enforcement kepemilikan.

**Prasyarat keputusan (dari docs/10):** dokumen KYC wajib (B-12), moderasi produk (B-11), kanal notifikasi (B-16).
