<?php

namespace App\Contracts\Auth;

use App\Modules\Identity\DTO\FirebaseIdentity;
use App\Support\Exceptions\ApiException;

/**
 * Kontrak verifikasi Firebase ID Token.
 *
 * Implementasi nyata memakai Firebase Admin SDK; tersedia juga implementasi
 * tiruan (fake) untuk pengembangan lokal dan pengujian tanpa kredensial.
 */
interface FirebaseTokenVerifier
{
    /**
     * Memverifikasi ID token dan mengembalikan identitas terverifikasi.
     *
     * @throws ApiException bila token tidak valid,
     *                      kedaluwarsa, atau akun dinonaktifkan.
     */
    public function verify(string $idToken): FirebaseIdentity;
}
