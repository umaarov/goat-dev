<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SecurityCheck extends Command
{
    protected $signature = 'security:check {--strict : treat warnings as failures}';

    protected $description = 'Audit the running configuration for insecure settings';

    private array $rows = [];

    public function handle(): int
    {
        $prod = app()->isProduction();

        $this->check('APP_KEY set', strlen((string) config('app.key')) >= 32);
        $this->check('APP_DEBUG off', !config('app.debug'), $prod);
        $this->check('APP_URL is https', str_starts_with((string) config('app.url'), 'https://'), $prod);

        $this->check('Session cookie Secure', (bool) config('session.secure'), $prod);
        $this->check('Session cookie HttpOnly', (bool) config('session.http_only'));
        $this->check('Session SameSite lax/strict', in_array(strtolower((string) config('session.same_site')), ['lax', 'strict'], true));
        $this->check('Session payload encrypted', (bool) config('session.encrypt'), $prod);

        $this->check('Password hashing argon2id', config('hashing.driver') === 'argon2id', false);
        $this->check('Password min length >= 10', (int) config('security.password.min_length') >= 10);
        $this->check('Breached-password check on', (bool) config('security.password.check_breached'), false);

        $origins = (array) config('cors.allowed_origins');
        $badOrigin = collect($origins)->contains(fn ($o) => str_contains($o, 'localhost') || str_contains($o, '127.0.0.1') || ($prod && str_starts_with($o, 'http://')));
        $this->check('CORS origins are production-only', !$badOrigin, $prod);

        $this->check('CSP enabled', (bool) config('security.csp.enabled'));
        $this->check('Local disk is not web-served', !config('filesystems.disks.local.serve'));

        $this->check('Redis password set', config('database.redis.default.password') !== null && config('database.redis.default.password') !== '', $prod && config('cache.default') === 'redis');
        $this->check('Sonar webhook secret set', (bool) config('security.sonar.webhook_secret'), false);

        $this->check('Moderation text key set (DeepSeek)', (bool) config('services.deepseek.api_key'), false);
        $this->check('Moderation image key set (Groq)', (bool) config('services.groq.api_key'), false);
        $this->check('Moderation prompts present', filled(config('services.deepseek.prompts.text'))
            && filled(config('services.deepseek.prompts.comment'))
            && filled(config('services.deepseek.prompts.url'))
            && filled(config('services.groq.prompts.image')), $prod);
        $this->check('Moderation fails closed', (bool) config('services.moderation.fail_closed'), false);

        $this->check('Refresh grace <= 30s', (int) config('security.refresh.grace_seconds') <= 30);
        $this->check('API access token <= 30 min', (int) config('auth.api_access_token_minutes') <= 30);

        $this->table(['Check', 'Result'], $this->rows);

        $fails = collect($this->rows)->filter(fn ($r) => str_contains($r[1], 'FAIL'))->count();
        $warns = collect($this->rows)->filter(fn ($r) => str_contains($r[1], 'warn'))->count();

        $this->line("failures: {$fails}, warnings: {$warns}, env: ".app()->environment());

        return ($fails > 0 || ($this->option('strict') && $warns > 0)) ? self::FAILURE : self::SUCCESS;
    }

    // $required: a failure is FAIL, otherwise only a warning
    private function check(string $label, bool $ok, bool $required = true): void
    {
        $this->rows[] = [$label, $ok ? 'ok' : ($required ? '<fg=red>FAIL</>' : '<fg=yellow>warn</>')];
    }
}
