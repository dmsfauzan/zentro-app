<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\Http\Resources\AuditLogResource;
use App\Modules\Platform\Models\AuditLog;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $logs = AuditLog::query()
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', '%'.$request->query('action').'%'))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->query('entity_type')))
            ->when($request->filled('actor_id'), fn ($query) => $query->where('actor_id', (int) $request->query('actor_id')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $logs,
            fn (AuditLog $log): array => (new AuditLogResource($log))->resolve(),
            'Audit log berhasil diambil.',
        );
    }
}
