<?php

namespace App\Modules\Platform\Models;

use App\Support\Http\Middleware\AssignRequestId;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit log bersifat append-only: created_at saja, tanpa updated_at,
 * dan tidak boleh diubah/dihapus.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'request_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(
        string $action,
        string $entityType,
        int $entityId,
        ?array $before = null,
        ?array $after = null,
        ?int $actorId = null,
    ): self {
        $request = request();

        return static::query()->create([
            'actor_id' => $actorId ?? $request?->user()?->getKey(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'request_id' => $request?->attributes->get(AssignRequestId::ATTRIBUTE),
        ]);
    }
}
