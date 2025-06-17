<?php

namespace App\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Policy;

class CustomPolicy extends Policy
{
    public function configure()
    {
        $this
            ->addDirective(Directive::BASE, ['self'])
            ->addDirective(Directive::SCRIPT, [
                'self',
                'https://code.jquery.com',
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://static.cloudflareinsights.com',
                'https://pagead2.googlesyndication.com',
                'https://fundingchoicesmessages.google.com',
                'https://www.google.com',
                "'unsafe-inline'",
                "'unsafe-eval'",
            ])
            ->addDirective(Directive::STYLE, [
                'self',
                'https://fonts.googleapis.com',
                'https://cdnjs.cloudflare.com',
                "'unsafe-inline'",
            ])
            ->addDirective(Directive::FONT, [
                'self',
                'https://fonts.gstatic.com',
            ])
            ->addDirective(Directive::IMG, [
                'self',
                'data:',
                'https://lh3.googleusercontent.com',
            ])
            ->addDirective(Directive::OBJECT, ['none'])
            ->addDirective(Directive::CONNECT, [
                'self',
                'https://stats.g.doubleclick.net',
            ]);
    }
}
