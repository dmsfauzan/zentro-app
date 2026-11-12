<?php

namespace App\Modules\Payment\Http\Controllers\Admin;

use App\Modules\Payment\Http\Resources\PaymentResource;
use App\Modules\Payment\Models\Payment;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $payments = Payment::query()
            ->with('order')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('provider'), fn ($query) => $query->where('provider', $request->query('provider')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $payments,
            fn (Payment $payment): array => (new PaymentResource($payment))->resolve(),
            'Daftar pembayaran berhasil diambil.',
        );
    }
}
