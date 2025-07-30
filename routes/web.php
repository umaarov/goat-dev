<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentLikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/language/{locale}', [LocaleController::class, 'setLocale'])->name('language.set');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/auth/google', [AuthController::class, 'googleRedirect'])->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);

    Route::get('/auth/x', [AuthController::class, 'xRedirect'])->name('auth.x');
    Route::get('/auth/x/callback', [AuthController::class, 'xCallback'])->name('auth.x.callback');

    Route::get('/auth/telegram/redirect', [AuthController::class, 'telegramRedirect'])->name('auth.telegram.redirect');
    Route::get('/auth/telegram/callback', [AuthController::class, 'telegramCallback'])->name('auth.telegram.callback');
});

Route::get('/', [PostController::class, 'index'])->name('home')->middleware('cache.response:10');
Route::get('/search', [PostController::class, 'search'])->name('search');
//Route::get('/p/{id}/{slug?}', [PostController::class, 'showBySlug'])->name('posts.showSlug')->middleware('cache.response:60');
Route::get('/@{username}/post/{post}', [PostController::class, 'showUserPost'])
    ->name('posts.show.user-scoped')
    ->where('post', '[0-9]+');

Route::get('/@{username}', [UserController::class, 'showProfile'])->name('profile.show');
Route::get('/check-username', [UserController::class, 'checkUsername'])->name('check.username');

Route::get('forgot-password', [AuthController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
Route::post('reset-password', [AuthController::class, 'reset'])->name('password.update');


Route::view('about', 'about')->name('about')->middleware('cache.response:1440');
Route::view('terms', 'terms')->name('terms')->middleware('cache.response:1440');
Route::view('sponsorship', 'sponsorship')->name('sponsorship')->middleware('cache.response:1440');
Route::view('ads', 'ads')->name('ads')->middleware('cache.response:1440');
Route::view('contribution', 'contribution')->name('contribution')->middleware('cache.response:1440');

//Route::get('/sss', SssController::class)->name('sss.show');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::controller(UserController::class)->group(function () {
        Route::get('/profile/edit', 'edit')->name('profile.edit');
        Route::put('/profile/update', 'update')->name('profile.update');
        Route::get('/profile/change-password', 'showChangePasswordForm')->name('password.change.form');
        Route::post('/profile/change-password', 'changePassword')->name('password.change');
        Route::get('/@{username}/posts-data', 'getUserPosts')->name('profile.posts.data');
        Route::get('/@{username}/voted-data', 'getUserVotedPosts')->name('profile.voted.data');
        Route::post('/profile/generate-picture', 'generateProfilePicture')->name('profile.picture.generate');
    });

    Route::get('/rating', [RatingController::class, 'index'])->name('rating.index');

    Route::controller(PostController::class)->group(function () {
        Route::get('/posts/create', 'create')->name('posts.create');
        Route::post('/posts', 'store')->name('posts.store')->middleware('throttle:10,1');
        Route::get('/posts/{post}/edit', 'edit')->name('posts.edit');
        Route::put('/posts/{post}', 'update')->name('posts.update');
        Route::delete('/posts/{post}', 'destroy')->name('posts.destroy');
        Route::get('/posts/{post}', 'show')->name('posts.show');

        Route::post('/posts/{post}/vote', 'vote')->name('posts.vote')->middleware('throttle:30,1');
        Route::post('/posts/{post}/share', 'incrementShareCount')->name('posts.share')->middleware('throttle:30,1');
    })->whereNumber('post');

    Route::controller(CommentController::class)->group(function () {
        Route::get('/posts/{post}/comments', 'index')->name('comments.index');
        Route::post('/posts/{post}/comments', 'store')->name('comments.store')->middleware('throttle:30,1');
        Route::put('/comments/{comment}', 'update')->name('comments.update');
        Route::delete('/comments/{comment}', 'destroy')->name('comments.destroy');
        Route::get('/comments/{comment}/replies', 'getReplies')->name('comments.getReplies');
        Route::get('/posts/{post}/comments/context/{comment}', 'showCommentContext')->name('comments.showContext');
    })->whereNumber(['post', 'comment']);

    Route::post('/comments/{comment}/toggle-like', [CommentLikeController::class, 'toggleLike'])->name('comments.toggle-like')->middleware('throttle:30,1');

    Route::controller(NotificationController::class)->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.unread.count');
        Route::post('/notifications/send', 'store')->name('notifications.store');
    });

    Route::controller(AuthController::class)->group(function () {
        Route::get('/email/verify', 'showVerificationNotice')->name('verification.notice');
        Route::post('/email/resend', 'resendVerificationEmail')->name('verification.resend')->middleware('throttle:2,1');
    });
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('password/set', [UserController::class, 'showSetPasswordForm'])->name('password.set.form');
    Route::put('password/set', [UserController::class, 'setPassword'])->name('password.set');

    Route::get('confirm-password', [AuthController::class, 'showConfirmForm'])->name('password.confirm');
    Route::post('confirm-password', [AuthController::class, 'confirm']);

    Route::delete('/profile/sessions/{session_id}', [UserController::class, 'terminateSession'])->name('profile.sessions.terminate')->middleware(['password.is_set', 'password.confirm']);

    Route::post('/profile/sessions/terminate-all', [UserController::class, 'terminateAllOtherSessions'])->name('profile.sessions.terminate_all')->middleware(['password.is_set', 'password.confirm']);


});

Route::get('/email/verify/{id}/{token}', [AuthController::class, 'verifyEmail'])->name('verification.verify');


Route::get('/sitemap.xml', [SitemapController::class, 'index']);

Route::get('/load-more-posts', [PostController::class, 'loadMorePosts'])->name('posts.load_more');

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
})->middleware('cache.response:1440');

//Route::get('/debug-proxies', function () {
//    return [
//        'isSecure' => request()->isSecure(),
//        'ip' => request()->ip(),
//        'trustedProxies' => app(Request::class)->getTrustedProxies(),
//    ];
//});


Route::get('/__netdebug', function () {
    $clientIp = request()->header('CF-Connecting-IP')
        ?? request()->header('X-Forwarded-For')
        ?? request()->ip();

    $allowedIps = array_filter(
        array_map('trim', explode(',', env('DEBUG_ALLOWED_IPS', '')))
    );

    if (!in_array($clientIp, $allowedIps, true)) {
        abort(403, ['error' => 'Access denied']);
    }

    return [
        'clientIp' => $clientIp,
        'trustedProxies' => request()->getTrustedProxies(),
        'cfConnectingIp' => request()->header('CF-Connecting-IP'),
        'xForwardedFor' => request()->header('X-Forwarded-For'),
        'remoteAddr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'headers' => request()->headers->all(),
        'isSecure' => request()->isSecure(),
        'scheme' => request()->getScheme(),
    ];
});
