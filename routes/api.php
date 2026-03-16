<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsappController;
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

Route::get('/webhook', function (Request $request) {
    try {

        $verifyToken = 'cgf1568!Bancalari6051Taylor';
        $query = $request->query();

        $mode = $query['hub_mode'];
        $token = $query['hub_verify_token'];
        $challenge = $query['hub_challenge'];

        if ($mode && $token) {
            if ($mode === 'subscribe' && $token == $verifyToken) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }
            // return response()->json([
            //     'success' => true,
            //     'data' => $query
            // ], 200);



        }

        throw new Exception('Invalid request');
    } catch (Exception $e) {

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

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


Route::post('/webhook', [WhatsappController::class, 'webhook']);
