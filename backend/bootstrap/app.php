<?php

use App\Modules\Identity\Http\Middleware\VerifyFirebaseToken;
use App\Modules\Seller\Http\Middleware\EnsureSellerActive;
use App\Modules\Seller\Http\Middleware\EnsureSellerExists;
use App\Support\Http\ExceptionRenderer;
use App\Support\Http\Middleware\AssignRequestId;
use App\Support\Http\Middleware\LogRequests;
use App\Support\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->api(prepend: [
            AssignRequestId::class,
        ]);

        // Setelah AssignRequestId agar request_id tersedia untuk korelasi log.
        $middleware->api(append: [
            LogRequests::class,
        ]);

        $middleware->alias([
            'auth.firebase' => VerifyFirebaseToken::class,
            'seller.exists' => EnsureSellerExists::class,
            'seller.active' => EnsureSellerActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            return (new ExceptionRenderer)->render($e, $request);
        });
    })->create();
