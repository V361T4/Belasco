<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// Auth
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login',    [AuthenticatedSessionController::class, 'store']);
Route::post('/logout',   [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->get('/user', fn() => request()->user());

// Public products
Route::get('/products',        [ProductController::class, 'index']);
Route::get('/products/{id}',   [ProductController::class, 'show']);

// Protected: Cart + Orders
Route::middleware('auth:sanctum')->group(function () {
    // Cart
    Route::get('/cart',            [CartController::class, 'index']);
    Route::post('/cart',           [CartController::class, 'store']);
    Route::put('/cart/{id}',       [CartController::class, 'update']);
    Route::delete('/cart/{id}',    [CartController::class, 'destroy']);

    // Orders
    Route::post('/checkout',       [OrderController::class, 'store']);
    Route::get('/orders',          [OrderController::class, 'index']);
});