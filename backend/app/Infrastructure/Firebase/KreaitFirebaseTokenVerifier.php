<?php

namespace App\Infrastructure\Firebase;

use App\Contracts\Auth\FirebaseTokenVerifier;
use App\Modules\Identity\DTO\FirebaseIdentity;
use DateTimeImmutable;
use DateTimeInterface;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Lcobucci\JWT\Token\DataSet;
use Throwable;

/**
 * Verifikasi Firebase ID Token memakai Firebase Admin SDK (kreait/laravel-firebase).
 *
 * Verifikasi mencakup: signature, issuer, audience, expiration, dan status revoked.
 *
 * Pengecekan revokasi dilakukan manual (bukan via `checkIfRevoked`) karena kreait
 * mengurangi `auth_time` dengan leeway saat membandingkannya dengan
 * `tokensValidAfterTime`, sehingga token yang baru diterbitkan selalu dianggap
 * revoked. Leeway hanya dipakai untuk klaim waktu (iat/exp/auth_time).
 */
final class KreaitFirebaseTokenVerifier implements FirebaseTokenVerifier
{
    public function __construct(
        private readonly FirebaseAuth $auth,
        private readonly int $leewayInSeconds = 0,
    ) {}

    public function verify(string $idToken): FirebaseIdentity
    {
        try {
            $token = $this->auth->verifyIdToken(
                $idToken,
                checkIfRevoked: false,
                leewayInSeconds: max(0, $this->leewayInSeconds),
            );
        } catch (FailedToVerifyToken $e) {
            if (stripos($e->getMessage(), 'expired') !== false) {
                throw FirebaseVerificationException::expired($e);
            }

            throw FirebaseVerificationException::invalid('Token verification failed.', $e);
        }

        $claims = $token->claims();

        $uid = (string) $claims->get('sub');

        if ($uid === '') {
            throw FirebaseVerificationException::invalid('Token has no subject identifier.');
        }

        if ($this->hasBeenRevoked($uid, $claims)) {
            throw FirebaseVerificationException::revoked();
        }

        $firebase = $claims->has('firebase') ? $claims->get('firebase') : [];

        return new FirebaseIdentity(
            uid: $uid,
            email: $claims->has('email') ? (string) $claims->get('email') : null,
            name: $claims->has('name') ? (string) $claims->get('name') : null,
            emailVerified: (bool) ($claims->has('email_verified') ? $claims->get('email_verified') : false),
            picture: $claims->has('picture') ? (string) $claims->get('picture') : null,
            signInProvider: is_array($firebase) && isset($firebase['sign_in_provider'])
                ? (string) $firebase['sign_in_provider']
                : null,
        );
    }

    /**
     * Token dianggap revoked bila `auth_time`-nya lebih awal dari `tokensValidAfterTime`
     * milik pengguna. Toleransi jam tidak diterapkan di sini agar token baru tetap sah.
     */
    private function hasBeenRevoked(string $uid, DataSet $claims): bool
    {
        try {
            $validSince = $this->auth->getUser($uid)->tokensValidAfterTime;
        } catch (Throwable $e) {
            throw FirebaseVerificationException::invalid(
                'Unable to determine the token revocation status.',
                $e,
            );
        }

        if (! $validSince instanceof DateTimeImmutable) {
            // Belum ada sesi yang pernah dicabut haknya.
            return false;
        }

        $authTime = $claims->get('auth_time');

        if ($authTime instanceof DateTimeInterface) {
            $authenticatedAt = $authTime->getTimestamp();
        } elseif (is_numeric($authTime)) {
            $authenticatedAt = (int) $authTime;
        } else {
            return false;
        }

        return $authenticatedAt < $validSince->getTimestamp();
    }
}
