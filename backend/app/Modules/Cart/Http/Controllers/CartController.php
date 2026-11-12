<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Modules\Cart\Actions\AddCartItemAction;
use App\Modules\Cart\Actions\UpdateCartItemAction;
use App\Modules\Cart\Http\Requests\AddCartItemRequest;
use App\Modules\Cart\Http\Requests\UpdateCartItemRequest;
use App\Modules\Cart\Http\Resources\CartItemResource;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController
{
    use AuthorizesRequests;

    /**
     * Relasi yang dibutuhkan agar harga & ketersediaan selalu segar.
     *
     * @var list<string>
     */
    private const RELATIONS = [
        'variant.product.seller',
        'variant.product.primaryImage',
        'variant.inventory',
    ];

    public function __construct(
        private readonly AddCartItemAction $add,
        private readonly UpdateCartItemAction $update,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cart = Cart::forUser($request->user());

        $items = $cart->items()->with(self::RELATIONS)->orderBy('id')->get();

        $data = CartItemResource::collection($items)->toArray($request);
        $collection = collect($data);

        return ApiResponse::success([
            'items' => $data,
            'summary' => [
                'subtotal' => $collection->sum('line_total'),
                'item_count' => $collection->sum('quantity'),
                'seller_count' => $collection->pluck('seller.id')->filter()->unique()->count(),
                'has_unavailable' => $collection->contains(fn (array $item): bool => $item['is_available'] === false),
            ],
        ], 'Keranjang berhasil diambil.');
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        $item = $this->add->execute(
            $request->user(),
            (int) $request->validated('variant_id'),
            (int) $request->validated('quantity'),
        );

        return ApiResponse::created(
            (new CartItemResource($this->fresh($item)))->resolve(),
            'Item berhasil ditambahkan ke keranjang.',
        );
    }

    public function update(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        $this->authorize('update', $item);

        $updated = $this->update->execute($item, (int) $request->validated('quantity'));

        return ApiResponse::success(
            (new CartItemResource($this->fresh($updated)))->resolve(),
            'Keranjang diperbarui.',
        );
    }

    public function destroy(CartItem $item): JsonResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return ApiResponse::success(null, 'Item dihapus dari keranjang.');
    }

    private function fresh(CartItem $item): CartItem
    {
        return $item->fresh(self::RELATIONS);
    }
}
