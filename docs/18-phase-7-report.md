# 18 — Phase 7 Implementation Report (Commission & Finance)

**Date:** 2026-11-11
**Status:** ✅ Selesai & terverifikasi (225 test hijau, konkurensi payout terbukti, ledger = saldo)
**Scope:** Komisi platform, saldo seller (pending/available/paid), settlement, payout, refund, rekonsiliasi.

---

## 1. Objective

- Setiap sub-order punya satu komisi (snapshot) saat pembayaran sukses.
- Dana seller tidak cair sebelum `completed` + masa hold (bucket `pending`).
- Payout sekali, idempoten, tervalidasi saldo, diaudit.
- Refund memindahkan dana dengan benar + reverse komisi, tanpa menghapus ledger.
- Saldo wallet selalu = SUM ledger per bucket.

## 2. Keputusan (ditetapkan 2026-11-11)

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | **B-5** fee gateway | **Platform** menanggung; tidak ada komponen biaya di ledger seller |
| 2 | **B-9** refund | **Penuh per sub-order**, jalur admin dulu; pasca-payout → `available` negatif (utang) |
| 3 | **B-13** dual control | **Nonaktif** default (`PAYOUT_DUAL_CONTROL_THRESHOLD=0`); 1 admin + audit |
| 4 | Ongkir | Milik seller (`seller_net = grand_total − commission`), sesuai docs/04 |
| 5 | Rekening bank | Tabel **`seller_bank_accounts`** (deviasi terdokumentasi; 1 utama/seller) |
| 6 | Pembulatan | Ke bawah + `rounding_adjustment` (A-18) |

## 3. Architecture Decision

| Aspek | Keputusan |
|---|---|
| Modul | `app/Modules/Finance` (baru) + integrasi dari `MarkPaymentPaidAction` |
| Penulis ledger | Hanya `WalletLedgerService`; transfer antar bucket = 2 baris |
| Immutability | Model `WalletTransaction` menolak update/delete + CHECK `balance_after = balance_before + amount` |
| Komisi | Snapshot `base_amount`/`rate_bp` di `commissions`; kolom snapshot di `seller_orders` |
| Rule | seller > kategori (satu kategori) > global; fallback `COMMISSION_DEFAULT_RATE_BP` |
| Settlement | `SettlementReleaseJob` (harian) → `pending → available` + komisi `earned` |
| Payout | Guard satu payout aktif + lock wallet saat `mark_paid`; idempotency key |
| Refund | Gateway call di luar transaksi; debit bucket + komisi `reversed` + status payment/order |
| Rekonsiliasi | `ReconcileWalletsJob` (harian) — laporkan, bukan auto-fix |
| Disbursement | Manual (admin `mark_paid`) |

## 4. Files Created / Modified

### Dibuat
```
app/Enums/{CommissionStatus,PayoutStatus,WalletTransactionType,BalanceBucket}.php
app/Modules/Finance/Models/{CommissionRule,Commission,SellerWallet,WalletTransaction,SellerPayout,PayoutItem,SellerBankAccount}.php
app/Modules/Finance/Support/{CommissionCalculator,CommissionRuleResolver,WalletLedgerService,PayoutNumberGenerator,RefundNumberGenerator}.php
app/Modules/Finance/Support/Dto/CommissionResult.php
app/Modules/Finance/Actions/{CreateCommissionAction,ReleaseSettlementAction,RequestPayoutAction,CancelPayoutAction,ProcessPayoutAction,CreateRefundAction,ProcessRefundAction,ReconcileWalletAction}.php
app/Modules/Finance/Http/Controllers/Seller/{SellerWalletController,SellerBankAccountController,SellerPayoutController}.php
app/Modules/Finance/Http/Controllers/Admin/{AdminCommissionController,AdminPayoutController,AdminRefundController}.php
app/Modules/Finance/Http/Requests/{RequestPayoutRequest,BankAccountRequest,ProcessPayoutRequest,CreateRefundRequest,RejectRefundRequest}.php
app/Modules/Finance/Http/Resources/{WalletResource,WalletTransactionResource,PayoutResource,CommissionResource,RefundResource}.php
app/Modules/Finance/Policies/SellerPayoutPolicy.php
app/Modules/Finance/Routes/api.php
app/Jobs/{SettlementReleaseJob,ReconcileWalletsJob}.php
database/seeders/CommissionRuleSeeder.php
database/migrations/2026_11_11_1500{01..08}_*.php
tests/Feature/Finance/* (5 file) · tests/Unit/CommissionCalculatorTest.php
```

### Dimodifikasi
```
app/Modules/Payment/Actions/MarkPaymentPaidAction.php (+CreateCommissionAction)
app/Modules/Payment/Models/Refund.php (+rejection_reason)
app/Modules/Order/Support/OrderStatusAggregator.php (+refunded, guard campuran terminal)
app/Modules/Order/Models/SellerOrder.php (+commission, +refunds)
app/Modules/Seller/Models/Seller.php (+wallet, +bankAccounts, +payouts)
app/Modules/Seller/Http/Controllers/SellerDashboardController.php (+wallet nyata)
app/Providers/AppServiceProvider.php (+limiter finance-action)
app/Support/Exceptions/ErrorCode.php (+8 kode)
config/zentro.php, .env.example, routes/api.php, routes/console.php, database/seeders/DatabaseSeeder.php, tests/Pest.php
```

## 5. Database Changes

7 tabel baru + 1 kolom — total tabel dev: **51**.

| Tabel | Catatan |
|---|---|
| `commission_rules` | CHECK rate 0–10000 + scope/target konsisten |
| `commissions` | UNIQUE `seller_order_id`, index `(seller_id,status,created_at)` |
| `seller_wallets` | UNIQUE `seller_id`, saldo BIGINT signed |
| `seller_bank_accounts` | deviasi; `account_number` encrypted |
| `seller_payouts` | UNIQUE `payout_number`, snapshot rekening, CHECK amount > 0 |
| `wallet_transactions` | append-only; index seller/bucket/reference; CHECK saldo & bucket; FK `refund_id` |
| `payout_items` | tautan payout ↔ ledger/sub-order |
| `refunds.rejection_reason` | kolom baru |

## 6. Implementation

| Method | Endpoint | Catatan |
|---|---|---|
| GET | `/api/v1/seller/wallet` | Saldo 3 bucket |
| GET | `/api/v1/seller/wallet/transactions` | Filter `bucket`/`type` |
| GET/POST | `/api/v1/seller/bank-accounts` | Nomor dimask di response |
| POST | `/api/v1/seller/payouts` | **Idempotency-Key**; guard saldo + satu aktif; `202` |
| GET | `/api/v1/seller/payouts` | Filter status |
| POST | `/api/v1/seller/payouts/{number}/cancel` | Hanya `requested` |
| GET | `/api/v1/admin/commissions` | Filter seller/status/period |
| GET | `/api/v1/admin/payouts` | Antrian admin |
| POST | `/api/v1/admin/payouts/{id}/process` | `approve|reject|mark_paid` + audit |
| GET/POST | `/api/v1/admin/refunds` | Ajukan/list refund |
| POST | `/api/v1/admin/refunds/{id}/approve` | Gateway + efek dana + status |
| POST | `/api/v1/admin/refunds/{id}/reject` | Wajib alasan |

**Alur uang:** paid → `sale_pending` (+pending) & komisi `pending` → completed + hold → `settlement_release` (pending→available, 2 baris) & komisi `earned` → payout `mark_paid` (`payout` available→paid, 2 baris) → refund → `refund_debit` (pending/available) & komisi `reversed`.

## 7. Security Considerations

| Kontrol | Status |
|---|---|
| Akses wallet/payout hanya seller pemilik | ✅ middleware + Policy + test |
| Endpoint admin butuh permission (`manage_*`) | ✅ test 403 |
| Payout ganda | ✅ idempotency + guard satu aktif + lock + test konkurensi |
| Ledger immutable | ✅ model + test |
| Nomor rekening | ✅ `encrypted` + dimask |
| Refund salah nominal | ✅ `422 REFUND_AMOUNT_INVALID` |
| Saldo tidak cukup | ✅ `422 INSUFFICIENT_BALANCE` (kecuali refund → utang) |
| Audit aksi admin payout | ✅ `AuditLog::record` |
| Rekonsiliasi ledger | ✅ job harian + test |

## 8. Testing Results

```
Backend : 225 passed (1071 assertions) — Pest 4.7.8   [36 test baru Phase 7]
Pint    : PASS

Uji konkurensi NYATA (dua proses PHP `mark_paid` bersamaan pada payout yang sama):
  Worker A: paid     Worker B: paid
  Akhir: payout_ledger_rows=2, payout_items=1,
         wallet pending/available/paid = 0/0/110000 = ledger_sum ✅ (satu transfer)
```

Cakupan test Phase 7:
- Unit: rumus komisi (floor + `rounding_adjustment`), batas rate/base.
- Komisi: snapshot saat paid, tidak dobel, ongkir dikecualikan, resolusi seller>kategori>global.
- Settlement: release, belum hold → tidak, idempoten, sub-order belum completed → tidak.
- Wallet: endpoint, filter, isolasi seller, ledger immutable, deteksi mismatch.
- Payout: tanpa rekening, saldo kurang, sukses + guard satu aktif, idempotency replay, approve+mark_paid + ledger + payout_items, saldo terkuras → 422, reject, cancel, permission.
- Refund: create + permission, nominal > total, debit pending/available, negatif pasca-payout, payment `partially_refunded`, reject wajib alasan, refund aktif ganda ditolak.

## 9. How to Run

```powershell
cd C:\laragon\www\Zentro\backend
php artisan migrate
php artisan db:seed --class=CommissionRuleSeeder
php artisan test
php artisan serve
php artisan schedule:work   # settlement & rekonsiliasi
```

Contoh alur:
```http
GET  /api/v1/seller/wallet
POST /api/v1/seller/bank-accounts   { "bank_name":"BCA","account_number":"123","account_name":"Toko" }
POST /api/v1/seller/payouts         Idempotency-Key: ...  { "amount":100000,"bank_account_id":1 }
POST /api/v1/admin/payouts/1/process { "action":"approve" }
POST /api/v1/admin/payouts/1/process { "action":"mark_paid","provider_reference":"TRX1" }
POST /api/v1/admin/refunds           { "order_number":"ZTR-...","seller_order_id":1,"amount":115000,"reason":"Rusak" }
POST /api/v1/admin/refunds/1/approve
```

## 10. Known Limitations

1. Disbursement manual (belum ada provider payout/bank API).
2. Refund hanya penuh per sub-order & hanya jalur admin (permintaan buyer/seller belum).
3. Dual control payout belum aktif (struktur siap).
4. Payout tidak memakai langkah `processing` eksplisit (approve → mark_paid).
5. Rule kategori hanya berlaku bila seluruh item sub-order satu kategori.
6. Fee gateway tidak dibukukan (B-5 = platform).
7. Belum ada laporan admin/rekap komisi & pajak/faktur.
8. Notifikasi & UI frontend belum.

## 11. Next Phase

**Phase 8 — Frontend**: fondasi Next.js (API client, TanStack Query, Firebase client, guard route) + area Customer, Seller, Admin; E2E smoke (Playwright).
