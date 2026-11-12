<?php

namespace App\Support\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception yang dipetakan langsung ke response error envelope.
 *
 * Catatan: properti bawaan Exception::$code bertipe int dan tidak dipakai;
 * kode aplikasi disimpan pada {@see ApiException::errorCode()}.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public function __construct(
        string $message,
        private readonly int $status = 400,
        private readonly ?ErrorCode $errorCode = null,
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): ?ErrorCode
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public static function unauthorized(
        string $message = 'Unauthenticated.',
        ErrorCode $code = ErrorCode::Unauthenticated,
        array $errors = [],
    ): static {
        return new static($message, 401, $code, $errors);
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public static function forbidden(
        string $message = 'This action is unauthorized.',
        array $errors = [],
    ): static {
        return new static($message, 403, ErrorCode::Forbidden, $errors);
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public static function notFound(
        string $message = 'Resource not found.',
        array $errors = [],
    ): static {
        return new static($message, 404, ErrorCode::NotFound, $errors);
    }
}
