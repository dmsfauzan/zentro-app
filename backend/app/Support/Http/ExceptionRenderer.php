<?php

namespace App\Support\Http;

use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Memetakan exception menjadi response envelope yang konsisten untuk /api/*.
 */
final class ExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        if ($e instanceof ApiException) {
            return ApiResponse::error(
                $e->getMessage(),
                $e->errors(),
                $e->status(),
                $e->errorCode(),
            );
        }

        if ($e instanceof ValidationException) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422,
                ErrorCode::ValidationFailed,
            );
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(
                'Unauthenticated.',
                [],
                401,
                ErrorCode::Unauthenticated,
            );
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return ApiResponse::error(
                $e instanceof AuthorizationException
                    ? ($e->getMessage() ?: 'This action is unauthorized.')
                    : 'This action is unauthorized.',
                [],
                403,
                ErrorCode::Forbidden,
            );
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return ApiResponse::error(
                'Resource not found.',
                [],
                404,
                ErrorCode::NotFound,
            );
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error(
                'HTTP method not allowed for this endpoint.',
                [],
                405,
                ErrorCode::MethodNotAllowed,
            );
        }

        if ($e instanceof TooManyRequestsHttpException) {
            $response = ApiResponse::error(
                'Too many requests. Please try again later.',
                [],
                429,
                ErrorCode::TooManyRequests,
            );

            if ($retryAfter = $e->getHeaders()['Retry-After'] ?? null) {
                $response->headers->set('Retry-After', (string) $retryAfter);
            }

            return $response;
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return ApiResponse::error(
                $status >= 500 ? 'Server error.' : $e->getMessage(),
                [],
                $status,
                ErrorCode::ServerError,
            );
        }

        report($e);

        $message = config('app.debug') ? $e->getMessage() : 'Server error.';

        return ApiResponse::error(
            $message,
            [],
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ErrorCode::ServerError,
        );
    }
}
