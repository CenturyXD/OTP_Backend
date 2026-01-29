<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserManagementController;

// ... Public routes (login, register) ...
Route::get('/test', function() {
    return response()->json(['message' => 'API is working']);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// เปลี่ยน middleware เป็น 'auth:sanctum' สำหรับทุก Route ที่ต้อง Login
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/forget-password', [AuthController::class, 'forgetPassword']);

    // --- Super Admin Routes ---

    Route::middleware(['auth:sanctum', 'role:superadmin'])->prefix('admin')->group(function () {
        Route::apiResource('users', UserManagementController::class);
    });

});
