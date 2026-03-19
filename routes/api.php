<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Webhook directo (Meta apunta aquí en modo dev o sin gateway)
Route::get ('/webhook', [WebhookController::class, 'verify']);
Route::post('/webhook', [WebhookController::class, 'direct']);

// Endpoint que llama el gateway (producción)
Route::post('/handle', [WebhookController::class, 'handle']);