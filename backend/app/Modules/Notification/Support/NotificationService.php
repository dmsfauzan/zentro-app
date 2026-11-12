<?php

namespace App\Modules\Notification\Support;

use App\Modules\Notification\Models\Notification;

/**
 * Pembuat notifikasi in-app. Kegagalan notifikasi tidak boleh menggagalkan
 * transaksi bisnis (dipanggil di dalam transaksi yang sudah ada).
 */
final class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): Notification {
        return Notification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data !== [] ? $data : null,
        ]);
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $data
     */
    public function sendMany(
        array $userIds,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        foreach (array_unique($userIds) as $userId) {
            if ($userId > 0) {
                $this->send($userId, $type, $title, $body, $data);
            }
        }
    }
}
