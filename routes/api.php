<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\CommentLikeController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RatingController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1)
|--------------------------------------------------------------------------
| All routes are prefixed with /api (bootstrap apiPrefix) -> /api/v1/...
| Auth uses Sanctum bearer access tokens; see App\Services\ApiTokenService.
*/

Route::prefix('v1')->group(function () {

    Route::get('meta/config', [MetaController::class, 'config']);

    /* ---------------------------- Authentication ---------------------------- */
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:20,1');
        Route::post('email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

        Route::post('social/{provider}', [SocialAuthController::class, 'login'])
            ->where('provider', 'google|x|telegram|github')
            ->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:2,1');
            Route::get('sessions', [AuthController::class, 'sessions']);
            Route::delete('sessions/{id}', [AuthController::class, 'destroySession'])->whereNumber('id');
        });
    });

    /* -------------------------------- Posts --------------------------------- */
    Route::get('posts', [PostController::class, 'index']);
    Route::get('posts/search', [PostController::class, 'search']);
    Route::get('posts/{post}', [PostController::class, 'show'])->whereNumber('post');

    Route::get('users/check-username', [ProfileController::class, 'checkUsername']);
    Route::get('users/{username}', [ProfileController::class, 'show']);
    Route::get('users/{username}/posts', [PostController::class, 'userPosts']);
    Route::get('users/{username}/voted-posts', [PostController::class, 'userVotedPosts']);

    Route::get('ratings', [RatingController::class, 'index']);

    /* ------------------------------- Comments ------------------------------- */
    Route::get('posts/{post}/comments', [CommentController::class, 'index'])->whereNumber('post');
    Route::get('comments/{comment}/replies', [CommentController::class, 'replies'])->whereNumber('comment');

    /* ----------------------------- Protected -------------------------------- */
    Route::middleware('auth:sanctum')->group(function () {

        // Posts (write)
        Route::post('posts', [PostController::class, 'store'])->middleware('throttle:10,1');
        Route::put('posts/{post}', [PostController::class, 'update'])->whereNumber('post');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->whereNumber('post');
        Route::post('posts/{post}/vote', [PostController::class, 'vote'])->whereNumber('post')->middleware('throttle:30,1');
        Route::post('posts/{post}/share', [PostController::class, 'share'])->whereNumber('post')->middleware('throttle:30,1');

        // Comments (write)
        Route::post('posts/{post}/comments', [CommentController::class, 'store'])->whereNumber('post')->middleware('throttle:30,1');
        Route::put('comments/{comment}', [CommentController::class, 'update'])->whereNumber('comment');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->whereNumber('comment');
        Route::post('comments/{comment}/like', [CommentLikeController::class, 'toggle'])->whereNumber('comment')->middleware('throttle:60,1');

        // Profile / account ("me")
        Route::put('me', [ProfileController::class, 'update']);
        Route::post('me/change-password', [ProfileController::class, 'changePassword']);
        Route::post('me/password', [ProfileController::class, 'setPassword']);
        Route::delete('me/password', [ProfileController::class, 'removePassword']);
        Route::post('me/profile-picture/generate', [ProfileController::class, 'generatePicture']);
        Route::delete('me', [ProfileController::class, 'deactivate']);
        Route::get('me/export', [ProfileController::class, 'export'])->middleware('throttle:3,60');
        Route::post('me/heartbeat', [ProfileController::class, 'heartbeat']);

        // Push-notification device registration
        Route::get('me/devices', [DeviceController::class, 'index']);
        Route::post('me/devices', [DeviceController::class, 'store']);
        Route::delete('me/devices', [DeviceController::class, 'destroy']);
        Route::post('me/social/{provider}', [SocialAuthController::class, 'link'])->where('provider', 'google|x|telegram|github');
        Route::delete('me/social/{provider}', [ProfileController::class, 'unlinkSocial'])->where('provider', 'google|x|telegram|github');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
    });
});

// JSON 404 for any unmatched /api/* endpoint (keeps clients off the web HTML 404 page).
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'error_code' => 'not_found',
        'message' => 'The requested endpoint does not exist.',
    ], 404);
});
