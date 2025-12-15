<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PingSearchEngines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function handle()
    {
        $sitemapUrl = "https://www.goat.uz/sitemap.xml";

        try {
            Http::timeout(10)->get("https://www.google.com/ping?sitemap={$sitemapUrl}");
        } catch (Exception $e) {
            Log::warning('Google Ping failed: ' . $e->getMessage());
        }

        try {
            Http::timeout(10)->get("https://www.bing.com/ping?sitemap={$sitemapUrl}");
        } catch (Exception $e) {
            Log::warning('Bing Ping failed: ' . $e->getMessage());
        }
    }
}
