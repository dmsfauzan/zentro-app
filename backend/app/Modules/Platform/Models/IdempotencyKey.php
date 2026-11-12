<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan idempotency untuk mutasi kritis (checkout, payout, dst.).
 */
class IdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'user_id',
        'scope',
        'request_hash',
        'response_snapshot',
        'response_status',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_snapshot' => 'array',
            'response_status' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
