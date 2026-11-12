<?php

namespace App\Modules\Seller\Http\Middleware;

use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses ke endpoint operasional hanya untuk seller berstatus active.
 * Harus dijalankan setelah EnsureSellerExists.
 */
class EnsureSellerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $seller = $request->attributes->get(EnsureSellerExists::ATTRIBUTE);

        if (! $seller instanceof Seller && $request->user() !== null) {
            $seller = $request->user()->seller()->first();
        }

        if (! $seller instanceof Seller || ! $seller->isActive()) {
            throw new ApiException(
                'Toko Anda belum aktif. Selesaikan verifikasi terlebih dahulu.',
                403,
                ErrorCode::SellerNotActive,
            );
        }

        return $next($request);
    }
}
