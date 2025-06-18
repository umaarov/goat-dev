<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentLikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
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
});

Route::get('/', [PostController::class, 'index'])->name('home')->middleware('cache.response:10');
Route::get('/search', [PostController::class, 'search'])->name('search');
Route::get('/p/{id}/{slug?}', [PostController::class, 'showBySlug'])->name('posts.showSlug')->middleware('cache.response:60');
Route::get('/@{username}', [UserController::class, 'showProfile'])->name('profile.show');
Route::get('/check-username', [UserController::class, 'checkUsername'])->name('check.username');

Route::view('about', 'about')->name('about')->middleware('cache.response:1440');
Route::view('terms', 'terms')->name('terms')->middleware('cache.response:1440');
Route::view('sponsorship', 'sponsorship')->name('sponsorship')->middleware('cache.response:1440');
Route::view('ads', 'ads')->name('ads')->middleware('cache.response:1440');
Route::view('contribution', 'contribution')->name('contribution')->middleware('cache.response:1440');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::controller(UserController::class)->group(function () {
        Route::get('/profile/edit', 'edit')->name('profile.edit');
        Route::put('/profile/update', 'update')->name('profile.update');
        Route::get('/profile/change-password', 'showChangePasswordForm')->name('password.change.form');
        Route::post('/profile/change-password', 'changePassword')->name('password.change');
        Route::get('/@{username}/posts-data', 'getUserPosts')->name('profile.posts.data');
        Route::get('/@{username}/voted-data', 'getUserVotedPosts')->name('profile.voted.data');
    });

    Route::controller(PostController::class)->group(function () {
        Route::get('/posts/create', 'create')->name('posts.create');
        Route::post('/posts', 'store')->name('posts.store')->middleware('throttle:10,1'); // Limit post creation
        Route::get('/posts/{post}/edit', 'edit')->name('posts.edit');
        Route::put('/posts/{post}', 'update')->name('posts.update');
        Route::delete('/posts/{post}', 'destroy')->name('posts.destroy');
        Route::get('/posts/{post}', 'show')->name('posts.show');

        Route::post('/posts/{post}/vote', 'vote')->name('posts.vote')->middleware('throttle:30,1');
        Route::post('/posts/{post}/share', 'incrementShareCount')->name('posts.share')->middleware('throttle:30,1');
    })->whereNumber('post');

    Route::controller(CommentController::class)->group(function () {
        Route::get('/posts/{post}/comments', 'index')->name('comments.index');
        Route::post('/posts/{post}/comments', 'store')->name('comments.store')->middleware('throttle:30,1'); // Limit comment creation
        Route::put('/comments/{comment}', 'update')->name('comments.update');
        Route::delete('/comments/{comment}', 'destroy')->name('comments.destroy');
        Route::get('/comments/{comment}/replies', 'getReplies')->name('comments.getReplies');
    })->whereNumber(['post', 'comment']);

    Route::post('/comments/{comment}/toggle-like', [CommentLikeController::class, 'toggleLike'])
        ->name('comments.toggle-like')
        ->middleware('throttle:30,1');

    Route::controller(NotificationController::class)->group(function () {
        Route::get('/notifications', 'index')->name('notifications.index');
        Route::post('/notifications/send', 'store')->name('notifications.store');
    });

    Route::controller(AuthController::class)->group(function () {
        Route::get('/email/verify', 'showVerificationNotice')->name('verification.notice');
        Route::post('/email/resend', 'resendVerificationEmail')->name('verification.resend')->middleware('throttle:2,1');
    });
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/email/verify/{id}/{token}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');


Route::get('/sitemap.xml', [SitemapController::class, 'index']);

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
})->middleware('cache.response:1440');
