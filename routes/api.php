<?php
//
//use App\Http\Controllers\API\AuthController;
//use App\Http\Controllers\API\CommentController;
//use App\Http\Controllers\API\PostController;
//use App\Http\Controllers\API\ShareController;
//use App\Http\Controllers\API\UserController;
//use Illuminate\Support\Facades\Route;
//
//Route::post('/register', [AuthController::class, 'register']);
//Route::post('/login', [AuthController::class, 'login']);
//Route::post('/refresh', [AuthController::class, 'refresh']);
//Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
//Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
//
//Route::get('/posts', [PostController::class, 'index']);
//Route::get('/posts/{post}', [PostController::class, 'show']);
//Route::get('/comments/{post}', [CommentController::class, 'index']);
//Route::get('/user/{username}/profile', [UserController::class, 'getProfile']);
//Route::get('/user/{username}/posts', [UserController::class, 'getUserPosts']);
//
//Route::middleware('auth:sanctum')->group(function () {
//    Route::post('/logout', [AuthController::class, 'logout']);
//    Route::get('/user', [AuthController::class, 'user']);
//
//    Route::put('/user', [UserController::class, 'update']);
//    Route::post('/user/change-password', [UserController::class, 'changePassword']);
//
//    Route::post('/posts', [PostController::class, 'store']);
//    Route::put('/posts/{post}', [PostController::class, 'update']);
//    Route::delete('/posts/{post}', [PostController::class, 'destroy']);
//    Route::post('/posts/{post}/vote', [PostController::class, 'vote']);
//    Route::post('/posts/{post}/save', [PostController::class, 'savePost']);
//    Route::delete('/posts/{post}/save', [PostController::class, 'unsavePost']);
//    Route::get('/user/posts', [PostController::class, 'getUserPosts']);
//
//    Route::post('/comments/{post}', [CommentController::class, 'store']);
//    Route::put('/comments/{comment}', [CommentController::class, 'update']);
//    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
//
//    Route::post('/shares/{post}', [ShareController::class, 'store']);
//});
