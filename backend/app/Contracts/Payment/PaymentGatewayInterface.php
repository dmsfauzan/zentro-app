<?php

namespace App\Contracts\Payment;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\Dto\PaymentInitiation;
use App\Modules\Payment\Support\Dto\PaymentStatusResult;
use App\Modules\Payment\Support\Dto\PaymentWebhookData;
use App\Modules\Payment\Support\Dto\RefundResult;

/**
 * Kontrak payment gateway.
 *
 * Implementasi aktif dipilih lewat config `zentro.payment.driver`. Seluruh
 * business logic (order, inventory, webhook processor) tidak boleh bergantung
 * pada provider tertentu.
 */
interface PaymentGatewayInterface
{
    /**
     * Nama provider yang dipakai pada URL webhook (`fake`, `midtrans`, ...).
     */
    public function name(): string;

    /**
     * Membuat transaksi di provider. Tidak boleh dipanggil di dalam transaksi DB.
     *
     * `$method` dipilih pembeli (mis. `qris`, `bca_va`). Gateway yang tidak
     * membutuhkan pemilihan metode boleh mengabaikannya.
     */
    public function createTransaction(Payment $payment, Order $order, ?string $method = null): PaymentInitiation;

    /**
     * Metode bayar yang bisa dipilih pembeli. Daftar kosong berarti gateway
     * membuat transaksi otomatis (mis. gateway palsu/Snap) tanpa pemilihan.
     *
     * @return array<int, array{key: string, label: string, group: string}>
     */
    public function selectableMethods(): array;

    /**
     * Mengambil status terkini dari provider (untuk job rekonsiliasi).
     */
    public function fetchStatus(Payment $payment): PaymentStatusResult;

    /**
     * Memverifikasi signature webhook.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload, ?string $signature): bool;

    /**
     * Normalisasi payload provider menjadi data internal.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): PaymentWebhookData;

    /**
     * Mengirim refund ke provider (dipakai pada Phase 7).
     *
     * `$refundKey` adalah idempotency key yang harus stabil antar retry: caller
     * menyimpannya sebelum memanggil, lalu memakai key yang sama bila diulang.
     * Bila null, provider boleh membuat key sendiri (tidak idempoten).
     */
    public function refund(Payment $payment, int $amount, ?string $refundKey = null): RefundResult;
}
