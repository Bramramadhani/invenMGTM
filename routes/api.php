<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Api\CategoryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes di file ini otomatis berada pada prefix /api dan middleware "api".
| Sanctum cookie-based auth aktif via EnsureFrontendRequestsAreStateful.
|
*/

// Cek sesi/login via Sanctum cookie
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

// Contoh publik sederhana (opsional)
// Route::get('/health', fn() => response()->json(['ok' => true]));

// Contoh resource lain (opsional, sesuaikan kebutuhan)
// Route::apiResource('categories', CategoryController::class);
