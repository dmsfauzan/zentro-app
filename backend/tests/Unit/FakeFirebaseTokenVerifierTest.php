<?php

use App\Infrastructure\Firebase\FakeFirebaseTokenVerifier;
use App\Infrastructure\Firebase\FirebaseVerificationException;
use App\Support\Exceptions\ErrorCode;

beforeEach(function (): void {
    $this->verifier = new FakeFirebaseTokenVerifier;
});

it('maps claims into a firebase identity', function (): void {
    $identity = $this->verifier->verify(json_encode([
        'uid' => 'abc',
        'email' => 'a@b.test',
        'name' => 'A',
        'email_verified' => true,
        'exp' => time() + 60,
        'firebase' => ['sign_in_provider' => 'google.com'],
    ], JSON_THROW_ON_ERROR));

    expect($identity->uid)->toBe('abc')
        ->and($identity->email)->toBe('a@b.test')
        ->and($identity->emailVerified)->toBeTrue()
        ->and($identity->signInProvider)->toBe('google.com');
});

it('accepts a base64 encoded payload', function (): void {
    $token = base64_encode(json_encode(['sub' => 'xyz', 'exp' => time() + 60], JSON_THROW_ON_ERROR));

    expect($this->verifier->verify($token)->uid)->toBe('xyz');
});

it('throws an invalid error for unparsable tokens', function (): void {
    try {
        $this->verifier->verify('garbage');
        $this->fail('Expected exception was not thrown.');
    } catch (FirebaseVerificationException $e) {
        expect($e->status())->toBe(401)
            ->and($e->errorCode())->toBe(ErrorCode::AuthTokenInvalid);
    }
});

it('throws an expired error for expired tokens', function (): void {
    try {
        $this->verifier->verify(json_encode(['uid' => 'x', 'exp' => time() - 1], JSON_THROW_ON_ERROR));
        $this->fail('Expected exception was not thrown.');
    } catch (FirebaseVerificationException $e) {
        expect($e->errorCode())->toBe(ErrorCode::AuthTokenExpired);
    }
});

it('throws a disabled error for disabled accounts', function (): void {
    try {
        $this->verifier->verify(json_encode(['uid' => 'x', 'disabled' => true], JSON_THROW_ON_ERROR));
        $this->fail('Expected exception was not thrown.');
    } catch (FirebaseVerificationException $e) {
        expect($e->errorCode())->toBe(ErrorCode::AuthAccountDisabled);
    }
});
