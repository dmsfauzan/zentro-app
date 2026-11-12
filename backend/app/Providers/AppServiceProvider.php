<?php

namespace App\Providers;

use App\Contracts\Auth\FirebaseTokenVerifier;
use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Shipping\ShippingProviderInterface;
use App\Contracts\Shipping\ShippingRateProvider;
use App\Infrastructure\Firebase\FakeFirebaseTokenVerifier;
use App\Infrastructure\Firebase\KreaitFirebaseTokenVerifier;
use App\Infrastructure\Payment\FakePaymentGateway;
use App\Infrastructure\Payment\MidtransGateway;
use App\Infrastructure\Shipping\FakeShippingProvider;
use App\Infrastructure\Shipping\FlatRateShippingProvider;
use App\Infrastructure\Shipping\ManualShippingProvider;
use App\Modules\Identity\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FirebaseTokenVerifier::class, function ($app): FirebaseTokenVerifier {
            if ((bool) config('firebase.fake_verifier', false)) {
                return new FakeFirebaseTokenVerifier;
            }

            return new KreaitFirebaseTokenVerifier(
                $app->make(FirebaseAuth::class),
                (int) config('firebase.leeway', 0),
            );
        });

        $this->app->singleton(ShippingRateProvider::class, FlatRateShippingProvider::class);

        $this->app->singleton(PaymentGatewayInterface::class, function (): PaymentGatewayInterface {
            if (config('zentro.payment.driver') === 'midtrans') {
                return new MidtransGateway((array) config('zentro.payment.midtrans', []));
            }

            return new FakePaymentGateway((string) config('zentro.payment.fake_secret', 'zentro-fake-secret'));
        });

        $this->app->singleton(ShippingProviderInterface::class, function (): ShippingProviderInterface {
            $secret = (string) config('zentro.shipping.webhook_secret', 'zentro-shipping-secret');

            if (config('zentro.shipping.driver') === 'fake') {
                return new FakeShippingProvider($secret);
            }

            return new ManualShippingProvider($secret);
        });
    }

    public function boot(): void
    {
        // Super admin melewati seluruh pemeriksaan permission.
        Gate::before(function ($user, string $ability): ?bool {
            if ($user instanceof User && $user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->getKey() ?? $request->ip();

            return Limit::perMinute(120)->by($key);
        });

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('checkout', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->getKey() ?? $request->ip());
        });

        RateLimiter::for('payment-retry', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->getKey() ?? $request->ip());
        });

        RateLimiter::for('webhook', function (Request $request): Limit {
            return Limit::perMinute(300)->by($request->ip());
        });

        RateLimiter::for('shipping-action', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->getKey() ?? $request->ip());
        });

        RateLimiter::for('finance-action', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->getKey() ?? $request->ip());
        });
    }
}
