<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const KEY = 'monitoring:queue_heartbeat';

    public $tries = 1;

    public function handle(): void
    {
        Cache::put(self::KEY, time(), now()->addDay());
    }
}
