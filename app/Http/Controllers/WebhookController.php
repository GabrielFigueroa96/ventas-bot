<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;

class WebhookController extends Controller
{
    public function verify(Request $request)
    {
              try {
            $verifyToken = '#GabrielFigueroa96!Bancalari6051Taylor#';
            $query = $request->query();

            $mode = $query['hub_mode'] ?? null;
            $token = $query['hub_verify_token'] ?? null;
            $challenge = $query['hub_challenge'] ?? null;

            if ($mode && $token) {
                if ($mode === 'subscribe' && $token === $verifyToken) {
                    return response($challenge, 200)->header('Content-Type', 'text/plain');
                }
            }

            throw new Exception('Invalid request');
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}