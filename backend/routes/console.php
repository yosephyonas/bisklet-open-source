<?php

use App\Models\Device;
use App\Services\OperationalAlertService;
use App\Services\ExclusiveDealsCacheService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('deals:warm-exclusive-cache {--limit=* : One or more limits to pre-warm} {--ttl=60 : Cache TTL in seconds}', function () {
    /** @var ExclusiveDealsCacheService $cache */
    $cache = app(ExclusiveDealsCacheService::class);

    $ttl = max(10, (int) $this->option('ttl'));
    $limits = $this->option('limit');
    $limits = is_array($limits) && $limits !== [] ? $limits : [8];

    $warmed = $cache->warmMany($limits, $ttl);
    $this->info('Exclusive deals cache warmed for limits: ' . implode(', ', $warmed));
})->purpose('Pre-warm exclusive deals cache');

Artisan::command('locks:scan-offline {--minutes=15 : Minutes since last lock check-in before alerting}', function () {
    $minutes = max(1, (int) $this->option('minutes'));
    $cutoff = now()->subMinutes($minutes);
    $alerts = app(OperationalAlertService::class);
    $count = 0;

    Device::query()
        ->where('is_active', true)
        ->where(function ($query) use ($cutoff) {
            $query->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', $cutoff);
        })
        ->orderBy('id')
        ->chunkById(100, function ($devices) use ($alerts, $cutoff, &$count) {
            foreach ($devices as $device) {
                if ($alerts->recordOfflineDevice($device, $cutoff) !== null) {
                    $count++;
                }
            }
        });

    $this->info("Offline lock scan completed. {$count} alert(s) open or updated.");
})->purpose('Create alerts for locks that have stopped checking in');

Schedule::command('deals:warm-exclusive-cache --limit=8 --ttl=60')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('locks:scan-offline --minutes=15')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
