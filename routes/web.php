<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/auth/google', [AuthController::class, 'googleRedirect'])->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
});

Route::get('/', [PostController::class, 'index'])->name('home');
Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{post}', [PostController::class, 'show'])->where('post', '[0-9]+')->name('posts.show');

Route::get('/@{username}', [UserController::class, 'showProfile'])->name('profile.show');
Route::get('/@{username}/posts', [UserController::class, 'showUserPosts'])->name('profile.posts');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', function () {
        $user = Auth::user();
        return view('dashboard', compact('user'));
    })->name('dashboard');

    Route::get('/profile/edit', [UserController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/update', [UserController::class, 'update'])->name('profile.update');
    Route::get('/profile/change-password', [UserController::class, 'showChangePasswordForm'])->name('password.change.form');
    Route::post('/profile/change-password', [UserController::class, 'changePassword'])->name('password.change');
    Route::get('/my-posts', [UserController::class, 'showMyPosts'])->name('my-posts');

    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->where('post', '[0-9]+')->name('posts.edit');
    Route::put('/posts/{post}', [PostController::class, 'update'])->where('post', '[0-9]+')->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->where('post', '[0-9]+')->name('posts.destroy');

    Route::post('/posts/{post}/vote', [PostController::class, 'vote'])->where('post', '[0-9]+')->name('posts.vote');

    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->where('post', '[0-9]+')->name('comments.store');
    // Route::get('/comments/{comment}/edit', [CommentController::class, 'edit'])->where('comment', '[0-9]+')->name('comments.edit');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->where('comment', '[0-9]+')->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->where('comment', '[0-9]+')->name('comments.destroy');

    Route::post('/posts/{post}/share', [ShareController::class, 'store'])->where('post', '[0-9]+')->name('posts.share');
});

// Fallback route for 404 maybe
// Route::fallback(function() {
//     return response()->view('errors.404', [], 404);
// });
