<?php

namespace App\Support\Idempotency;

use App\Modules\Platform\Models\IdempotencyKey;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Idempotency untuk mutasi kritis.
 *
 * Alur: cek key → bila ada & hash sama → replay; hash beda → 409.
 * Key disimpan di dalam transaksi bisnis agar atomik dengan efeknya.
 */
final class IdempotencyService
{
    /**
     * @return array{replay: bool, snapshot?: array<string, mixed>, status?: int}
     */
    public function begin(string $key, ?int $userId, string $scope, array $payload): array
    {
        $hash = hash('sha256', (string) json_encode($payload));

        $existing = IdempotencyKey::query()->where('key', $key)->first();

        if ($existing instanceof IdempotencyKey) {
            if (! hash_equals($existing->request_hash, $hash)) {
                throw new ApiException(
                    'Idempotency-Key sudah dipakai dengan payload berbeda.',
                    409,
                    ErrorCode::IdempotencyConflict,
                );
            }

            return [
                'replay' => true,
                'snapshot' => $existing->response_snapshot ?? [],
                'status' => $existing->response_status ?? 200,
            ];
        }

        return ['replay' => false, 'hash' => $hash];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function complete(string $key, ?int $userId, string $scope, string $hash, array $response, int $status): void
    {
        IdempotencyKey::query()->create([
            'key' => $key,
            'user_id' => $userId,
            'scope' => $scope,
            'request_hash' => $hash,
            'response_snapshot' => $response,
            'response_status' => $status,
            'expires_at' => now()->addHours((int) config('zentro.idempotency_ttl_hours', 24)),
        ]);
    }

    /**
     * Menjalankan callback dalam transaksi + retry deadlock.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function transactional(callable $callback, int $attempts = 3): mixed
    {
        return DB::transaction($callback, $attempts);
    }

    public function forget(string $key): void
    {
        IdempotencyKey::query()->where('key', $key)->delete();
    }

    /**
     * Dipakai pada catch duplicate-key saat dua request bersamaan memakai key sama.
     */
    public function replayOrFail(string $key, string $hash): array
    {
        $existing = IdempotencyKey::query()->where('key', $key)->first();

        if (! $existing instanceof IdempotencyKey) {
            throw new ApiException(
                'Terjadi konflik idempotency. Silakan coba lagi.',
                409,
                ErrorCode::IdempotencyConflict,
            );
        }

        if (! hash_equals($existing->request_hash, $hash)) {
            throw new ApiException(
                'Idempotency-Key sudah dipakai dengan payload berbeda.',
                409,
                ErrorCode::IdempotencyConflict,
            );
        }

        return [
            'replay' => true,
            'snapshot' => $existing->response_snapshot ?? [],
            'status' => $existing->response_status ?? 200,
        ];
    }

    public function isDuplicateKeyError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'idempotency_keys_key_unique')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed');
    }
}
