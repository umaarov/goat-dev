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

    public function handle()
    {
        $sitemapUrl = url('/sitemap.xml');
        $googlePingUrl = "https://www.google.com/ping?sitemap={$sitemapUrl}";

        $bingPingUrl = "https://www.bing.com/ping?sitemap={$sitemapUrl}";

        Http::get($googlePingUrl);
        Http::get($bingPingUrl);
    }
}
