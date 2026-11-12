<?php

use App\Infrastructure\Firebase\FirebaseVerificationException;
use App\Infrastructure\Firebase\KreaitFirebaseTokenVerifier;
use App\Support\Exceptions\ErrorCode;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\UnencryptedToken;
use Mockery\MockInterface;

/**
 * @param  array<string, mixed>  $claims
 * @param  array<string, mixed>  $userData
 */
function fakeFirebaseAuth(array $claims, int $leeway, array $userData = []): FirebaseAuth&MockInterface
{
    $verifiedToken = mock(UnencryptedToken::class);
    $verifiedToken->shouldReceive('claims')->andReturn(new DataSet($claims, ''));

    $auth = mock(FirebaseAuth::class);
    $auth->shouldReceive('verifyIdToken')
        ->once()
        ->with('token-abc', false, $leeway)
        ->andReturn($verifiedToken);

    $auth->shouldReceive('getUser')
        ->andReturn(UserRecord::fromResponseData($userData + [
            'localId' => (string) $claims['sub'],
            'createdAt' => time(),
        ]));

    return $auth;
}

it('forwards the configured clock leeway to the firebase sdk', function (): void {
    $auth = fakeFirebaseAuth([
        'sub' => 'uid-1',
        'email' => 'a@b.test',
        'email_verified' => true,
        'auth_time' => time(),
        'firebase' => ['sign_in_provider' => 'password'],
    ], 60);

    $identity = (new KreaitFirebaseTokenVerifier($auth, 60))->verify('token-abc');

    expect($identity->uid)->toBe('uid-1')
        ->and($identity->email)->toBe('a@b.test')
        ->and($identity->emailVerified)->toBeTrue()
        ->and($identity->signInProvider)->toBe('password');
});

it('passes zero leeway when none is configured', function (): void {
    $auth = fakeFirebaseAuth([
        'sub' => 'uid-2',
        'auth_time' => time(),
        'firebase' => ['sign_in_provider' => 'google.com'],
    ], 0);

    $identity = (new KreaitFirebaseTokenVerifier($auth, 0))->verify('token-abc');

    expect($identity->uid)->toBe('uid-2')
        ->and($identity->signInProvider)->toBe('google.com');
});

it('accepts a freshly issued token even when a validSince equals its auth_time', function (): void {
    $authTime = time();

    $auth = fakeFirebaseAuth([
        'sub' => 'uid-3',
        'auth_time' => $authTime,
    ], 60, ['validSince' => $authTime]);

    $identity = (new KreaitFirebaseTokenVerifier($auth, 60))->verify('token-abc');

    expect($identity->uid)->toBe('uid-3');
});

it('rejects a token authenticated before the last revocation', function (): void {
    $auth = fakeFirebaseAuth([
        'sub' => 'uid-4',
        'auth_time' => time() - 3600,
    ], 60, ['validSince' => time() - 60]);

    expect(fn () => (new KreaitFirebaseTokenVerifier($auth, 60))->verify('token-abc'))
        ->toThrow(
            FirebaseVerificationException::class,
            'Authentication token has been revoked.',
        );
});

it('maps a revoked token to the session revoked error code', function (): void {
    $auth = fakeFirebaseAuth([
        'sub' => 'uid-5',
        'auth_time' => time() - 3600,
    ], 60, ['validSince' => time()]);

    try {
        (new KreaitFirebaseTokenVerifier($auth, 60))->verify('token-abc');
        $this->fail('Expected exception was not thrown.');
    } catch (FirebaseVerificationException $e) {
        expect($e->status())->toBe(401)
            ->and($e->errorCode())->toBe(ErrorCode::AuthSessionRevoked);
    }
});
