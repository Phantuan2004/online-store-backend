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
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CategoryController;


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
Route::post('logout', [AuthController::class, 'logout']);

// Products (Public View)
Route::apiResource('products', ProductController::class)->only(['index', 'show']);

// Categories (Public View)
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);


// Attributes (Public View)
Route::get('attributes', [AttributeController::class, 'index']);
Route::get('attributes/{attribute}', [AttributeController::class, 'show']);

// Payments Callback (Public for Gateway: Accepts both GET for Redirect and POST for IPN)
Route::match(['get', 'post'], 'payments/callback', [PaymentController::class, 'handleCallback']);


// ==========================================
// AUTHENTICATED ROUTES
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth & User Profile
    Route::get('me', [AuthController::class, 'me']);
    
    Route::get('user/profile', [UserController::class, 'profile']);
    Route::put('user/profile', [UserController::class, 'updateProfile']);
    
    // Addresses
    Route::apiResource('addresses', AddressController::class);
    
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

        // Categories Management (Admin)
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
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
        Route::delete('/', [CartController::class, 'clearCart']);
    });

    // Orders (Customer)
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

    // Orders Management (Admin)
    Route::middleware('is_admin')->group(function () {
        Route::put('orders/{order}', [OrderController::class, 'update']);
    });

    // Reviews (Nested Routes)
    Route::prefix('products/{product}/reviews')->group(function () {
        Route::get('/', [ReviewController::class, 'index']);
        Route::post('/', [ReviewController::class, 'store']);
    });

});
