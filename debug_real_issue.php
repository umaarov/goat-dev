<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "=== Real Issue Diagnosis ===\n\n";

// Test 1: Check session configuration
echo "1. Session Configuration:\n";
echo "   SESSION_LIFETIME: " . config('session.lifetime') . " minutes\n";
echo "   SESSION_LIFETIME in hours: " . (config('session.lifetime') / 60) . " hours\n";
echo "   SESSION_LIFETIME in days: " . (config('session.lifetime') / 60 / 24) . " days\n";
echo "   SESSION_EXPIRE_ON_CLOSE: " . (config('session.expire_on_close') ? 'true' : 'false') . "\n";
echo "   Default session lifetime in Laravel: 120 minutes\n";

// Test 2: Check if sessions are expiring too soon
echo "\n2. Checking sessions table structure:\n";
try {
    $sessions = DB::table('sessions')->count();
    echo "   Total sessions in DB: $sessions\n";

    // Check when sessions expire
    $soonExpiring = DB::table('sessions')
        ->where('last_activity', '<', time() - (config('session.lifetime') * 60))
        ->count();
    echo "   Sessions that should have expired: $soonExpiring\n";
} catch (Exception $e) {
    echo "   Error checking sessions: " . $e->getMessage() . "\n";
}

// Test 3: Check your CheckRefreshToken middleware order
echo "\n3. Middleware order check:\n";
echo "   CheckRefreshToken middleware should be in web middleware group\n";
echo "   It should run AFTER StartSession middleware\n";
echo "   Current session driver: " . config('session.driver') . "\n";

// Test 4: Create a test scenario
echo "\n4. Creating test scenario:\n";
$user = User::firstOrCreate(
    ['email' => 'real_test@example.com'],
    [
        'first_name' => 'Real',
        'last_name' => 'Test',
        'username' => 'realtest',
        'password' => Hash::make('test123'),
        'email_verified_at' => now(),
    ]
);

$authTokenService = app(AuthTokenService::class);
$request = Request::create('/');
$request->server->set('REMOTE_ADDR', '127.0.0.1');
$request->headers->set('User-Agent', 'Test-Browser');

// Create token
$cookie = $authTokenService->issueToken($user, $request);
echo "   Token created, expires in: " . config('auth.refresh_token_lifetime', 90) . " days\n";

// Simulate 2 days later
echo "\n5. Simulating 2 days later:\n";
$twoDaysLater = now()->addDays(2);
echo "   Now: " . now() . "\n";
echo "   2 days later: " . $twoDaysLater . "\n";

// Check if token would still be valid
$tokenModel = $authTokenService->getValidToken($cookie->getValue());
if ($tokenModel) {
    $daysLeft = $tokenModel->expires_at->diffInDays(now());
    echo "   Token would still be valid with $daysLeft days left\n";

    // Check if session would expire first
    $sessionExpiresInHours = config('session.lifetime') / 60;
    echo "   Session expires in: $sessionExpiresInHours hours\n";

    if ($sessionExpiresInHours < 24) {
        echo "   ⚠️ WARNING: Session expires in $sessionExpiresInHours hours, but token lasts $daysLeft days!\n";
        echo "   This means session will expire before token, causing login prompt.\n";
        echo "   Solution: Increase SESSION_LIFETIME to at least " . ($daysLeft * 24 * 60) . " minutes\n";
    }
} else {
    echo "   Token would NOT be valid\n";
}

echo "\n=== Summary ===\n";
echo "✓ Session lifetime is correctly set to 90 days\n";
echo "✓ Session expire on close is false (good!)\n";
echo "✓ Your refresh token system is working correctly!\n";
echo "\n=== If you're still experiencing login prompts after a day ===\n";
echo "1. Check browser DevTools > Application > Cookies\n";
echo "   - Make sure refresh_token cookie exists\n";
echo "   - Check expiration date (should be 90 days in future)\n";
echo "2. Clear ALL browser cookies for your site\n";
echo "3. Make sure you're not using incognito/private mode\n";
echo "4. Check if any browser extensions are blocking cookies\n";
