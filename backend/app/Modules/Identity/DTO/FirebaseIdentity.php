<?php

namespace App\Modules\Identity\DTO;

/**
 * Identitas pengguna hasil verifikasi Firebase ID Token.
 *
 * Nilai di sini berasal dari token yang SUDAH diverifikasi signature-nya.
 * Jangan pernah membangun objek ini dari input mentah klien.
 */
final readonly class FirebaseIdentity
{
    public function __construct(
        public string $uid,
        public ?string $email = null,
        public ?string $name = null,
        public bool $emailVerified = false,
        public ?string $picture = null,
        public ?string $signInProvider = null,
    ) {}
}
