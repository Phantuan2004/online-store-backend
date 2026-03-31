<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AttributeValueController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// PUBLIC ROUTES
// ==========================================

// Auth (Public)
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('refresh', [AuthController::class, 'refresh']);

// Products (Public View)
Route::apiResource('products', ProductController::class)->only(['index', 'show']);

// Attributes (Public View)
Route::get('attributes', [AttributeController::class, 'index']);
Route::get('attributes/{attribute}', [AttributeController::class, 'show']);

// Payments Callback (Public for Gateway)
Route::post('payments/callback', [PaymentController::class, 'handleCallback']);


// ==========================================
// AUTHENTICATED ROUTES
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth & User Profile
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    
    Route::get('user/profile', [UserController::class, 'profile']);
    Route::put('user/profile', [UserController::class, 'updateProfile']);
    
    // Auth + Admin required routes
    Route::middleware('is_admin')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
        
        // Attributes Management (Admin only, no prefix)
        Route::post('attributes', [AttributeController::class, 'store']);
        Route::put('attributes/{attribute}', [AttributeController::class, 'update']);
        Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy']);
        
        // Attribute Values Management
        Route::post('attributes/{attribute}/values', [AttributeValueController::class, 'store']);
        Route::delete('attribute-values/{attributeValue}', [AttributeValueController::class, 'destroy']);
    });

    // Payments (Auth Required to initiate)
    Route::post('payments', [PaymentController::class, 'createPayment']);

    // Admin Routes
    Route::prefix('admin')->middleware('is_admin')->group(function () {
        Route::apiResource('users', UserController::class);
        
        // Admin Product Management
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    });

    // Cart
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('/', [CartController::class, 'addToCart']);
        Route::put('/{cartItem}', [CartController::class, 'updateQuantity']);
        Route::delete('/{cartItem}', [CartController::class, 'removeItem']);
    });

    // Orders
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);

    // Reviews (Nested Routes)
    Route::prefix('products/{product}/reviews')->group(function () {
        Route::get('/', [ReviewController::class, 'index']);
        Route::post('/', [ReviewController::class, 'store']);
    });

});
