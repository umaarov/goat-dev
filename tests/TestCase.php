<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(
            Vite::class,
            fn () => new class extends Vite {
                public function __invoke(string $entrypoints, ?string $buildDirectory = null): string
                {
                    return '';
                }
                public function asset(string $asset, ?string $buildDirectory = null): string
                {
                    return '';
                }
            }
        );
    }
}
