<?php

namespace App\Modules\Seller\Http\Middleware;

use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan user terautentikasi memiliki toko, lalu menaruh seller pada
 * request attributes agar controller tidak perlu query ulang.
 */
class EnsureSellerExists
{
    public const ATTRIBUTE = 'seller';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $seller = $user?->seller()->first();

        if (! $seller instanceof Seller) {
            throw new ApiException(
                'Anda belum memiliki toko.',
                403,
                ErrorCode::SellerNotFound,
            );
        }

        $user?->setRelation('seller', $seller);
        $request->attributes->set(self::ATTRIBUTE, $seller);

        return $next($request);
    }
}
