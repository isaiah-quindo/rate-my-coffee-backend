<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CoffeeShopController;
use App\Http\Controllers\Api\ShopHourController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ShopPhotoController;
use App\Http\Controllers\Api\AuthController;

Route::middleware('api')->group(function () {
    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/user/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::get('/auth/user/{id}', [AuthController::class, 'showUser'])->middleware('auth:sanctum');
    // Public endpoints
    Route::get('/coffee-shops', [CoffeeShopController::class, 'index']);
    // Get all coffee shops locations
    Route::get('/coffee-shops/locations', [CoffeeShopController::class, 'locations']);
    Route::get('/coffee-shops/{slug}', [CoffeeShopController::class, 'showBySlug']);
    Route::get('/coffee-shops/{shop}', [CoffeeShopController::class, 'show']);

    // Nested Shop Hours routes
    Route::get('/coffee-shops/{shop}/hours', [ShopHourController::class, 'index']);
    Route::post('/coffee-shops/{shop}/hours', [ShopHourController::class, 'store']);
    Route::get('/coffee-shops/{shop}/hours/{day}/{open}', [ShopHourController::class, 'show']);
    Route::patch('/coffee-shops/{shop}/hours/{day}/{open}', [ShopHourController::class, 'update']);
    Route::delete('/coffee-shops/{shop}/hours/{day}/{open}', [ShopHourController::class, 'destroy']);

    // Reviews routes
    Route::get('/coffee-shops/{shop}/reviews', [PostController::class, 'indexByShop']);
    Route::post('/coffee-shops/{shop}/reviews', [PostController::class, 'store']);
    Route::get('/reviews/{post}', [PostController::class, 'show']);
    Route::patch('/reviews/{post}', [PostController::class, 'update']);
    Route::delete('/reviews/{post}', [PostController::class, 'destroy']);

    // Shop Photos routes
    Route::get('/coffee-shops/{shop}/photos', [ShopPhotoController::class, 'index']);
    Route::post('/coffee-shops/{shop}/photos', [ShopPhotoController::class, 'store']);
    Route::get('/photos/{photo}', [ShopPhotoController::class, 'show']);
    Route::patch('/photos/{photo}', [ShopPhotoController::class, 'update']);
    Route::delete('/photos/{photo}', [ShopPhotoController::class, 'destroy']);

    // Admin-only endpoints (enforced by controller middleware Auth + Gate)
    Route::post('/coffee-shops', [CoffeeShopController::class, 'store']);
    Route::patch('/coffee-shops/{shop}', [CoffeeShopController::class, 'update']);
    Route::delete('/coffee-shops/{shop}', [CoffeeShopController::class, 'destroy']);
});
