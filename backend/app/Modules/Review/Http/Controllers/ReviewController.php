<?php

namespace App\Modules\Review\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Order\Models\Order;
use App\Modules\Review\Actions\CreateReviewAction;
use App\Modules\Review\Http\Requests\CreateReviewRequest;
use App\Modules\Review\Http\Resources\ReviewResource;
use App\Modules\Review\Models\Review;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController
{
    public function __construct(
        private readonly CreateReviewAction $create,
    ) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        $product = Product::query()->where('slug', $slug)->first();

        if (! $product instanceof Product) {
            throw ApiException::notFound('Produk tidak ditemukan.');
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage === 0 ? 10 : $perPage, 50));

        $reviews = Review::query()
            ->where('product_id', $product->getKey())
            ->where('status', ReviewStatus::Published->value)
            ->with('user')
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $reviews,
            fn (Review $review): array => (new ReviewResource($review))->resolve(),
            'Ulasan produk berhasil diambil.',
        );
    }

    public function store(CreateReviewRequest $request, Order $order): JsonResponse
    {
        $review = $this->create->execute(
            $request->user(),
            $order,
            (int) $request->validated('order_item_id'),
            (int) $request->validated('rating'),
            $request->validated('comment'),
        );

        return ApiResponse::created(
            (new ReviewResource($review->load('user')))->resolve(),
            'Ulasan berhasil dibuat.',
        );
    }
}
