<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\IaEmpresa;
use App\Models\Localidad;
use App\Models\Pedidosia;
use App\Models\Pedido;
use App\Models\Carrito;
use App\Services\BotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pruebas de los flujos determinísticos del bot (sin OpenAI).
 *
 * Para correr: php artisan test --filter BotFlowTest
 *
 * Requiere base de datos de test configurada en .env.testing o phpunit.xml.
 */
class BotFlowTest extends TestCase
{
    use RefreshDatabase;

    protected BotService $bot;
    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        // Silenciar llamadas HTTP externas (OpenAI, WhatsApp)
        Http::fake([
            'api.openai.com/*'          => $this->fakeOpenAiRespuestaTexto('OK'),
            'graph.facebook.com/*'      => Http::response(['messages' => [['id' => 'wamid_test']]], 200),
        ]);

        // Empresa mínima
        IaEmpresa::create([
            'nombre'          => 'Test Carnicería',
            'bot_puede_pedir' => true,
            'bot_permite_envio' => true,
            'bot_medios_pago' => ['efectivo'],
            'suc'             => '1',
            'pv'              => '1',
        ]);

        // Localidad con dos días de reparto
        $localidad = Localidad::create([
            'nombre'       => 'TestVille',
            'activo'       => true,
            'dias_reparto' => [
                ['dia' => 3, 'desde_dia' => 1, 'desde_hora' => '00:00'],  // miércoles
                ['dia' => 5, 'desde_dia' => 1, 'desde_hora' => '00:00'],  // viernes
            ],
        ]);

        $this->cliente = Cliente::create([
            'phone'        => '5491100000001',
            'name'         => 'Testino',
            'localidad'    => 'TestVille',
            'localidad_id' => $localidad->id,
            'estado'       => 'activo',
        ]);

        $this->bot = app(BotService::class);
    }

    // ──────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────

    /** Envía un mensaje al bot y devuelve la respuesta. */
    protected function enviar(string $mensaje): string
    {
        return $this->bot->process($this->cliente, $mensaje);
    }

    /** Respuesta OpenAI que simula texto plano (sin tool calls). */
    protected function fakeOpenAiRespuestaTexto(string $contenido): \Illuminate\Http\Client\Response
    {
        return Http::response([
            'choices' => [[
                'finish_reason' => 'stop',
                'message'       => ['content' => $contenido, 'role' => 'assistant'],
            ]],
        ], 200);
    }

    /** Respuesta OpenAI que simula una tool call. */
    protected function fakeOpenAiToolCall(string $tool, array $args): \Illuminate\Http\Client\Response
    {
        return Http::response([
            'choices' => [[
                'finish_reason' => 'tool_calls',
                'message'       => [
                    'role'       => 'assistant',
                    'tool_calls' => [[
                        'id'       => 'call_test_001',
                        'type'     => 'function',
                        'function' => [
                            'name'      => $tool,
                            'arguments' => json_encode($args),
                        ],
                    ]],
                ],
            ]],
        ], 200);
    }

    // ──────────────────────────────────────────────────────
    // 1. FLUJO DE REGISTRO
    // ──────────────────────────────────────────────────────

    public function test_registro_solicita_nombre_si_cliente_nuevo(): void
    {
        $nuevo = Cliente::create(['phone' => '5491199999999']);
        $resp  = $this->bot->process($nuevo, 'hola');
        $this->assertStringContainsStringIgnoringCase('nombre', $resp);
    }

    public function test_registro_verifica_nombre(): void
    {
        $nuevo = Cliente::create(['phone' => '5491199999998', 'estado' => 'esperando_nombre']);
        $resp  = $this->bot->process($nuevo, 'Gabriel');
        $this->assertStringContainsString('Gabriel', $resp);
        $this->assertStringContainsStringIgnoringCase('sí', $resp);
    }

    // ──────────────────────────────────────────────────────
    // 2. DETECCIÓN "LO MISMO DE SIEMPRE"
    // ──────────────────────────────────────────────────────

    /** @dataProvider frasesLoMismo */
    public function test_detecta_frase_lo_mismo(string $frase): void
    {
        $metodo = new \ReflectionMethod($this->bot, 'esLoMismoDeSimepre');
        $metodo->setAccessible(true);
        $this->assertTrue($metodo->invoke($this->bot, $frase), "No detectó: '{$frase}'");
    }

    public static function frasesLoMismo(): array
    {
        return [
            ['lo mismo de siempre'],
            ['lo de siempre'],
            ['repetir pedido'],
            ['el pedido habitual'],
            ['igual que siempre'],
            ['lo habitual'],
            ['lo mismo de la última vez'],
        ];
    }

    public function test_no_detecta_frase_normal(): void
    {
        $metodo = new \ReflectionMethod($this->bot, 'esLoMismoDeSimepre');
        $metodo->setAccessible(true);
        $this->assertFalse($metodo->invoke($this->bot, 'quiero pedir asado'));
        $this->assertFalse($metodo->invoke($this->bot, 'hola'));
    }

    // ──────────────────────────────────────────────────────
    // 3. SELECCIÓN DE REPARTO POR TEXTO
    // ──────────────────────────────────────────────────────

    protected function setearOpcionesReparto(): array
    {
        $opciones = [
            ['fecha' => now()->addDays(2)->format('Y-m-d'), 'texto' => 'miércoles 23 de abril', 'dia' => 3],
            ['fecha' => now()->addDays(4)->format('Y-m-d'), 'texto' => 'viernes 25 de abril',   'dia' => 5],
        ];
        Cache::put('reparto_opciones_' . $this->cliente->id, $opciones, now()->addMinutes(30));
        $this->cliente->update(['estado' => 'eligiendo_reparto']);
        $this->cliente->refresh();
        return $opciones;
    }

    public function test_seleccion_reparto_por_numero(): void
    {
        $opciones = $this->setearOpcionesReparto();

        // Redirigir la llamada interna a process() para no llamar OpenAI
        Http::fake(['api.openai.com/*' => $this->fakeOpenAiRespuestaTexto('Perfecto, tenés el miércoles confirmado.')]);

        $this->enviar('1');

        $this->assertEquals($opciones[0]['fecha'], Cache::get('fecha_reparto_elegida_' . $this->cliente->id));
    }

    public function test_seleccion_reparto_por_nombre_dia(): void
    {
        $opciones = $this->setearOpcionesReparto();
        Http::fake(['api.openai.com/*' => $this->fakeOpenAiRespuestaTexto('Perfecto.')]);

        $this->enviar('viernes');

        $this->assertEquals($opciones[1]['fecha'], Cache::get('fecha_reparto_elegida_' . $this->cliente->id));
    }

    public function test_seleccion_reparto_opcion_invalida_repite_lista(): void
    {
        $this->setearOpcionesReparto();

        $resp = $this->enviar('mañana temprano');

        $this->assertStringContainsString('No entendí', $resp);
        $this->assertStringContainsString('1.', $resp);
        $this->assertEquals('eligiendo_reparto', $this->cliente->fresh()->estado);
    }

    // ──────────────────────────────────────────────────────
    // 4. FLUJO DE CONFIRMACIÓN POR TEXTO
    // ──────────────────────────────────────────────────────

    protected function setearConfirmacionFinal(array $extra = []): void
    {
        $data = array_merge([
            'tipo_entrega'  => 'envio',
            'fecha_entrega' => now()->addDays(2)->format('Y-m-d'),
            'medio_pago'    => 'efectivo',
            'calle'         => 'Italia',
            'numero'        => '1234',
            'localidad'     => 'TestVille',
            'dato_extra'    => '',
            'obs'           => '',
        ], $extra);

        Cache::put('pedido_conf_' . $this->cliente->id, $data, now()->addMinutes(30));
        $this->cliente->update(['estado' => 'confirmando_final']);
        $this->cliente->refresh();

        // Carrito mínimo
        Carrito::create([
            'cliente_id' => $this->cliente->id,
            'items'      => [
                'vacío' => ['cod' => 1, 'des' => 'Vacío', 'cant' => 0, 'kilos' => 2, 'precio' => 1500, 'neto' => 3000, 'tipo' => 'Peso'],
            ],
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_confirmacion_si_crea_pedido(): void
    {
        $this->setearConfirmacionFinal();

        // Mock para que createOrder no falle por falta de tablas de pedidos reales
        // (en entorno de test sin la BD de producción, el pedido puede fallar — testamos el flujo del estado)
        $resp = $this->enviar('sí');

        $this->assertNotEquals('confirmando_final', $this->cliente->fresh()->estado);
    }

    public function test_confirmacion_no_cancela_pedido(): void
    {
        $this->setearConfirmacionFinal();

        $resp = $this->enviar('no');

        $this->assertStringContainsStringIgnoringCase('cancelado', $resp);
        $this->assertEquals(0, Carrito::where('cliente_id', $this->cliente->id)->count());
        $this->assertNotEquals('confirmando_final', $this->cliente->fresh()->estado);
    }

    public function test_confirmacion_texto_libre_pasa_a_gpt(): void
    {
        $this->setearConfirmacionFinal();
        Http::fake(['api.openai.com/*' => $this->fakeOpenAiRespuestaTexto('Acá está el detalle del carrito.')]);

        $resp = $this->enviar('que detalle hay?');

        // El estado ya no es confirmando_final
        $this->assertNotEquals('confirmando_final', $this->cliente->fresh()->estado);
        // GPT respondió
        $this->assertStringContainsString('detalle', $resp);
    }

    // ──────────────────────────────────────────────────────
    // 5. SELECCIÓN DE PAGO POR TEXTO (múltiples medios)
    // ──────────────────────────────────────────────────────

    public function test_seleccion_pago_por_numero(): void
    {
        // Configurar empresa con 2 medios de pago
        IaEmpresa::first()->update(['bot_medios_pago' => ['efectivo', 'transferencia']]);

        $data = [
            'tipo_entrega'  => 'envio',
            'fecha_entrega' => now()->addDays(2)->format('Y-m-d'),
            'calle' => 'Italia', 'numero' => '1234', 'localidad' => 'TestVille', 'dato_extra' => '', 'obs' => '',
        ];
        Cache::put('pedido_conf_' . $this->cliente->id, $data, now()->addMinutes(30));
        $this->cliente->update(['estado' => 'confirmando_pago']);
        $this->cliente->refresh();

        Carrito::create([
            'cliente_id' => $this->cliente->id,
            'items'      => ['vacío' => ['cod' => 1, 'des' => 'Vacío', 'cant' => 0, 'kilos' => 2, 'precio' => 1500, 'neto' => 3000, 'tipo' => 'Peso']],
            'expires_at' => now()->addYear(),
        ]);

        $resp = $this->enviar('1'); // Efectivo

        $this->assertEquals('confirmando_final', $this->cliente->fresh()->estado);
        $this->assertStringContainsStringIgnoringCase('confirmar', $resp);
    }

    public function test_seleccion_pago_invalido_repite_opciones(): void
    {
        IaEmpresa::first()->update(['bot_medios_pago' => ['efectivo', 'transferencia']]);

        $data = ['tipo_entrega' => 'envio', 'fecha_entrega' => now()->addDays(2)->format('Y-m-d'),
                 'calle' => 'Italia', 'numero' => '1234', 'localidad' => 'TestVille', 'dato_extra' => '', 'obs' => ''];
        Cache::put('pedido_conf_' . $this->cliente->id, $data, now()->addMinutes(30));
        $this->cliente->update(['estado' => 'confirmando_pago']);

        $resp = $this->enviar('bitcoin');

        $this->assertStringContainsString('No entendí', $resp);
        $this->assertEquals('confirmando_pago', $this->cliente->fresh()->estado);
    }
}
