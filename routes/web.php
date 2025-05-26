<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/language/{locale}', [LocaleController::class, 'setLocale'])->name('language.set');

Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/email/verify', [AuthController::class, 'showVerificationNotice'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{token}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');

Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
    ->middleware('auth')
    ->name('verification.resend');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/auth/google', [AuthController::class, 'googleRedirect'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);

Route::get('/', [PostController::class, 'index'])->name('home');
Route::get('/search', [PostController::class, 'search'])->name('search');

Route::get('/p/{id}/{slug?}', [PostController::class, 'showBySlug'])->name('posts.show.slug');
Route::post('/posts/{post}/share', [PostController::class, 'incrementShareCount']);

Route::get('about', function () {
    return view('about');
})->name('about');

Route::get('terms', function () {
    return view('terms');
})->name('terms');

Route::get('sponsorship', function () {
    return view('sponsorship');
})->name('sponsorship');

Route::get('ads', function () {
    return view('ads');
})->name('ads');

Route::get('/@{username}', [UserController::class, 'showProfile'])
    ->where('username', '[a-zA-Z0-9_\-]+')
    ->name('profile.show');

Route::get('/check-username', [UserController::class, 'checkUsername'])->name('check.username');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])
        ->where('post', '[0-9]+')
        ->name('posts.edit');
    Route::put('/posts/{post}', [PostController::class, 'update'])
        ->where('post', '[0-9]+')
        ->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])
        ->where('post', '[0-9]+')
        ->name('posts.destroy');

    Route::post('/posts/{post}/vote', [PostController::class, 'vote'])
        ->where('post', '[0-9]+')
        ->name('posts.vote');
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])
        ->where('post', '[0-9]+')
        ->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])
        ->where('comment', '[0-9]+')
        ->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])
        ->where('comment', '[0-9]+')
        ->name('comments.destroy');
    Route::get('/posts/{post}/comments', [CommentController::class, 'index'])->name('comments.index');

    Route::get('/profile/edit', [UserController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/update', [UserController::class, 'update'])->name('profile.update');

    Route::get('/profile/change-password', [UserController::class, 'showChangePasswordForm'])->name('password.change.form');
    Route::post('/profile/change-password', [UserController::class, 'changePassword'])->name('password.change');

    Route::get('/@{username}/posts-data', [UserController::class, 'getUserPosts'])
        ->where('username', '[a-zA-Z0-9_\-]+')
        ->name('profile.posts.data');

    Route::get('/@{username}/voted-data', [UserController::class, 'getUserVotedPosts'])
        ->where('username', '[a-zA-Z0-9_\-]+')
        ->name('profile.voted.data');
});

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
