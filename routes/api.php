<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\LeadController;
use App\Http\Middleware\PublicChatRateLimiter;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('admin/login', [AuthController::class, 'login']);
Route::post('admin/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('admin/logout', [AuthController::class, 'logout']);
    Route::post('company/create', [CompanyController::class, 'store']);
    Route::post('company/update-description', [CompanyController::class, 'updateDescription']);
    Route::get('company', [CompanyController::class, 'show']);
    Route::post('company/delete', [CompanyController::class, 'destroy']);
    Route::get('company/embedscript', [CompanyController::class, 'generateEmbedScript']);
});

// OpenAI API routes
Route::middleware('auth:sanctum')->post('/chat', [ChatController::class, 'chat']);

// Chat History routes
Route::middleware('auth:sanctum')->get('/chat/history', [ChatController::class, 'history']);
Route::middleware('auth:sanctum')->get('/chat/conversations', [ChatController::class, 'conversationHistory']);

// Public chat endpoint
Route::post('/public-chat/{company:slug}', [ChatController::class, 'publicChat'])
    ->middleware('public-chat-rate-limiter');

// Leads endpoints
Route::middleware('auth:sanctum')->get('/leads', [LeadController::class, 'index']);

// Plans endpoints
Route::get('/plans', [PlanController::class, 'index']); 

// Subscription endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::get('/subscribe/confirm', [SubscriptionController::class, 'confirm']);
    Route::get('/subscribe/status', [SubscriptionController::class, 'status']);
});