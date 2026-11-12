# 13 — Phase 2 Implementation Report (Seller Management)

**Date:** 2026-11-09
**Status:** ✅ Selesai & terverifikasi (56 test hijau, smoke test end-to-end)
**Scope:** Pendaftaran seller, verifikasi dokumen, approval/reject/suspend, profil toko, dashboard, enforcement kepemilikan.

---

## 1. Objective

Menyediakan seluruh alur manajemen seller:

- User terverifikasi mendaftar toko (status awal `pending`).
- Seller mengunggah dokumen KYC; status berpindah ke `under_review`.
- Admin meninjau, menyetujui, menolak, menangguhkan, atau mengaktifkan kembali.
- Seller mengelola profil toko; dashboard hanya untuk seller aktif.
- Kepemilikan data ditegakkan di server (bukan di frontend).

## 2. Architecture Decision

| Aspek | Keputusan |
|---|---|
| Modul | `app/Modules/Seller` (Actions, Http, Models, Policies, Routes) |
| Alur status | Action terpisah per transisi dengan guard + audit log (`ApproveSellerAction`, dst.) |
| Kepemilikan | `SellerPolicy` / `SellerDocumentPolicy` (auto-discovery Laravel) |
| Middleware | `seller.exists` (punya toko) & `seller.active` (toko aktif) |
| Otorisasi admin | `can:` middleware → Gate + Spatie permission (`manage_sellers`, `approve_sellers`, `suspend_sellers`) |
| Penyimpanan dokumen | Disk **privat** (`config('zentro.documents_disk')`, default `local`) — bukan `public/` |
| KYC wajib | `config('zentro.kyc_required_documents')` default `['ktp']` |
| Audit | `audit_logs` untuk submit, upload, approve, reject, suspend, reactivate |

**Asumsi yang dipakai (keputusan bisnis belum dijawab pengguna):**
- **B-12 (KYC):** KTP wajib; NPWP/NIB/rekening koran opsional — configurable tanpa ubah kode.
- **B-16 (notifikasi):** belum ada pengiriman email/push; sementara hanya audit log. Dijadwalkan pada fase notifikasi.
- **B-11 (moderasi produk):** relevan di Phase 3, belum dipakai.

## 3. Files Created / Modified

### Dibuat
```
app/Enums/{SellerStatus,SellerDocumentType,SellerDocumentStatus,SellerAddressType}.php
app/Modules/Seller/Models/{Seller,SellerDocument,SellerAddress}.php
app/Modules/Seller/Actions/{SubmitSellerApplicationAction,UploadSellerDocumentAction,ApproveSellerAction,RejectSellerAction,SuspendSellerAction,ReactivateSellerAction}.php
app/Modules/Seller/Http/Middleware/{EnsureSellerExists,EnsureSellerActive}.php
app/Modules/Seller/Policies/{SellerPolicy,SellerDocumentPolicy}.php
app/Modules/Seller/Http/Requests/{StoreSellerApplicationRequest,UploadSellerDocumentRequest,UpdateSellerProfileRequest,RejectSellerRequest,SuspendSellerRequest}.php
app/Modules/Seller/Http/Resources/{SellerResource,SellerDocumentResource,SellerAddressResource}.php
app/Modules/Seller/Http/Controllers/{SellerApplicationController,SellerProfileController,SellerDashboardController}.php
app/Modules/Seller/Http/Controllers/Admin/{SellerController,SellerDocumentController}.php
app/Modules/Seller/Routes/api.php
app/Modules/Seller/... (struktur modul)
config/zentro.php
database/migrations/2026_11_09_1100{01..03}_*.php
database/factories/{SellerFactory,SellerDocumentFactory,SellerAddressFactory}.php
tests/Feature/Seller/{SellerApplicationTest,SellerDocumentTest,SellerApprovalTest,SellerProfileTest}.php
```

### Dimodifikasi
```
app/Enums/UserStatus.php (tetap) · app/Support/Exceptions/ErrorCode.php (+7 kode)
app/Modules/Identity/Models/User.php (+relasi seller)
app/Modules/Seller/... · bootstrap/app.php (alias middleware seller.*)
routes/api.php (memuat route Seller)
tests/Pest.php (+helper authHeadersFor, sellerPayload)
```

### Dokumen
```
docs/05-state-machines.md, docs/06-api-spec.md, docs/08-implementation-roadmap.md
docs/13-phase-2-report.md (baru), README.md
```

## 4. Database Changes

3 migrasi baru (MySQL 8.0.30):

| Tabel | Catatan |
|---|---|
| `sellers` | `user_id` UNIQUE (1 user = 1 toko), `slug` UNIQUE, index `(status, created_at)`, kolom audit approval/rejection/suspension |
| `seller_documents` | FK RESTRICT; UNIQUE `(seller_id, type, file_hash)` untuk cegah duplikat; index `(seller_id, type)` |
| `seller_addresses` | FK CASCADE; generated column `default_uniq` + UNIQUE `(seller_id, default_uniq)` → satu alamat default per toko |

## 5. Implementation

| Method | Endpoint | Akses |
|---|---|---|
| POST | `/api/v1/seller-applications` | user terverifikasi, tanpa toko |
| GET | `/api/v1/seller-applications/me` | pemilik |
| POST | `/api/v1/seller-applications/{seller}/documents` | pemilik |
| GET/PATCH | `/api/v1/seller/profile` | pemilik (semua status) |
| GET | `/api/v1/seller/dashboard` | pemilik **aktif** |
| GET | `/api/v1/admin/sellers` | `manage_sellers` |
| POST | `/api/v1/admin/sellers/{seller}/approve` \| `reject` | `approve_sellers` |
| POST | `/api/v1/admin/sellers/{seller}/suspend` \| `reactivate` | `suspend_sellers` |
| GET | `/api/v1/admin/seller-documents/{document}` | `manage_sellers` |

Transisi yang ditegakkan: `pending→under_review` (upload), `pending|under_review→rejected`, `under_review→active` (wajib KYC lengkap), `active→suspended`, `suspended→active`. Transisi lain → `409 STATE_TRANSITION_INVALID`.

## 6. Security Considerations

| Kontrol | Status |
|---|---|
| Otorisasi server-side (Policy + Gate/Spatie) | ✅ + test 403 |
| Kepemilikan seller ditegakkan | ✅ (unggah lintas seller → 403) |
| Dokumen di disk privat | ✅ (bukan `public/`) |
| URL unduh dokumen hanya untuk `manage_sellers` | ✅ |
| Validasi unggahan (MIME + ukuran) | ✅ (`jpg/jpeg/png/pdf`, maks 5 MB) |
| Anti-duplikat dokumen (hash SHA-256) | ✅ |
| Email harus terverifikasi untuk mendaftar | ✅ |
| Audit log aksi sensitif | ✅ |
| Tidak menerima `logo_path`/`banner_path` arbitrer | ✅ (belum ada endpoint upload) |
| Rate limiting endpoint umum | ✅ (Phase 1) |

## 7. Testing Results

```
Backend : 56 passed (190 assertions) — Pest 4.7.8
Pint    : OK
OpenAPI : /docs/api.json → 15 path (termasuk 11 endpoint seller)
```

Cakupan test Phase 2 (23 test baru):
- Aplikasi: butuh auth, dibuat + alamat origin + audit, duplikat ditolak (409), email belum verifikasi (403), validasi (422), `me` (200/404).
- Dokumen: unggah + status → `under_review` + berkas tersimpan, MIME tidak diizinkan (422), tipe tak dikenal (422), duplikat (409), lintas seller (403), saat aktif (409).
- Admin: non-admin 403, list + filter status, approve tanpa dokumen (422), approve sukses (+role `seller`, dokumen `approved`, audit), transisi tidak sah (409), reject + validasi alasan, suspend + reactivate, unduh dokumen (admin 200 / customer 403).
- Profil: tanpa toko (403), ambil profil, update nama + alamat (slug tetap stabil), validasi, dashboard gated (`SELLER_NOT_ACTIVE`) dan sukses untuk seller aktif.

**Smoke test end-to-end (server nyata):** daftar → upload KTP → admin list `under_review` → approve → `seller/profile` menampilkan `active` dan `kyc.complete = true`.

## 8. How to Run

```powershell
cd C:\laragon\www\onlineshop\backend
php artisan migrate            # tambah 3 tabel
php artisan serve              # http://127.0.0.1:8000
php artisan test

# Login lokal (fake verifier): demo-customer / demo-admin / demo-seller
# Alur: POST /api/v1/seller-applications
#       POST /api/v1/seller-applications/{id}/documents (multipart: type, file)
#       POST /api/v1/admin/sellers/{id}/approve (sebagai demo-admin)
```
Dokumen tersimpan di `storage/app/private/seller-documents/{seller_id}/`.

## 9. Known Limitations

1. **Notifikasi belum dikirim** (email/push/WA) — keputusan B-16 masih terbuka; untuk sekarang hanya audit log.
2. **Disk dokumen default `local`.** Belum diuji ke S3 (belum ada kredensial). Ganti `SELLER_DOCUMENTS_DISK` ke disk S3 privat saat tersedia.
3. **Tidak ada antivirus/scan** berkas unggahan (rencana Phase 9).
4. **Logo/banner toko belum bisa diunggah** — kolom ada, endpoint media menyusul di Phase 3.
5. **Dashboard masih placeholder**: `stats` bernilai 0 dengan penanda `stats_available: false` hingga katalog & order ada.
6. **Slug toko stabil** saat `store_name` berubah (untuk menjaga URL). Perubahan slug eksplisit belum ada.
7. **Satu toko per user** (asumsi A-01) — belum mendukung multi-toko.
8. **Regenerasi dokumen yang ditolak**: unggahan baru mengembalikan status ke `under_review`, tetapi riwayat keputusan hanya tersimpan di `audit_logs`.

## 10. Next Phase

**Phase 3 — Catalog & Inventory**: `categories`, `products`, `product_variants`, `product_images`, `inventories`, `inventory_movements`; CRUD produk/varian (owner-scoped), moderasi admin, endpoint publik katalog + cache, upload gambar produk, update stok dengan audit pergerakan.

**Prasyarat keputusan:** **B-11** (produk langsung tayang vs moderasi) dan **B-15** (master data wilayah). Bila belum dijawab, saya akan memakai default: produk `draft` → tayang oleh seller tanpa moderasi, dengan admin dapat menonaktifkan (moderasi reaktif).
