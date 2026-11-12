<?php

namespace App\Support\Http;

use App\Support\Exceptions\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Membangun response envelope yang konsisten untuk seluruh endpoint /api/v1.
 *
 * Sukses: { success: true, message, data, meta? }
 * Error : { success: false, message, errors: { code?, field: [...] } }
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object) [],
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public static function error(
        string $message,
        array $errors = [],
        int $status = 400,
        ?ErrorCode $code = null,
    ): JsonResponse {
        if ($code instanceof ErrorCode) {
            $errors = ['code' => $code->value] + $errors;
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? (object) [] : $errors,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return self::success(null, $message, 200);
    }

    /**
     * Membungkus paginator menjadi data + meta.pagination yang konsisten.
     */
    public static function paginated(
        AbstractPaginator $paginator,
        ?callable $transform = null,
        string $message = 'Data retrieved successfully',
    ): JsonResponse {
        $items = $transform
            ? $paginator->getCollection()->map($transform)->all()
            : $paginator->items();

        return self::success($items, $message, 200, [
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => method_exists($paginator, 'total') ? $paginator->total() : $paginator->count(),
                'last_page' => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : 1,
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
