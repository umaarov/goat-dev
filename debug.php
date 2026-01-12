<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

echo "=== Debug Refresh Token System ===\n\n";

// Create a test user if doesn't exist
$user = User::firstOrCreate(
    ['email' => 'debug@example.com'],
    [
        'first_name' => 'Debug',
        'last_name' => 'User',
        'username' => 'debuguser',
        'password' => Hash::make('debug123'),
        'email_verified_at' => now(),
    ]
);

echo "User created: {$user->id}\n";

// Test AuthTokenService
$authTokenService = app(AuthTokenService::class);

// Create a mock request
$request = Request::create('/');
$request->server->set('REMOTE_ADDR', '127.0.0.1');
$request->headers->set('User-Agent', 'Debug-Agent');

// Issue token
$cookie = $authTokenService->issueToken($user, $request);
echo "\n1. Token issued successfully\n";
echo "   Cookie value length: " . strlen($cookie->getValue()) . "\n";
echo "   Cookie name: {$cookie->getName()}\n";
echo "   Expires in: " . $cookie->getExpiresTime() . " seconds\n";
echo "   HttpOnly: " . ($cookie->isHttpOnly() ? 'Yes' : 'No') . "\n";
echo "   Secure: " . ($cookie->isSecure() ? 'Yes' : 'No') . "\n";
echo "   SameSite: " . $cookie->getSameSite() . "\n";

// Validate token
$tokenModel = $authTokenService->getValidToken($cookie->getValue());
if ($tokenModel) {
    echo "\n2. Token validation: SUCCESS\n";
    echo "   Token ID: {$tokenModel->id}\n";
    echo "   User ID: {$tokenModel->user_id}\n";
    echo "   Expires at: {$tokenModel->expires_at}\n";
    echo "   Days until expiry: " . $tokenModel->expires_at->diffInDays(now()) . "\n";
} else {
    echo "\n2. Token validation: FAILED\n";
}

// Check session config
echo "\n3. Session Configuration:\n";
echo "   SESSION_LIFETIME: " . config('session.lifetime') . " minutes\n";
echo "   SESSION_SECURE_COOKIE: " . (config('session.secure') ? 'true' : 'false') . "\n";
echo "   SESSION_SAME_SITE: " . config('session.same_site') . "\n";
echo "   SESSION_DOMAIN: " . config('session.domain') . "\n";

// Check auth config
echo "\n4. Auth Configuration:\n";
echo "   REFRESH_TOKEN_LIFETIME_DAYS: " . config('auth.refresh_token_lifetime', 90) . "\n";
echo "   REFRESH_WITHIN_HOURS: " . config('auth.refresh_within_hours', 12) . "\n";

// Simulate expired token
if ($tokenModel) {
    $tokenModel->update(['expires_at' => now()->subDay()]);
    $expiredToken = $authTokenService->getValidToken($cookie->getValue());
    echo "\n5. Expired token test: " . ($expiredToken ? 'STILL VALID (WRONG!)' : 'INVALID (CORRECT)') . "\n";
}

echo "\n=== Debug Complete ===\n";
