<?php

use App\Jobs\AutoCompleteShipmentsJob;
use App\Jobs\ExpireOrdersJob;
use App\Jobs\ExpirePaymentsJob;
use App\Jobs\ReconcilePaymentsJob;
use App\Jobs\ReconcileWalletsJob;
use App\Jobs\RecoverStuckRefundsJob;
use App\Jobs\ReleaseExpiredReservationsJob;
use App\Jobs\ReleaseRiskHoldJob;
use App\Jobs\SettlementReleaseJob;
use App\Jobs\SyncShipmentTrackingJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled jobs
|--------------------------------------------------------------------------
| Menjalankan worker scheduler: `php artisan schedule:work`.
*/

Schedule::job(new ExpireOrdersJob)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job(new ReleaseExpiredReservationsJob)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job(new ExpirePaymentsJob)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job(new ReconcilePaymentsJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::job(new SyncShipmentTrackingJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::job(new AutoCompleteShipmentsJob)
    ->dailyAt('01:00')
    ->withoutOverlapping();

Schedule::job(new SettlementReleaseJob)
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::job(new ReleaseRiskHoldJob)
    ->dailyAt('02:30')
    ->withoutOverlapping();

Schedule::job(new RecoverStuckRefundsJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::job(new ReconcileWalletsJob)
    ->dailyAt('03:00')
    ->withoutOverlapping();
