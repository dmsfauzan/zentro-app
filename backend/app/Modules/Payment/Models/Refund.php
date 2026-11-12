<?php

namespace App\Modules\Payment\Models;

use App\Enums\RefundStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan refund.
 *
 * Phase 5 hanya menyiapkan struktur & guard transisi; eksekusi perpindahan dana
 * (ledger wallet, pembalik komisi) dilakukan pada Phase 7.
 */
class Refund extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'refund_number',
        'payment_id',
        'order_id',
        'seller_order_id',
        'amount',
        'product_amount',
        'shipping_amount',
        'seller_debit_amount',
        'commission_reversal_amount',
        'status',
        'reason',
        'rejection_reason',
        'provider_reference',
        'provider_refund_key',
        'provider_refunded_at',
        'requested_by',
        'approved_by',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'integer',
            'product_amount' => 'integer',
            'shipping_amount' => 'integer',
            'seller_debit_amount' => 'integer',
            'commission_reversal_amount' => 'integer',
            'provider_refunded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
