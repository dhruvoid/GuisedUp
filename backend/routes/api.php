<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\InteractionController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Guised Up
|--------------------------------------------------------------------------
*/

// Public auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Protected routes — require Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout',       [AuthController::class, 'logout']);
    Route::get('/user',          [AuthController::class, 'me']);

    // Posts
    Route::post('/posts',        [PostController::class, 'store']);
    Route::get('/posts/{post}',  [PostController::class, 'show']);

    // Feed
    Route::get('/feed',          [FeedController::class, 'index']);

    // Search
    Route::get('/search',        [SearchController::class, 'index']);

    // Interactions
    Route::post('/interactions', [InteractionController::class, 'store']);
});
