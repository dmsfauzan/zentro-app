<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Modules\Notification\Http\Resources\NotificationResource;
use App\Modules\Notification\Models\Notification;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $query = Notification::query()
            ->where('user_id', $user->getKey())
            ->when($request->boolean('unread'), fn ($builder) => $builder->whereNull('read_at'))
            ->latest('created_at');

        $notifications = $query->paginate($perPage);

        $unread = Notification::query()
            ->where('user_id', $user->getKey())
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success([
            'items' => NotificationResource::collection($notifications->getCollection())->toArray($request),
            'unread_count' => $unread,
            'pagination' => [
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'has_more' => $notifications->hasMorePages(),
            ],
        ], 'Notifikasi berhasil diambil.');
    }

    public function show(Request $request, Notification $notification): JsonResponse
    {
        $this->assertOwner($request, $notification);

        return ApiResponse::success(
            (new NotificationResource($notification))->resolve(),
            'Detail notifikasi berhasil diambil.',
        );
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $this->assertOwner($request, $notification);

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return ApiResponse::success(
            (new NotificationResource($notification))->resolve(),
            'Notifikasi ditandai sudah dibaca.',
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(null, 'Semua notifikasi ditandai sudah dibaca.');
    }

    private function assertOwner(Request $request, Notification $notification): void
    {
        if ((int) $notification->user_id !== (int) $request->user()->getKey()) {
            throw ApiException::forbidden('Notifikasi ini bukan milikmu.');
        }
    }
}
