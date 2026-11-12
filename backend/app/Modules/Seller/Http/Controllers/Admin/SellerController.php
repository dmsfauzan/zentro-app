<?php

namespace App\Modules\Seller\Http\Controllers\Admin;

use App\Modules\Identity\Models\User;
use App\Modules\Seller\Actions\ApproveSellerAction;
use App\Modules\Seller\Actions\ReactivateSellerAction;
use App\Modules\Seller\Actions\RejectSellerAction;
use App\Modules\Seller\Actions\SuspendSellerAction;
use App\Modules\Seller\Http\Requests\RejectSellerRequest;
use App\Modules\Seller\Http\Requests\SuspendSellerRequest;
use App\Modules\Seller\Http\Resources\SellerResource;
use App\Modules\Seller\Models\Seller;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ApproveSellerAction $approve,
        private readonly RejectSellerAction $reject,
        private readonly SuspendSellerAction $suspend,
        private readonly ReactivateSellerAction $reactivate,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Seller::class);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $sellers = Seller::query()
            ->with(['user', 'addresses', 'documents'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->query('q').'%';
                $query->where(fn ($inner) => $inner
                    ->where('store_name', 'like', $term)
                    ->orWhere('slug', 'like', $term));
            })
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated(
            $sellers,
            fn (Seller $seller): array => (new SellerResource($seller))->resolve(),
            'Daftar penjual berhasil diambil.',
        );
    }

    public function approve(Request $request, Seller $seller): JsonResponse
    {
        $this->authorize('approve', $seller);

        $updated = $this->approve->execute($this->admin($request), $seller);

        return ApiResponse::success(
            (new SellerResource($updated))->resolve(),
            'Penjual berhasil disetujui.',
        );
    }

    public function reject(RejectSellerRequest $request, Seller $seller): JsonResponse
    {
        $this->authorize('reject', $seller);

        $updated = $this->reject->execute($this->admin($request), $seller, $request->validated('reason'));

        return ApiResponse::success(
            (new SellerResource($updated))->resolve(),
            'Penjual ditolak.',
        );
    }

    public function suspend(SuspendSellerRequest $request, Seller $seller): JsonResponse
    {
        $this->authorize('suspend', $seller);

        $updated = $this->suspend->execute($this->admin($request), $seller, $request->validated('reason'));

        return ApiResponse::success(
            (new SellerResource($updated))->resolve(),
            'Penjual ditangguhkan.',
        );
    }

    public function reactivate(Request $request, Seller $seller): JsonResponse
    {
        $this->authorize('reactivate', $seller);

        $updated = $this->reactivate->execute($this->admin($request), $seller);

        return ApiResponse::success(
            (new SellerResource($updated))->resolve(),
            'Penjual diaktifkan kembali.',
        );
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
