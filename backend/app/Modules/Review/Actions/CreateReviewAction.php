<?php

namespace App\Modules\Review\Actions;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Review\Models\Review;
use App\Modules\Review\Support\ReviewStats;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Membuat ulasan untuk satu item pesanan yang sudah selesai.
 */
final class CreateReviewAction
{
    public function execute(
        User $buyer,
        Order $order,
        int $orderItemId,
        int $rating,
        ?string $comment = null,
    ): Review {
        return DB::transaction(function () use ($buyer, $order, $orderItemId, $rating, $comment): Review {
            /** @var Order $locked */
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->buyer_id !== (int) $buyer->getKey()) {
                throw ApiException::forbidden('Pesanan ini bukan milikmu.');
            }

            if ($locked->status !== OrderStatus::Completed) {
                throw new ApiException(
                    'Ulasan hanya dapat dibuat setelah pesanan selesai.',
                    409,
                    ErrorCode::StateTransitionInvalid,
                );
            }

            /** @var OrderItem|null $item */
            $item = OrderItem::query()
                ->where('order_id', $locked->getKey())
                ->whereKey($orderItemId)
                ->first();

            if (! $item instanceof OrderItem) {
                throw ApiException::notFound('Item pesanan tidak ditemukan.');
            }

            $exists = Review::query()->where('order_item_id', $item->getKey())->exists();

            if ($exists) {
                throw new ApiException(
                    'Item ini sudah diulas.',
                    409,
                    ErrorCode::ValidationFailed,
                );
            }

            $review = Review::query()->create([
                'order_id' => $locked->getKey(),
                'order_item_id' => $item->getKey(),
                'product_id' => $item->product_id,
                'seller_id' => $item->seller_id,
                'user_id' => $buyer->getKey(),
                'rating' => max(1, min(5, $rating)),
                'comment' => $comment,
                'status' => ReviewStatus::Published,
                'published_at' => now(),
            ]);

            ReviewStats::recomputeProduct((int) $item->product_id);
            ReviewStats::recomputeSeller((int) $item->seller_id);

            return $review;
        });
    }
}
