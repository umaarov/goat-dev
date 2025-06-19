<?php

namespace App\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Policy;

class CustomPolicy extends Policy
{
    public function configure()
    {
        $this
            ->addDirective(Directive::BASE, 'self')
            ->addDirective(Directive::CONNECT, [
                'self',
                'https://stats.g.doubleclick.net',
                'https://pagead2.googlesyndication.com',
                'https://ep2.adtrafficquality.google',
            ])
            ->addDirective(Directive::DEFAULT, 'self')
            ->addDirective(Directive::FORM_ACTION, 'self')
            ->addDirective(Directive::IMG, [
                'self',
                'data:',
                'https:',
                'blob:',
                '*',
            ])
            ->addDirective(Directive::FONT, [
                'self',
                'https://fonts.gstatic.com',
            ])
            ->addDirective(Directive::MEDIA, 'self')
            ->addDirective(Directive::OBJECT, 'none')
            ->addDirective(Directive::SCRIPT, [
                'self',
                'https://code.jquery.com',
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://static.cloudflareinsights.com',
                'https://pagead2.googlesyndication.com',
                'https://fundingchoicesmessages.google.com',
                'https://www.google.com',
                'https://www.googletagservices.com',
                'https://adservice.google.com',
                "'unsafe-inline'",
                "'unsafe-eval'",
            ])
            ->addDirective(Directive::STYLE, [
                'self',
                'https://fonts.googleapis.com',
                'https://cdnjs.cloudflare.com',
                "'unsafe-inline'",
            ])
            ->addDirective(Directive::FRAME, [
                'self',
                'https://googleads.g.doubleclick.net',
                'https://tpc.googlesyndication.com',
                'https://fundingchoicesmessages.google.com',
                'https://ep2.adtrafficquality.google',
                'https://www.google.com',
            ])
            ->addDirective('fenced-frame-src', [
                'self',
                'https://googleads.g.doubleclick.net',
                'https://tpc.googlesyndication.com',
                'https://fundingchoicesmessages.google.com',
                'https://ep2.adtrafficquality.google',
                'https://www.google.com',
            ]);
    }
}
