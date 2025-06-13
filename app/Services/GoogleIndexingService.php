<?php

namespace App\Services;

use Exception;
use Google\Client;
use Google\Service\SearchConsole;
use Illuminate\Support\Facades\Log;

class GoogleIndexingService
{
    public static function submitSitemap()
    {
        try {
            $client = new Client();
            $client->setAuthConfig(storage_path('app/service-account.json'));
            $client->addScope('https://www.googleapis.com/auth/webmasters');

            $searchConsole = new SearchConsole($client);
            $sitemapUrl = 'https://goat.uz/sitemap.xml';
            $siteUrl = 'https://goat.uz/';

            $searchConsole->sitemaps->submit($siteUrl, $sitemapUrl);

            Log::info('Successfully submitted sitemap to Google Search Console API.');

        } catch (Exception $e) {
            Log::error('Failed to submit sitemap to Google API: ' . $e->getMessage());
        }
    }
}
