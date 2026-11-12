# 10 — Assumptions & Business Decisions Needed

Berisi: **asumsi** yang saya ambil saat menyusun rancangan (beserta konsekuensinya), dan **keputusan** yang harus Anda tentukan. Keputusan bertanda **🔴 BLOCKER** wajib dijawab sebelum fase terkait.

---

## B-0. Keputusan yang Sudah Ditetapkan (2026-11-09)

| ID | Keputusan | Status | Konsekuensi arsitektur |
|---|---|---|---|
| B-1 | **Escrow platform + payout terjadwal**. Dana buyer masuk rekening platform, lalu disalurkan ke seller via `seller_payouts`. Split payment tidak dipakai di MVP. | ✅ Ditetapkan | `payments` = satu transaksi ke platform; `seller_wallets` (pending/available/paid) menjadi otoritas dana seller; payout via disbursement/bank transfer. Adapter tetap disiapkan agar split bisa ditambahkan. **Wajib**: kajian kepatuhan/izin penampungan dana sebelum produksi (lihat B-14). |
| B-2 | **Midtrans** sebagai payment gateway utama. | ✅ Ditetapkan & aktif di sandbox (2026-11-11) | `MidtransGateway` mengimplementasikan `PaymentGatewayInterface` memakai **Core API `/v2/charge`** (tanpa Snap): QRIS, e-wallet, VA semua bank. Signature & status mapping Midtrans; panduan di [docs/22](22-midtrans-integration.md). |
| B-3 | **Basis komisi = subtotal produk saja; ongkir TIDAK termasuk.** Diskon yang ditanggung seller mengurangi basis; diskon platform tidak mengurangi basis komisi seller. | ✅ Ditetapkan | `commission_rules.includes_shipping = false` (default global); `commission_base_amount` dihitung dari subtotal produk setelah diskon seller; `voucher_discount_borne_by_seller` & `platform_funded_discount` disimpan snapshot. |
| B-4 | **Rate default 5%** (basis point `500`), dapat dioverride per kategori/seller via `commission_rules`. | ✅ Ditetapkan | Seed `commission_rules` global 5%, `effective_from` = tanggal go-live; override kategori/seller didukung. |

> Keputusan di atas **belum memblokir Phase 1**. Semua tetap dapat direvisi sebelum fase terkait, tetapi perubahan setelah data transaksi ada harus melalui migrasi data + entri pembalik.
>
> **Catatan Phase 5 (2026-11-10):** karena kredensial Midtrans belum tersedia, Phase 5 dijalankan dengan **gateway `fake`** (`PAYMENT_GATEWAY=fake`) sementara `MidtransGateway` sudah ditulis lengkap dan diaktifkan hanya dengan mengganti env. Alur signature, idempotency, amount check, dan commit stok tetap diuji penuh.
>
> **Pembaruan (2026-11-11):** sandbox Midtrans sudah aktif dengan **Core API tanpa Snap** — pembeli memilih metode (QRIS/e-wallet/VA semua bank) di dalam aplikasi. Lihat [docs/22](22-midtrans-integration.md).

---

## A. Asumsi yang Diambil

| ID | Asumsi | Konsekuensi bila salah | Alternatif |
|---|---|---|---|
| A-01 | Satu user = maksimal satu toko (`sellers.user_id` UNIQUE) | Bila ingin multi-toko per user, ubah constraint & seluruh scope seller | Hapus UNIQUE, tambah "active seller" switch |
| A-02 | Produk **fisik** saja untuk MVP (tanpa produk digital) | Produk digital butuh alur fulfillment berbeda (tanpa shipment) | Tipe produk + `digital` fulfillment tanpa shipment |
| A-03 | Mata uang tunggal **IDR**, integer rupiah tanpa sen | Multi-currency butuh kolom & konversi tambahan | Simpan `currency` + rate |
| A-04 | Login wajib (tanpa guest checkout) via Firebase | Guest checkout butuh identitas anonim & penggabungan order | Alur guest + claim |
| A-05 | Satu checkout = satu transaksi payment gateway | Provider harus mendukung satu transaksi multi-seller | Split payment per seller (ubah model §7.1) |
| A-06 | Satu inventory per varian (single lokasi seller) | Multi-warehouse butuh `warehouses` + stok per lokasi | Tambah entitas warehouse |
| A-07 | Cart menyimpan `variant_id` saja; harga selalu dibaca ulang | — | — |
| A-08 | Ongkir mengikuti model per-seller (satu shipment per suborder) | Partial shipment tetap memungkinkan (desain siap) | — |
| A-09 | Berat disimpan dalam gram (`weight_gram`) | — | — |
| A-10 | Firebase Auth sebagai satu-satunya IdP untuk MVP | Social login lain/SSO butuh mapping tambahan | Multi-provider mapping |
| A-11 | Redis server dari Laragon; client Predis (phpredis belum aktif) | Performa produksi lebih baik dengan phpredis | Aktifkan phpredis |
| A-12 | Rekening bank seller = 1 utama per payout (snapshot) | Multi-rekening butuh entitas `seller_bank_accounts` | Tambah entitas bank account |
| A-13 | Alamat wilayah (provinsi/kota/kecamatan/kelurahan) disimpan sebagai teks | Validasi wilayah lemah tanpa ID resmi | Kolom `region_id` + master data |
| A-14 | Admin & seller memakai Firebase Auth yang sama; MFA dikonfigurasi di Firebase | — | — |
| A-15 | `password` pada `users` tidak dipakai (warisan skeleton) | — | Hapus kolom |
| A-16 | Deploy awal: Docker + Nginx + MySQL + Redis (parity), namun **Docker belum diverifikasi** di mesin ini | — | Jalankan manual via Laragon dulu |
| A-17 | Pest **4.7.8** (bukan 5) dan TypeScript **5.9.x** (bukan 7) | — | — |
| A-18 | Komisi memakai **basis point** (`rate_bp`), pembulatan **ke bawah**, selisih dicatat | Pembulatan ke atas/banker's rounding mengubah angka | Ubah kebijakan pembulatan |
| A-19 | Snapshot rate & basis komisi disimpan pada tiap `commissions` | — | — |
| A-20 | Nomor rekening seller dienkripsi at-rest (`encrypted` cast) | — | KMS eksternal |

---

## B. Keputusan Bisnis yang Perlu Ditentukan

Format: **Keputusan — opsi — rekomendasi — dampak arsitektur — blocker fase.**

### B-1. 🔴 Model settlement & kepatuhan dana (KRITIS)

> **Status:** ✅ **DITETAPKAN (2026-11-09): Escrow platform + payout terjadwal.** Lihat §B-0.

- **Pertanyaan:** Apakah platform **memegang dana buyer** (escrow) sebelum diteruskan ke seller, atau dana langsung masuk ke seller via **split payment** provider?
- **Opsi:**
  1. **Escrow platform** — dana masuk rekening platform, disalurkan via disbursement/payout. Butuh kepatuhan (kemungkinan izin PJP/kerja sama), risiko regulasi lebih tinggi.
  2. **Split payment** — provider memisahkan dana seller & platform otomatis. Tergantung dukungan provider.
  3. **Hybrid** — pembayaran masuk platform, payout terjadwal; gunakan rekening penampung bank.
- **Rekomendasi:** mulai dengan **escrow + payout manual/terjadwal** bila belum ada izin; rancang adapter agar split bisa ditambahkan.
- **Dampak:** menentukan desain `payments`, `seller_wallets`, `seller_payouts`, dan interface `PaymentGatewayInterface` (kemunculan method `split`/`disburse`).
- **Blocker:** Phase 5 & 7.

### B-2. 🔴 Provider payment & disbursement

> **Status:** ✅ **DITETAPKAN (2026-11-09): Midtrans (payment gateway utama).** Lihat §B-0.

- **Pertanyaan:** Midtrans, Xendit, atau keduanya? Metode yang diaktifkan (QRIS, VA, e-wallet, kartu)?
- **Rekomendasi:** Midtrans (kartu/VA/e-wallet/QRIS) sebagai default; Xendit bila butuh disbursement kuat.
- **Dampak:** adapter implementation, status mapping, signature scheme.
- **Blocker:** Phase 5.

### B-3. 🔴 Basis komisi (termasuk apa saja?)

> **Status:** ✅ **DITETAPKAN (2026-11-09): subtotal produk saja; ongkir tidak termasuk; diskon penjual mengurangi basis.** Lihat §B-0.

- **Pertanyaan:** Komisi dihitung dari (a) subtotal produk saja, (b) + ongkir, (c) +/– voucher?
- **Rekomendasi:** subtotal produk setelah diskon yang **ditanggung penjual**; **ongkir tidak** termasuk; diskon voucher platform **tidak** mengurangi basis komisi seller.
- **Dampak:** `commission_rules.includes_shipping`, `commission_base_amount`, komponen `voucher_discount_borne_by_seller`/`platform_funded_discount`.
- **Blocker:** Phase 7 (dan snapshot di Phase 4).

### B-4. Rate default komisi

> **Status:** ✅ **DITETAPKAN (2026-11-09): default 5% (rate_bp=500), override per kategori/seller didukung.** Lihat §B-0.

- **Pertanyaan:** Berapa default (mis. 5% / 10%)? Apakah bervariasi per kategori/seller?
- **Rekomendasi:** `commission_rules` global default (mis. 5%) + override kategori/seller.
- **Blocker:** Phase 7 (dibutuhkan seed).

### B-5. Siapa menanggung fee payment gateway?

- **Opsi:** platform / seller / dibagi.
- **Rekomendasi:** platform (disederhanakan untuk MVP), dicatat sebagai biaya platform.
- **Dampak:** perlu komponen `payment_fee` di perhitungan settlement bila ditanggung seller.
- **Blocker:** Phase 7.
- **Status:** ✅ **DITETAPKAN (2026-11-11): platform menanggung fee.** Tidak ada komponen `payment_fee` di ledger seller.

### B-6. Siapa menanggung voucher?

- **Opsi:** platform / seller / sesuai scope voucher.
- **Rekomendasi:** field `vouchers.funded_by` sudah disiapkan; voucher platform ditanggung platform, voucher seller ditanggung seller.
- **Blocker:** Phase 4/7 (perhitungan).

### B-7. Masa hold settlement & auto-complete

- **Pertanyaan:** Berapa lama dana `pending` → `available` setelah order completed? Berapa hari auto-complete setelah delivered?
- **Rekomendasi:** auto-complete 7 hari; settlement hold 7 hari (total ±14 hari dari pengiriman).
- **Dampak:** `platform_settings`, job yang berjalan.
- **Blocker:** Phase 7.

### B-8. Timeout pembayaran & reservasi stok

- **Rekomendasi:** 24 jam untuk pembayaran; reservation = sama.
- **Blocker:** Phase 4/5.

### B-9. 🔴 Kebijakan refund, retur, dan dispute

- **Pertanyaan:** Refund penuh saja atau parsial? Setelah payout, bagaimana? Retur barang?
- **Rekomendasi MVP:** refund **penuh per suborder** (belum parsial per item), sebelum `completed`; refund setelah payout → saldo negatif/utang dikompensasi payout berikutnya.
- **Dampak:** `refunds`, `wallet_transactions` (`refund_debit`), pembalik komisi.
- **Blocker:** Phase 7.
- **Status:** ✅ **DIPERBARUI (2026-11-12, Fase 8A): refund penuh ATAU parsial per sub-order.** Refund dipecah komponen produk/ongkir dan dialokasikan proporsional (debit seller = hak ekonomi atas bagian yang dibatalkan; komisi platform dibalik proporsional) sehingga platform tidak diuntungkan dan seller tidak dirugikan. Refund setelah payout menghasilkan saldo `available` negatif (utang) yang dikompensasi payout berikutnya. Refund di atas sisa yang dapat direfund ditolak (invariant per komponen). **Fase 8B (2026-11-12):** utang dicatat eksplisit di bucket `debt` (bukan saldo `available` negatif) dan dilunasi dari settlement berikutnya; tersedia reserve risiko opsional via `seller_risk_profiles` (rate + masa tahan) yang dilepas terjadwal.

### B-10. Shipping: agregator atau resi manual?

> **Status:** ✅ **DITETAPKAN (2026-11-10): resi manual (`SHIPPING_DRIVER=manual`), tanpa agregator untuk sekarang.** `ShippingProviderInterface` dibuat lengkap sehingga adapter RajaOngkir/Biteship tinggal ditambahkan tanpa mengubah alur fulfillment. Alasan: belum ada kredensial/ID wilayah; pola sama seperti payment `fake` → Midtrans.

- **Opsi:** (1) integrasi RajaOngkir/Biteship/Shipper untuk quote+resi, (2) seller input resi manual (tanpa quote real-time).
- **Rekomendasi MVP:** quote dari salah satu agregator; fallback input manual. Adapter `ShippingProviderInterface` sudah disiapkan.
- **Blocker:** Phase 6.

### B-11. Moderasi produk

- **Pertanyaan:** Produk langsung tayang (`active`) atau butuh review admin dulu?
- **Rekomendasi:** langsung tayang, moderasi reaktif (admin bisa menonaktifkan), untuk mempercepat onboarding.
- **Dampak:** status produk & alur moderasi.
- **Blocker:** Phase 3.

### B-12. KYC & dokumen wajib seller

- **Pertanyaan:** Dokumen minimal (KTP/NPWP/NIB)? Ambang wajib sebelum payout?
- **Rekomendasi:** KTP + rekening bank wajib untuk payout; NIB/NPWP opsional untuk MVP.
- **Blocker:** Phase 2 (approval) & 7 (payout).

### B-13. Approval admin & dual control

- **Pertanyaan:** Perlukah dua approver untuk payout besar (dual control)? Ambang?
- **Rekomendasi:** opsi dual control untuk payout di atas ambang (mis. > Rp10 juta).
- **Blocker:** Phase 7.
- **Status:** ✅ **DITETAPKAN (2026-11-11): nonaktif default** (`PAYOUT_DUAL_CONTROL_THRESHOLD=0`); 1 admin approve + audit log. Struktur ambang disiapkan untuk diaktifkan kemudian.

### B-14. Pajak & faktur

- **Pertanyaan:** Apakah platform menerbitkan faktur? PPN/PPh ditanggung siapa? Integrasi e-Faktur?
- **Rekomendasi:** parkir untuk MVP; sediakan hook; libatkan kepatuhan.
- **Blocker:** tidak memblokir teknis, tapi memblokir produksi berizin.

### B-15. Wilayah & alamat

- **Pertanyaan:** Apakah perlu master data wilayah resmi (provinsi/kota/kecamatan/kelurahan + ID) untuk ongkir akurat?
- **Rekomendasi:** simpan sebagai teks + `region_id` opsional bila agregator menyediakan.
- **Blocker:** Phase 3/4 (bila integrasi ongkir butuh ID wilayah).

### B-16. Notifikasi

- **Pertanyaan:** Kanal mana? Email (provider apa: Mailgun/SES/SMTP), WhatsApp (Fonnte/WABLAS), push FCM, in-app?
- **Rekomendasi MVP:** in-app + email + FCM push; WhatsApp opsional.
- **Blocker:** Phase 2+ (tidak kritis arsitektur, tapi butuh konfigurasi).

### B-17. MFA admin

- **Pertanyaan:** Wajib MFA untuk admin/super_admin?
- **Rekomendasi:** **wajib** sebelum produksi.
- **Blocker:** Phase 9 (produksi).

### B-18. 🟡 Multi-vendor level platform

- Apakah perlu **sub-admin**/peran khusus per kategori, atau cukup super_admin/admin?
- Rekomendasi MVP: cukup 2 level admin.

### B-19. Kebijakan pembatalan order

- Siapa boleh membatalkan dan pada tahap mana (sebelum/sesudah dibayar, sebelum/sesudah dikirim)?
- Rekomendasi: buyer boleh cancel sebelum `confirmed`; sesudahnya hanya seller/admin; sesudah `shipped` → jalur refund/retur.

### B-20. Bahasa & locale

- Rekomendasi MVP: Bahasa Indonesia; simpan format tanggal/waktu UTC, tampilkan WIB.

---

## C. Keputusan Teknis yang Perlu Persetujuan

| ID | Topik | Rekomendasi |
|---|---|---|
| C-01 | Versi stack | Laravel 13 + PHP 8.3, Next.js 16 + TS 5.9, Pest 4.7.8, Tailwind 4 |
| C-02 | Redis client | Predis dulu; phpredis untuk produksi |
| C-03 | Monorepo vs dua repo | Monorepo (`backend/`, `frontend/`, `docs/`) |
| C-04 | Auth session frontend | Firebase ID Token per request; session cookie opsional di Phase 8 |
| C-05 | Search | MySQL FULLTEXT dulu; search engine menyusul bila perlu |
| C-06 | Payment provider abstraction | `PaymentGatewayInterface` + adapter per provider |
| C-07 | Ledger | Single-entry (bucket) untuk MVP, disiapkan menuju double-entry |
| C-08 | Money | Integer rupiah + `Money` VO + basis point untuk persen |
| C-09 | CI/CD | GitHub Actions: lint, test, build (Phase 1/9) |
| C-10 | Hosting awal | VPS + Docker + Nginx, atau PaaS; perlu ditentukan |

---

## D. Ringkasan Blocker Sebelum Mulai

**Tidak ada blocker untuk Phase 1.** Phase 1 (foundation, auth, RBAC, envelope, docs) tidak bergantung pada keputusan bisnis di atas.

Blocker bertahap:

| Fase | Keputusan yang dibutuhkan |
|---|---|
| Phase 1 | C-01, C-03, C-04 (dan kredensial Firebase untuk integrasi nyata) |
| Phase 2 | B-11, B-12, B-16 |
| Phase 3 | B-11, B-15 |
| Phase 4 | B-3, B-6, B-8, B-15, B-19 |
| Phase 5 | **B-1, B-2** |
| Phase 6 | B-10 |
| Phase 7 | **B-1, B-3, B-4, B-5, B-6, B-7, B-9, B-13** |
| Phase 9 | B-14, B-17 |

**Rekomendasi:** setujui dokumen ini + jawab **B-1, B-2, B-3** (paling berdampak) lebih awal agar Phase 4–7 mulus. Fase 1–3 dapat berjalan paralel dengan pengambilan keputusan tersebut.

---

## E. Pertanyaan Cepat untuk Anda (jawab singkat)

1. Model dana: escrow platform, split payment, atau hybrid? (B-1)
2. Provider payment: Midtrans / Xendit / keduanya? (B-2)
3. Komisi: berapa % default, dan apakah ongkir termasuk basis? (B-3, B-4)
4. Fee gateway & voucher ditanggung siapa? (B-5, B-6)
5. Hold settlement & auto-complete berapa hari? (B-7)
6. Timeout pembayaran berapa jam? (B-8)
7. Refund: penuh dulu atau perlu parsial? (B-9)
8. Shipping: agregator (RajaOngkir/Biteship) atau resi manual? (B-10)
9. Produk langsung tayang atau lewat moderasi? (B-11)
10. Satu user boleh punya berapa toko? (A-01)
