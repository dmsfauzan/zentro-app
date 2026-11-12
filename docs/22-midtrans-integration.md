# 22 — Integrasi Midtrans (Core API, tanpa Snap)

**Status:** ✅ Aktif di sandbox (2026-11-11) · **Driver:** `PAYMENT_GATEWAY=midtrans`
**Scope:** pembuatan transaksi via Core API `/v2/charge` untuk QRIS, e-wallet, dan Virtual Account semua bank; verifikasi webhook; ganti metode pembayaran.

---

## 1. Kredensial (3 nilai berbeda!)

Dashboard Midtrans → **Settings → Access Keys**:

| Env | Contoh | Dipakai untuk |
|---|---|---|
| `MIDTRANS_SERVER_KEY` | `SB-Mid-server-...` | Basic Auth API (charge, status, refund, expire) |
| `MIDTRANS_CLIENT_KEY` | `Mid-client-...` | Hanya bila nanti memakai Snap.js di frontend |
| `MIDTRANS_SIGNATURE_KEY` | `Mid-server-...` | **Verifikasi signature webhook** (akun baru memakai key terpisah) |

> Kesalahan paling umum: memakai `MIDTRANS_SERVER_KEY` untuk memverifikasi signature. Pada akun terbaru
> notifikasi ditandatangani dengan **Signature Secret Key**, sehingga endpoint membalas `401`
> walau transaksi sukses. Bila `MIDTRANS_SIGNATURE_KEY` kosong, adapter otomatis jatuh ke server key
> (perilaku akun lama).

```env
PAYMENT_GATEWAY=midtrans
MIDTRANS_SERVER_KEY=...
MIDTRANS_CLIENT_KEY=...
MIDTRANS_SIGNATURE_KEY=...
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SNAP_URL=https://app.sandbox.midtrans.com
MIDTRANS_API_URL=https://api.sandbox.midtrans.com
MIDTRANS_PAYMENTS=qris,gopay,bca_va,bni_va,bri_va,cimb_va,permata_va,mandiri_va
```

`MIDTRANS_PAYMENTS` menentukan metode yang ditawarkan ke pembeli. Kunci yang tidak dikenal diabaikan.

## 2. Metode yang didukung

| Key | Request Core API | Instruksi yang dirender |
|---|---|---|
| `qris` | `payment_type=qris`, `qris.acquirer=gopay` | `qr_code_url` (gambar), fallback `qr_string` |
| `gopay` | `payment_type=gopay` | QR + `deeplink_url` (buka app GoPay) |
| `shopeepay` | `payment_type=shopeepay` | QR + deeplink |
| `bca_va`, `bni_va`, `bri_va`, `cimb_va`, `permata_va` | `payment_type=bank_transfer`, `bank_transfer.bank=<bank>` | `va_number` |
| `mandiri_va` | `payment_type=echannel` | `biller_code` + `bill_key` |

Kartu kredit **di luar scope** (butuh 3DS/Snap).

## 3. Alur

```
checkout ──► payment row (status=pending, provider_payload kosong)
            └─ GET /orders/{no}/payment → available_methods
buyer pilih metode DI CHECKOUT (tab Transfer Bank / e-Wallet-QRIS)
            └─ POST /checkout  → create order
            └─ POST /orders/{no}/payment/retry {"method":"bca_va"}
                 ├─ (bila ada instruksi lama) POST /v2/{order_id}/expire
                 │    └─ catat provider_payload.swept_transaction_id
                 ├─ POST /v2/charge  → va_number / qr_code_url / deeplink
                 └─ payment.status = requires_action  → redirect /pembayaran/{no}
buyer bayar ─► webhook /api/v1/webhooks/payments/midtrans
                 └─ signature → event durable → order paid + commit stok
```

UI: `src/app/(shop)/checkout/page.tsx` + `src/components/checkout/StoreGroupCard.tsx` dan pemilih metode
`src/components/payment/{PaymentMethodPicker,PaymentMethodLogo}.tsx` (dipakai juga di halaman pembayaran).
Logo bank/e-wallet ada di `frontend/public/banks/` (lihat README di folder itu untuk sumber aset).
Halaman pembayaran tetap menyediakan "Ganti metode" sebagai fallback.

Detail perilaku:

- **Ganti metode**: instruksi metode lama dibuang seluruhnya (tidak ada nomor VA + QR tampil bersamaan).
- **Transaksi lama yang di-expire** mengirim notifikasi `expire`; event itu dikenali lewat
  `swept_transaction_id` dan **diabaikan** agar tidak menurunkan status payment yang baru.
- `payments.payment_method` menyimpan **key internal** (`bca_va`), bukan `payment_type` provider
  (`bank_transfer`) — webhook tidak menimpanya.
- Transaksi yang belum dipilih metodenya bisa membalas `404 Transaction doesn't exist` di Core API;
  rekonsiliasi memperlakukannya sebagai `pending`, bukan `failed`.

## 4. Menjalankan lokal (ngrok)

1. Backend & Redis jalan: `php artisan serve`, `redis-server`, `php artisan schedule:work`.
2. Tunnel: `ngrok http 8000` → salin `https://<host>.ngrok-free.dev`.
3. Dashboard Sandbox → **Settings → Configuration → Payment Notification URL**:
   `https://<host>.ngrok-free.dev/api/v1/webhooks/payments/midtrans`.
   Test notification dashboard kini dibalas **200** (signature-nya memakai Signature Secret Key).
4. Uji cepat:
   ```powershell
   curl.exe -i -X POST https://<host>.ngrok-free.dev/api/v1/webhooks/payments/midtrans `
     -H "Content-Type: application/json" -d "{\"signature_key\":\"salah\"}"   # harus 401
   ```
5. Beli barang → pilih metode → bayar lewat simulator sandbox
   (`https://simulator.sandbox.midtrans.com`), lalu pastikan order menjadi `paid`.

> URL ngrok gratis berubah setiap restart → perbarui Notification URL di dashboard.

**Prasyarat PHP (Windows/Laragon):** request HTTPS dari PHP butuh CA bundle. Bila muncul
`cURL error 60: SSL certificate ... unable to get local issuer certificate`, set di `php.ini`:

```ini
curl.cainfo = "C:\laragon\bin\php\php-8.3.32-Win32-vs16-x64\extras\ssl\cacert.pem"
openssl.cafile = "C:\laragon\bin\php\php-8.3.32-Win32-vs16-x64\extras\ssl\cacert.pem"
```

## 5. Verifikasi manual

```powershell
# cek status transaksi (Basic Auth server key)
curl.exe https://api.sandbox.midtrans.com/v2/ZTR-20261111-000007/status `
  -u "SB-Mid-server-...:"

# cek payload payment di DB
php artisan tinker --execute="..."
```

Webhook idempotensi tersimpan di `payment_webhook_events` (unique `provider_event_id`).

## 6. Test

| File | Cakupan |
|---|---|
| `tests/Feature/Payment/MidtransGatewayTest.php` | charge per metode (QRIS, 5 bank VA, Mandiri, GoPay), validasi metode, expire-on-retry, `fetchStatus`, signature (signature key + fallback) |
| `tests/Feature/Payment/PaymentMethodSelectionTest.php` | `available_methods` di checkout, retry ber-`method`, 422 metode tak dikenal, ganti metode (payload bersih), abaikan notifikasi transaksi lama, `payment_method` tidak tertimpa |
| `tests/Feature/Payment/PaymentWebhookTest.php` | idempotensi, amount check, commit stok, signature invalid |

## 7. Checklist produksi

- [ ] Ganti ke key produksi (`MIDTRANS_IS_PRODUCTION=true`, URL `app.midtrans.com`/`api.midtrans.com`).
- [ ] Pastikan semua metode di `MIDTRANS_PAYMENTS` aktif di akun produksi.
- [ ] Notification URL publik HTTPS (bukan ngrok).
- [ ] `PAYMENT_WEBHOOK_IPS` / firewall bila ingin membatasi pengirim (opsional).
- [ ] Rotasi kredensial yang pernah dibagikan di chat/log; pastikan `backend/.env` tidak ter-commit
      (`scripts/scan-secrets.mjs`).
- [ ] Jalankan `schedule:work` (expire payment, rekonsiliasi 15 menit, settlement harian).
