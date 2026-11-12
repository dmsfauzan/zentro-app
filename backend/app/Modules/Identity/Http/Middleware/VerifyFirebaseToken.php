<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Contracts\Auth\FirebaseTokenVerifier;
use App\Modules\Identity\Actions\SyncFirebaseUserAction;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memverifikasi Firebase ID Token dari header Authorization, menyinkronkan user,
 * lalu menjadikannya user terautentikasi untuk request ini.
 *
 * UID dan role TIDAK pernah diambil dari body/query request.
 */
class VerifyFirebaseToken
{
    public function __construct(
        private readonly FirebaseTokenVerifier $verifier,
        private readonly SyncFirebaseUserAction $sync,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            throw new ApiException(
                'Authentication token was not provided.',
                401,
                ErrorCode::AuthTokenMissing,
            );
        }

        $identity = $this->verifier->verify($token);
        $user = $this->sync->execute($identity);

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
