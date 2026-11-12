# 00 — Project Inspection Report

**Project:** ZENTRO — *Everything in One Place*
**Document version:** 1.0
**Date:** 2026-11-09
**Status:** Awaiting architecture sign-off (no code implemented)

---

## 1. Kondisi Direktori

| Item | Hasil |
|---|---|
| Working directory | `C:\laragon\www\onlineshop` |
| Isi direktori | **Kosong** (0 file/folder) |
| Git repository | Belum ada |
| Kode existing | Tidak ada |
| Konfigurasi existing | Tidak ada |
| Kesimpulan | Proyek **greenfield**. Bootstrap penuh diperlukan pada Phase 1. |

Tidak ada kode, dependency, atau konfigurasi lama yang perlu dipertahankan. Tidak ada risiko menimpa pekerjaan pengguna.

---

## 2. Versi Toolchain yang Terpasang (terverifikasi)

| Tool | Versi terpasang | Keterangan |
|---|---|---|
| PHP | 8.3.32 (ZTS, Win32 VS16 x64) | Memenuhi `^8.3` untuk Laravel 13 |
| Composer | 2.10.3 | OK |
| Node.js | v24.17.0 | Next.js 16 butuh `>=20.9.0` |
| npm | 11.13.0 | OK |
| Git | 2.54.0 (Windows) | OK |
| MySQL | 8.0.30 (Laragon) | MySQL **8.0**, bukan 8.4. Fitur 8.0 tersedia; hindari sintaks khusus 8.4 |
| Redis server | Ada di `C:\laragon\bin\redis` | Server tersedia |
| phpredis (ext-redis) | **TIDAK aktif** | `php -m` tidak memuat `redis`; DLL tidak ada di extension_dir |
| `pdo_mysql` | Aktif | OK |
| `bcmath` | Aktif | Berguna untuk perhitungan uang (walaupun utama pakai integer) |
| `intl` | Aktif | OK |
| `sodium` | **Tidak terdeteksi** | Tidak wajib, tapi enkripsi kolom bank account sebaiknya pakai `APP_KEY` + `encrypted` cast Laravel |
| `zip`, `fileinfo`, `openssl`, `curl`, `mbstring` | Aktif | OK |

### Temuan penting: Redis PHP client

Karena ekstensi `phpredis` belum aktif, ada dua opsi:

1. **Rekomendasi default:** gunakan `predis/predis` v3.7.1 (pure PHP). Cukup untuk cache, queue, rate limit, dan `Cache::lock()`.
2. **Rekomendasi produksi:** aktifkan `phpredis` (lebih cepat, atomic lock lebih baik). Untuk Laragon: unduh DLL `php_redis` yang cocok dengan PHP 8.3 **ZTS x64**, aktifkan di `php.ini`, lalu verifikasi dengan `php -m | findstr redis`.

**Dampak:** `predis` kompatibel dengan seluruh kebutuhan fungsional. Perbedaan hanya performa (minor pada skala awal). Ini bukan blocker.

---

## 3. Versi Dependency Stable yang Tersedia Saat Ini (terverifikasi dari registry)

### Backend

| Paket | Versi stable terbaru | Catatan kompatibilitas |
|---|---|---|
| `laravel/laravel` (skeleton) | **v13.11.0** | `php ^8.3`, framework `^13.17` |
| `laravel/framework` | **v13.40.0** | OK dengan PHP 8.3 |
| `laravel/sanctum` | **v4.3.3** | `illuminate ^11|^12|^13` |
| `spatie/laravel-permission` | **8.5.0** | `illuminate ^12|^13`, `php ^8.3` — **cocok** |
| `kreait/laravel-firebase` | **7.2.1** | `illuminate ^11|^12|^13`, `php ~8.3.0` — **cocok** |
| `kreait/firebase-php` | **8.6.0** | Dependency dari paket di atas |
| `dedoc/scramble` (OpenAPI) | **0.13.53** | `illuminate ^10–^13` — cocok |
| `predis/predis` | **v3.7.1** | Pure PHP Redis client |
| `league/flysystem-aws-s3-v3` | **3.35.3** | S3-compatible |
| `midtrans/midtrans-php` | **2.6.2** | Kandidat provider pertama |
| `pestphp/pest` | **5.3.5** butuh PHP `^8.4` → **tidak bisa** | Gunakan **Pest 4.7.8** (`php ^8.3.0`, PHPUnit 12) |
| `phpunit/phpunit` | 12.x (via Pest 4) | OK |
| `laravel/pint` | latest 1.x | Code style |

> **Keputusan versi backend:** Laravel **13.x** (skeleton 13.11.0 / framework 13.40.0) + PHP 8.3.32 + Pest **4.7.8**.

### Frontend

| Paket | Versi stable terbaru | Catatan |
|---|---|---|
| `next` | **16.4.5** | Butuh Node >= 20.9 (Node 24 OK), React ^19 |
| `react` / `react-dom` | **19.3.1** | OK |
| `typescript` | **7.0.2** (native preview line) | **Risiko**: TS 7 masih lini baru. Untuk stabilitas produksi gunakan **TypeScript 5.9.3** |
| `tailwindcss` | **4.3.5** | Tailwind v4 (config via CSS `@theme`) |
| `@tanstack/react-query` | **5.107.1** | Server state management |
| `zod` | latest 3.x/4.x | Validasi form & parsing response — pin versi saat install |
| `firebase` (JS SDK) | latest 12.x | Client SDK |
| `firebase-admin` | latest | **Hanya** jika perlu server-side session cookie; bukan untuk transaksi marketplace |

> **Keputusan versi frontend:** Next.js **16.4.5**, React **19.3.1**, TypeScript **5.9.x** (bukan 7.x), Tailwind **4.3.5**.

---

## 4. Arsitektur Runtime yang Direncanakan

```
Browser
  │  Firebase JS SDK (login, Google, verify email, reset password)
  ▼
Next.js 16 (App Router, SSR/CSR hybrid)
  │  Authorization: Bearer <Firebase ID Token>
  ▼
Nginx (TLS, reverse proxy, rate limit edge)
  ▼
Laravel 13 API (Modular Monolith)
  ├─ Firebase Admin SDK (verify ID token)
  ├─ MySQL 8.0 (source of truth)
  ├─ Redis (cache, queue, lock, rate limit)
  ├─ Queue workers (Redis driver)
  ├─ S3-compatible storage (product images, seller docs)
  └─ Payment / Shipping provider (outbound, async via queue)
```

**Prinsip:** Firebase hanya identity provider. Semua otorisasi, kepemilikan data, status transaksi, dan uang ditentukan Laravel + MySQL.

---

## 5. Batasan Lingkungan yang Perlu Diketahui

1. **Windows + Laragon.** Perintah `php artisan`/`npm` berjalan native. Docker tetap direkomendasikan untuk staging/production agar parity terjaga; Docker belum diverifikasi aktif di mesin ini.
2. **MySQL 8.0.30.** Gunakan `CHECK` constraint (didukung 8.0.16+), generated column, dan functional index secara hati-hati.
3. **Tidak ada CI/CD terpasang.** Pipeline perlu dibuat pada Phase 1/9.
4. **Kredensial Firebase, payment gateway, dan S3 belum tersedia.** Phase 1 akan memakai abstraksi + mock/fake driver sampai kredensial diberikan.
5. **Tidak ada akses ke terminal pada beberapa lingkungan agent** — semua langkah "how to run" ditulis eksplisit agar dapat dieksekusi manual.

---

## 6. Kesimpulan Inspeksi

Proyek dapat dimulai dari nol tanpa konflik. Stack yang dipilih **stabil dan saling kompatibel** berdasarkan pengecekan registry, dengan dua penyesuaian penting:

- **Pest 4.7.8**, bukan Pest 5 (PHP 8.3).
- **TypeScript 5.9.x**, bukan 7.x (stabilitas).

Tidak ada blocker teknis. Menunggu persetujuan dokumen arsitektur sebelum Phase 1.
