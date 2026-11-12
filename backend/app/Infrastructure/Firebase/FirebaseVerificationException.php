<?php

namespace App\Infrastructure\Firebase;

use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Throwable;

/**
 * Kegagalan verifikasi Firebase ID Token yang sudah dipetakan ke response envelope.
 */
final class FirebaseVerificationException extends ApiException
{
    public static function invalid(string $message = 'Invalid authentication token.', ?Throwable $previous = null): self
    {
        return new self($message, 401, ErrorCode::AuthTokenInvalid, [], $previous);
    }

    public static function expired(?Throwable $previous = null): self
    {
        return new self('Authentication token has expired.', 401, ErrorCode::AuthTokenExpired, [], $previous);
    }

    public static function revoked(?Throwable $previous = null): self
    {
        return new self('Authentication token has been revoked.', 401, ErrorCode::AuthSessionRevoked, [], $previous);
    }

    public static function disabled(?Throwable $previous = null): self
    {
        return new self('This account has been disabled.', 401, ErrorCode::AuthAccountDisabled, [], $previous);
    }
}
