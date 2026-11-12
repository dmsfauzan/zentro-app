# 09 — Risk Register (Security & Financial)

Skala: **Dampak** (L/M/H) × **Probabilitas** (L/M/H). Prioritas = turunan keduanya.

---

## A. Risiko Keamanan

| ID | Risiko | Dampak | Prob | Mitigasi |
|---|---|---|---|---|
| SEC-01 | Firebase ID Token palsu/di-replay | H | M | Verifikasi signature/iss/aud/exp penuh; opsional revocation check; jangan percaya UID dari body |
| SEC-02 | Role/permission dipalsukan dari frontend | H | M | Authz selalu dari DB (Policy/Gate); cache per user di-invalidate saat perubahan |
| SEC-03 | IDOR: seller mengakses order/produk seller lain | H | M | Policy + scoped query wajib; feature test lintas seller |
| SEC-04 | Webhook payment dipalsukan | H | M | Signature verification + IP allowlist + amount check + idempotency |
| SEC-05 | Webhook ganda / replay → double-pay | H | M | `payment_webhook_events.provider_event_id` UNIQUE + status check dalam lock |
| SEC-06 | SQL injection | H | L | Eloquent binding; hindari raw SQL; validasi input |
| SEC-07 | XSS via deskripsi produk/ulasan | M | M | Sanitasi HTML (HTMLPurifier/DOMPurify), escape output |
| SEC-08 | CSRF | M | L | API token-based (Bearer) tanpa cookie session; bila pakai cookie → SameSite+token |
| SEC-09 | CORS terlalu terbuka | M | M | Allowlist origin frontend resmi |
| SEC-10 | Upload file berbahaya | H | M | Validasi MIME/ekstensi/ukuran, simpan di S3 private, presigned URL, tanpa eksekusi |
| SEC-11 | Kebocoran dokumen seller (KTP/NPWP) | H | M | Akses terbatas + signed URL berumur pendek + audit akses |
| SEC-12 | Kebocoran service account Firebase | H | L | Secret manager/env, tidak di repo, rotasi berkala |
| SEC-13 | Brute force / abuse endpoint | M | M | Rate limiting per IP/user/endpoint, lockout |
| SEC-14 | Admin account takeover | H | M | Firebase MFA wajib untuk admin, IP allowlist opsional, audit login |
| SEC-15 | Prompt/parameter manipulation pada nominal | H | L | Server menghitung ulang semua angka; abaikan nilai dari client |
| SEC-16 | Log memuat data sensitif/secret | M | M | Redaksi PII & secret, structured logging |
| SEC-17 | Dependency rentan | M | M | `composer audit`/`npm audit`, renovate, pin versi |
| SEC-18 | Redis terekspos publik | H | L | Bind lokal/VPC, auth, TLS, tidak expose port |
| SEC-19 | Backup tidak teruji | H | M | Uji restore berkala; enkripsi backup; simpan offsite |
| SEC-20 | Firebase App Check disalahpahami sebagai authz | M | L | Dokumentasikan: App Check hanya lapisan tambahan, bukan pengganti authz Laravel |

---

## B. Risiko Finansial

| ID | Risiko | Dampak | Prob | Mitigasi |
|---|---|---|---|---|
| FIN-01 | Overselling (stok minus) | H | M | Row lock / conditional atomic UPDATE + reservation + **concurrency test** |
| FIN-02 | Pembayaran dobel | H | M | 1 payment per order (UNIQUE), lock + status guard, webhook idempotent |
| FIN-03 | Nominal webhook dimanipulasi | H | M | Validasi amount == payment.amount; tolak mismatch, alert |
| FIN-04 | Payout dobel | H | M | Idempotency-Key + lock wallet + status payout + cek saldo dalam transaksi |
| FIN-05 | Saldo wallet tidak konsisten dengan ledger | H | M | Update saldo & ledger dalam 1 transaksi; job rekonsiliasi harian; ledger immutable |
| FIN-06 | Dana seller dicairkan sebelum settlement | H | M | Bucket pending → available hanya setelah kondisi settlement; hold days |
| FIN-07 | Refund setelah payout → seller berutang | M | M | Utang tercatat eksplisit di bucket `debt` (available tidak negatif), payout diblokir saat utang > 0, offset dari settlement/reserve berikutnya |
| FIN-18 | Refund parsial mendebit penuh | H | M | `RefundAllocation` berbasis komponen + delta kumulatif; test refund parsial |
| FIN-19 | Refund ganda (admin/customer) | H | L | Invariant remaining per komponen di dalam transaksi + row lock; test over-refund |
| FIN-20 | Gateway sukses tetapi finalize gagal | H | M | `provider_refund_key` disimpan sebelum panggil gateway; retry finalize tanpa panggil gateway ulang |
| FIN-08 | Komisi berubah retroaktif | M | M | Snapshot `rate_bp` & `base_amount` di `commissions` |
| FIN-09 | Pembulatan komisi merugikan pihak | M | M | Basis point + `rounding_adjustment` tercatat; kebijakan pembulatan eksplisit |
| FIN-10 | Voucher ganda / limit dilampaui | M | M | Lock voucher row, cek `used_count`, unique `(voucher_id, order_id)` |
| FIN-11 | Ongkir termasuk/tidak termasuk basis komisi tidak jelas | M | M | `commission_rules.includes_shipping` eksplisit + snapshot |
| FIN-12 | Order stuck (`pending_payment` selamanya) | M | H | Job expiry + release reservation; monitoring queue |
| FIN-13 | Settlement tidak jalan (job mati) | H | M | Monitoring queue, alert backlog, health check scheduled |
| FIN-14 | Payout tanpa approval berlapis | H | M | Status machine + permission khusus + audit + opsi dual-control |
| FIN-15 | Fee payment gateway tidak dialokasikan | M | M | Definisikan penanggung fee (platform/seller) di `platform_settings` + catat |
| FIN-16 | Rekonsiliasi provider vs internal tidak cocok | M | M | Job rekonsiliasi harian + laporan selisih |
| FIN-17 | Data bank tidak terenkripsi | H | M | `encrypted` cast untuk nomor rekening; mask di UI/log |
| FIN-21 | Payout dobel potong / saldo dipakai dua kali | H | L | Reservasi `available → payout_reserved` sejak pengajuan + lock wallet + guard satu payout aktif |
| FIN-22 | Refund/chargeback pasca-payout tak tertagih | M | M | `risk_hold` berbasis profil risiko (`seller_risk_profiles`) + pelepasan terjadwal + debt offset; write-off bercatat (Fase 8C) |
| FIN-18 | Regulasi (PPN/PPh, PJP) | H | M | Libatkan kepatuhan; jangan asumsikan split settlement semua provider mendukung |

---

## C. Risiko Teknis & Operasional

| ID | Risiko | Mitigasi |
|---|---|---|
| TEC-01 | Transaksi DB panjang menahan lock | Tidak ada panggilan jaringan di dalam transaksi; batch kecil; deadlock retry |
| TEC-02 | Serialisasi/isolasi MySQL | Gunakan `FOR UPDATE` hati-hati; hindari lock lintas tabel berlebihan |
| TEC-03 | Migration FK circular | Urutan migrasi terdokumentasi (doc 04 §7) |
| TEC-04 | php_redis tidak aktif | Predis sebagai default; aktifkan phpredis untuk produksi |
| TEC-05 | MySQL 8.0.30 (bukan 8.4) | Hindari fitur 8.4; manfa'atkan CHECK (8.0.16+) |
| TEC-06 | TypeScript 7 preview | Pakai TS 5.9.x stabil |
| TEC-07 | Pest 5 butuh PHP 8.4 | Pakai Pest 4.7.8 |
| TEC-08 | Sinkronisasi user Firebase ↔ MySQL | Sinkronisasi idempotent pada setiap request auth; job rekonsiliasi |
| TEC-09 | Search produk membebani DB | FULLTEXT dulu; siap migrasi ke search engine |
| TEC-10 | Vendor lock-in payment | Interface + adapter; hindari asumsi split settlement |
| TEC-11 | Docker belum terverifikasi di mesin ini | Verifikasi saat Phase 1/9; sediakan panduan manual sebagai fallback |
| TEC-12 | Timezone konsistensi | Simpan UTC; tampilkan lokal; `platform_settings` untuk tz |

---

## D. Risiko Produk/Bisnis

| ID | Risiko | Mitigasi |
|---|---|---|
| BIZ-01 | Kebijakan refund/dispute belum ditetapkan | Tetapkan aturan uang & SLA sebelum Phase 7 |
| BIZ-02 | Fee ongkir & siapa menanggung | Definisikan eksplisit |
| BIZ-03 | Siapa menanggung voucher (platform/seller) | Field `funded_by` + `voucher_discount_borne_by_seller` |
| BIZ-04 | Multi-currency di masa depan | Simpan `currency`, hindari asumsi IDR di kode |
| BIZ-05 | Pajak & faktur | Konsultasi kepatuhan; siapkan hook pajak |

---

## E. Top 10 Prioritas Mitigasi

1. FIN-01 Overselling → concurrency test wajib lulus sebelum Phase 4 selesai.
2. SEC-05/FIN-02 Double-pay → test webhook ganda wajib.
3. FIN-04 Double payout → test payout ganda wajib.
4. SEC-03 IDOR lintas seller → test akses lintas seller wajib.
5. FIN-05 Konsistensi saldo-ledger → rekonsiliasi harian + test invariant.
6. SEC-01/SEC-12 Firebase → verifikasi penuh + secret management.
7. FIN-06 Settlement sebelum pencairan → state machine tegas.
8. SEC-10/SEC-11 Upload & dokumen → validasi + signed URL + audit.
9. FIN-17 Enkripsi data bank → `encrypted` cast.
10. SEC-14 Admin MFA → wajibkan sebelum produksi.
