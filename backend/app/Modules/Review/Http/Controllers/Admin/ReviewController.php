<?php

namespace App\Modules\Review\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Review\Http\Requests\ModerateReviewRequest;
use App\Modules\Review\Http\Resources\AdminReviewResource;
use App\Modules\Review\Models\Review;
use App\Modules\Review\Support\ReviewStats;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $reviews = Review::query()
            ->with(['product:id,name,slug', 'seller:id,store_name', 'user:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('rating'), fn ($query) => $query->where('rating', (int) $request->query('rating')))
            ->when($request->filled('seller_id'), fn ($query) => $query->where('seller_id', (int) $request->query('seller_id')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $reviews,
            fn (Review $review): array => (new AdminReviewResource($review))->resolve(),
            'Daftar ulasan berhasil diambil.',
        );
    }

    public function update(ModerateReviewRequest $request, Review $review): JsonResponse
    {
        $status = ReviewStatus::from((string) $request->validated('status'));

        $review->status = $status;
        $review->published_at = $status === ReviewStatus::Published
            ? ($review->published_at ?? now())
            : null;
        $review->save();

        ReviewStats::recomputeProduct((int) $review->product_id);
        ReviewStats::recomputeSeller((int) $review->seller_id);

        AuditLog::record(
            'review.moderated',
            'review',
            (int) $review->getKey(),
            null,
            ['status' => $status->value],
            $request->user()?->getKey(),
        );

        return ApiResponse::success(
            (new AdminReviewResource($review->load(['product:id,name,slug', 'seller:id,store_name', 'user:id,name'])))->resolve(),
            'Status ulasan diperbarui.',
        );
    }
}
