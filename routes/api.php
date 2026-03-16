<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WhatsappController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/webhook', [WebhookController::class, 'verify']);
Route::post('/webhook', [WhatsappController::class, 'webhook']);

/*
Route::post('/webhook', function (Request $request) {
    
    try {

        $bodyContent = json_decode($request->getContent(), true);

        $value = $bodyContent['entry'][0]['changes'][0]['value'];
        
        if (!empty($value['messages'])) {
            if ($value['messages'][0]['type'] == 'text') {
                $body = $value['messages'][0]['text']['body'];
                $from = $value['messages'][0]['from'];
            }
        }

    }catch(Exception $ex){
        
        return $ex->getMessage();
        
    }
    
});*/



