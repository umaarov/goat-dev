<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class PingSearchEngines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function handle()
    {
        $sitemapUrl = url('/sitemap.xml');
        Http::timeout(10)->get("https://www.google.com/ping?sitemap={$sitemapUrl}");
        Http::timeout(10)->get("https://www.bing.com/ping?sitemap={$sitemapUrl}");
    }
}
