<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ChatController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('admin/login', [AuthController::class, 'login']);
Route::post('admin/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('admin/logout', [AuthController::class, 'logout']);
    Route::post('company/create', [CompanyController::class, 'store']);
});

// OpenAI API routes
Route::middleware('auth:sanctum')->post('/chat', [ChatController::class, 'chat']);