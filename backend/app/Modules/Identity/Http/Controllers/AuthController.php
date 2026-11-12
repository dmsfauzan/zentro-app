<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Contracts\Auth\FirebaseTokenVerifier;
use App\Modules\Identity\Actions\SyncFirebaseUserAction;
use App\Modules\Identity\Http\Requests\FirebaseLoginRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController
{
    public function __construct(
        private readonly FirebaseTokenVerifier $verifier,
        private readonly SyncFirebaseUserAction $sync,
    ) {}

    /**
     * Menukar Firebase ID Token dengan konteks user terlokalisasi.
     */
    public function firebase(FirebaseLoginRequest $request): JsonResponse
    {
        $identity = $this->verifier->verify($request->validated('id_token'));
        $user = $this->sync->execute($identity);

        Auth::setUser($user);

        return ApiResponse::success(
            (new UserResource($user))->resolve(),
            'Authenticated successfully',
        );
    }
}
