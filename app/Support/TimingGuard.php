<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

// unknown accounts must cost the same as wrong passwords, or login timing reveals who is registered
final class TimingGuard
{
    // answer no sooner than $seconds after $startedAt, so fast and slow outcomes look alike
    public static function padTo(float $startedAt, float $seconds = 0.5): void
    {
        $remaining = $seconds - (microtime(true) - $startedAt);

        if ($remaining > 0) {
            usleep((int) ($remaining * 1_000_000));
        }
    }

    // keyed by the algorithm settings so the dummy always costs the same as a real check
    public static function cacheKey(): string
    {
        $driver = (string) config('hashing.driver');

        return 'timing-guard:'.md5($driver.json_encode(config('hashing.'.($driver === 'argon2id' ? 'argon' : $driver))));
    }

    public static function burn(string $password): void
    {
        // cached, not static: classic FrankenPHP starts every request fresh, which would add a Hash::make
        // to the unknown-account path
        $hash = Cache::rememberForever(self::cacheKey(), fn () => Hash::make(bin2hex(random_bytes(16))));

        Hash::check($password, $hash);
    }
}
