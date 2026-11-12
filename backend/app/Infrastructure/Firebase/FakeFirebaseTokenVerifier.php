<?php

namespace App\Infrastructure\Firebase;

use App\Contracts\Auth\FirebaseTokenVerifier;
use App\Modules\Identity\DTO\FirebaseIdentity;

/**
 * Verifier tiruan untuk lokal & pengujian tanpa kredensial Firebase.
 *
 * Token yang diterima adalah JSON biasa (atau base64 dari JSON tersebut) dengan
 * klaim: uid/sub, email, name, email_verified, picture, exp, disabled.
 *
 * Contoh token (JSON):
 *   {"uid":"user-1","email":"a@b.test","name":"A","email_verified":true,"exp":1893456000}
 *
 * CATATAN KEAMANAN: hanya aktif bila FIREBASE_FAKE_VERIFIER=true dan TIDAK boleh
 * dipakai di lingkungan produksi.
 */
final class FakeFirebaseTokenVerifier implements FirebaseTokenVerifier
{
    public function verify(string $idToken): FirebaseIdentity
    {
        $claims = $this->decode($idToken);

        if ($claims === null) {
            throw FirebaseVerificationException::invalid();
        }

        if (($claims['disabled'] ?? false) === true) {
            throw FirebaseVerificationException::disabled();
        }

        if (isset($claims['exp']) && (int) $claims['exp'] < time()) {
            throw FirebaseVerificationException::expired();
        }

        $uid = $claims['uid'] ?? $claims['sub'] ?? $claims['user_id'] ?? null;

        if (! is_string($uid) || $uid === '') {
            throw FirebaseVerificationException::invalid('Token has no subject identifier.');
        }

        $signInProvider = $claims['firebase']['sign_in_provider'] ?? $claims['sign_in_provider'] ?? null;

        return new FirebaseIdentity(
            uid: $uid,
            email: isset($claims['email']) ? (string) $claims['email'] : null,
            name: isset($claims['name']) ? (string) $claims['name'] : null,
            emailVerified: (bool) ($claims['email_verified'] ?? false),
            picture: isset($claims['picture']) ? (string) $claims['picture'] : null,
            signInProvider: is_string($signInProvider) ? $signInProvider : null,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $token): ?array
    {
        $token = trim($token);

        if (str_starts_with($token, 'fake.')) {
            $token = substr($token, 5);
        }

        // Dukung base64url (dengan atau tanpa padding), lalu fallback ke JSON mentah.
        $normalised = strtr($token, '-_', '+/');
        $remainder = strlen($normalised) % 4;

        if ($remainder !== 0) {
            $normalised .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($normalised, true);

        if (is_string($decoded)) {
            $json = json_decode($decoded, true);

            if (is_array($json)) {
                return $json;
            }
        }

        $json = json_decode($token, true);

        return is_array($json) ? $json : null;
    }
}
