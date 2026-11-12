<?php

namespace App\Modules\Promotion\Http\Controllers\Admin;

use App\Enums\VoucherFundedBy;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Modules\Promotion\Http\Requests\VoucherRequest;
use App\Modules\Promotion\Http\Resources\VoucherResource;
use App\Modules\Promotion\Models\Voucher;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VoucherController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $vouchers = Voucher::query()
            ->when($request->filled('scope'), fn ($query) => $query->where('scope', $request->query('scope')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->query('q').'%';
                $query->where(fn ($inner) => $inner
                    ->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term));
            })
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $vouchers,
            fn (Voucher $voucher): array => (new VoucherResource($voucher))->resolve(),
            'Daftar voucher berhasil diambil.',
        );
    }

    public function store(VoucherRequest $request): JsonResponse
    {
        $voucher = DB::transaction(fn (): Voucher => Voucher::query()->create($this->payload($request)));

        return ApiResponse::created(
            (new VoucherResource($voucher))->resolve(),
            'Voucher dibuat.',
        );
    }

    public function update(VoucherRequest $request, Voucher $voucher): JsonResponse
    {
        DB::transaction(function () use ($request, $voucher): void {
            $voucher->fill($this->payload($request, $voucher))->save();
        });

        return ApiResponse::success(
            (new VoucherResource($voucher->refresh()))->resolve(),
            'Voucher diperbarui.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(VoucherRequest $request, ?Voucher $existing = null): array
    {
        $data = $request->validated();
        $payload = $data;

        $scopeValue = $data['scope'] ?? ($existing?->scope?->value ?? 'platform');
        $scope = VoucherScope::tryFrom((string) $scopeValue);

        if (! $scope instanceof VoucherScope) {
            throw $this->invalid('scope', 'Scope voucher tidak valid.');
        }

        $type = VoucherType::tryFrom((string) ($data['type'] ?? ($existing?->type?->value ?? 'fixed')));

        if (! $type instanceof VoucherType) {
            throw $this->invalid('type', 'Tipe voucher tidak valid.');
        }

        $value = (int) ($data['value'] ?? $existing?->value ?? 0);

        if ($type === VoucherType::Percent && $value > 10000) {
            throw $this->invalid('value', 'Nilai persen maksimal 10000 basis point (100%).');
        }

        if ($scope === VoucherScope::Seller && empty($data['seller_id']) && ! $existing instanceof Voucher) {
            throw $this->invalid('seller_id', 'Wajib diisi untuk voucher toko.');
        }

        $payload['type'] = $type;
        $payload['scope'] = $scope;
        $payload['funded_by'] = $scope === VoucherScope::Seller
            ? VoucherFundedBy::Seller
            : VoucherFundedBy::Platform;

        if ($scope === VoucherScope::Platform) {
            $payload['seller_id'] = null;
        }

        $startsAt = isset($data['starts_at'])
            ? Carbon::parse($data['starts_at'])
            : $existing?->starts_at;

        $expiresAt = array_key_exists('expires_at', $data)
            ? ($data['expires_at'] !== null ? Carbon::parse($data['expires_at']) : null)
            : $existing?->expires_at;

        if ($startsAt === null) {
            throw $this->invalid('starts_at', 'Tanggal mulai wajib diisi.');
        }

        if ($expiresAt !== null && ! $expiresAt->greaterThan($startsAt)) {
            throw $this->invalid('expires_at', 'Tanggal berakhir harus setelah tanggal mulai.');
        }

        $payload['starts_at'] = $startsAt;
        $payload['expires_at'] = $expiresAt;

        return $payload;
    }

    private function invalid(string $field, string $message): ApiException
    {
        return new ApiException($message, 422, ErrorCode::ValidationFailed, [
            $field => [$message],
        ]);
    }
}
