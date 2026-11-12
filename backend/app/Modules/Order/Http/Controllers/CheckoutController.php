<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Cart\Models\Cart;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserAddress;
use App\Modules\Order\Actions\PlaceCheckoutAction;
use App\Modules\Order\Http\Requests\CheckoutPreviewRequest;
use App\Modules\Order\Http\Requests\CheckoutRequest;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Support\CheckoutCalculator;
use App\Modules\Payment\Actions\InitiatePaymentAction;
use App\Modules\Payment\Models\Payment;
use App\Modules\Promotion\Support\VoucherService;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class CheckoutController
{
    public function __construct(
        private readonly CheckoutCalculator $calculator,
        private readonly PlaceCheckoutAction $place,
        private readonly InitiatePaymentAction $payments,
        private readonly VoucherService $vouchers,
    ) {}

    public function preview(CheckoutPreviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $items = $this->resolveItems($request->input('items'), $user);
        $address = $this->resolveAddress($user, $request->input('address_id'), required: false);

        $quantityMap = $this->calculator->normaliseItems($items);
        $variants = $this->calculator->loadVariants(array_keys($quantityMap));

        $computed = $this->calculator->groupBySeller(
            $variants,
            $quantityMap,
            $this->destination($address),
            (array) $request->input('shipping_selections', []),
        );

        $voucherCode = $request->input('voucher_code');
        $voucherResult = $this->vouchers->apply(
            $computed,
            $user,
            is_string($voucherCode) ? $voucherCode : null,
        );
        $computed = $voucherResult['computed'];

        return ApiResponse::success([
            'seller_groups' => array_map(fn (array $group): array => [
                'seller' => [
                    'id' => $group['seller']->getKey(),
                    'store_name' => $group['seller']->store_name,
                    'slug' => $group['seller']->slug,
                ],
                'items' => array_map(fn (array $line): array => [
                    'variant_id' => (int) $line['variant']->getKey(),
                    'product_name' => $line['product']->name,
                    'variant_name' => $line['variant']->variant_name,
                    'sku' => $line['variant']->sku,
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ], $group['items']),
                'subtotal' => $group['subtotal'],
                'discount' => $group['discount'],
                'shipping_options' => $group['shipping_options'],
                'selected_service_code' => $group['selected_shipping']['code'],
                'shipping_cost' => $group['shipping_cost'],
                'total' => $group['grand_total'],
            ], $computed['groups']),
            'summary' => $computed['summary'],
            'voucher' => $computed['voucher'] ?? ['code' => null, 'discount' => 0, 'available' => false],
            'available_vouchers' => $this->vouchers->availableFor($computed, $user),
            'available_methods' => $this->payments->availableMethods(),
            'expires_at' => $this->calculator->expiresAt()->toIso8601String(),
        ], 'Pratinjau checkout berhasil dihitung.');
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            throw new ApiException(
                'Header Idempotency-Key wajib diisi untuk checkout.',
                400,
                ErrorCode::IdempotencyKeyRequired,
            );
        }

        $address = $this->resolveAddress($user, $request->input('address_id'), required: true);

        $result = $this->place->execute(
            $user,
            trim($key),
            (array) $request->input('items'),
            $address,
            $this->normaliseSelections($request->input('shipping_selections')),
            $request->input('voucher_code'),
        );

        // Pembayaran dibuat SETELAH transaksi checkout commit (tanpa jaringan
        // di dalam transaksi DB). Replay idempotency memakai payment yang sudah
        // ada tanpa memanggil provider lagi.
        $result['payload']['payment'] = $this->attachPayment($user, (string) $result['payload']['order_number']);

        $response = ApiResponse::success(
            $result['payload'],
            $result['replayed'] ? 'Order dikembalikan dari idempotency.' : 'Checkout berhasil.',
            $result['status'],
        );

        if ($result['replayed']) {
            $response->headers->set('X-Idempotent-Replayed', 'true');
        }

        return $response;
    }

    /**
     * Menyiapkan pembayaran untuk order hasil checkout dan mengembalikan
     * instruksi bayar (token/redirect URL) bila tersedia.
     *
     * @return array<string, mixed>|null
     */
    private function attachPayment(User $user, string $orderNumber): ?array
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order instanceof Order) {
            return null;
        }

        if ($order->isPendingPayment()) {
            $this->payments->execute($order, $user->getKey());
        }

        $payment = $order->payment()->first();

        if (! $payment instanceof Payment) {
            return null;
        }

        return $payment->clientInstructions() + ['available_methods' => $this->payments->availableMethods()];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(?array $items, User $user): array
    {
        if (is_array($items) && $items !== []) {
            return $items;
        }

        $cart = Cart::forUser($user);

        return $cart->items()
            ->get()
            ->map(fn ($item): array => [
                'variant_id' => (int) $item->variant_id,
                'quantity' => (int) $item->quantity,
            ])
            ->all();
    }

    private function resolveAddress(User $user, mixed $addressId, bool $required): ?UserAddress
    {
        if ($addressId === null || $addressId === '') {
            if ($required) {
                throw new ApiException(
                    'Alamat pengiriman wajib diisi.',
                    422,
                    ErrorCode::ValidationFailed,
                    ['address_id' => ['Alamat pengiriman wajib diisi.']],
                );
            }

            return null;
        }

        $address = UserAddress::query()
            ->whereKey((int) $addressId)
            ->where('user_id', $user->getKey())
            ->first();

        if (! $address instanceof UserAddress) {
            throw new ApiException(
                'Alamat pengiriman tidak ditemukan.',
                404,
                ErrorCode::NotFound,
            );
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    private function destination(?UserAddress $address): array
    {
        if (! $address instanceof UserAddress) {
            return ['city' => null, 'province' => null, 'postal_code' => null];
        }

        return [
            'city' => $address->city,
            'province' => $address->province,
            'postal_code' => $address->postal_code,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $selections
     * @return array<int, array<string, mixed>>
     */
    private function normaliseSelections(?array $selections): array
    {
        return is_array($selections) ? $selections : [];
    }
}
