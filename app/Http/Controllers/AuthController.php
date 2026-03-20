<?php

namespace App\Http\Controllers;

use App\Models\IaEmpresa;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors([
                'email' => 'Las credenciales no son correctas.',
            ])->onlyInput('email');
        }

        // Cargar el tenant para obtener el teléfono de contacto
        $telefono = null;
        if ($user->tenant_id) {
            try {
                app(TenantManager::class)->loadById((int) $user->tenant_id);
                $telefono = IaEmpresa::first()?->telefono_pedidos;
                \Illuminate\Support\Facades\Log::info("2FA: tenant={$user->tenant_id} telefono=" . ($telefono ?? 'NULL'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("2FA tenant error: " . $e->getMessage());
            }
        } else {
            \Illuminate\Support\Facades\Log::info("2FA: user {$user->id} sin tenant_id");
        }

        if (!$telefono) {
            \Illuminate\Support\Facades\Log::info("2FA: sin teléfono, login directo");
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        // Generar código de 6 dígitos
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put('pending_2fa', [
            'user_id'    => $user->id,
            'remember'   => $request->boolean('remember'),
            'code'       => $codigo,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        // Enviar código via plantilla WhatsApp "logint"
        $debugInfo = ['telefono' => $telefono, 'codigo' => $codigo, 'api_status' => null, 'api_body' => null];
        try {
            $waRes = Http::withToken(config('api.whatsapp.key'))
                ->post('https://graph.facebook.com/v19.0/' . config('api.whatsapp.phone_number_id') . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to'                => $telefono,
                    'type'              => 'template',
                    'template'          => [
                        'name'     => 'logint',
                        'language' => ['code' => 'es_AR'],
                        'components' => [
                            [
                                'type'       => 'body',
                                'parameters' => [['type' => 'text', 'text' => $codigo]],
                            ],
                            [
                                'type'       => 'button',
                                'sub_type'   => 'url',
                                'index'      => '0',
                                'parameters' => [['type' => 'text', 'text' => $codigo]],
                            ],
                        ],
                    ],
                ]);
            $debugInfo['api_status'] = $waRes->status();
            $debugInfo['api_body']   = $waRes->body();
        } catch (\Throwable $e) {
            $debugInfo['api_body'] = $e->getMessage();
        }
        $request->session()->put('pending_2fa_debug', $debugInfo);

        return redirect()->route('login.verificar');
    }

    public function showVerificar(Request $request)
    {
        if (!$request->session()->has('pending_2fa')) {
            return redirect()->route('login');
        }

        $email = $request->session()->get('pending_2fa.email', '');
        // Ocultar parte del email: ga***@ejemplo.com
        $masked = preg_replace('/(?<=.{2}).(?=.*@)/u', '*', $email);

        return view('admin.login-verificar', compact('masked'));
    }

    public function verificar(Request $request)
    {
        $request->validate(['codigo' => 'required|digits:6']);

        $pending = $request->session()->get('pending_2fa');

        if (!$pending) {
            return redirect()->route('login');
        }

        if (now()->timestamp > $pending['expires_at']) {
            $request->session()->forget('pending_2fa');
            return redirect()->route('login')->withErrors([
                'email' => 'El código expiró. Ingresá nuevamente.',
            ]);
        }

        if ($request->input('codigo') !== $pending['code']) {
            return back()->withErrors(['codigo' => 'Código incorrecto.']);
        }

        $request->session()->forget('pending_2fa');
        Auth::loginUsingId($pending['user_id'], $pending['remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
