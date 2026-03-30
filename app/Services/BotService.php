<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Carrito;
use App\Models\IaEmpresa;
use App\Models\Factventas;
use App\Models\Localidad;
use App\Models\Pedidosia;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
use App\Models\Message;
use App\Models\Pedido;

class BotService
{
    public ?string $lastOutgoingWamid = null;

    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
    private const OPENAI_MODEL = 'gpt-4.1';

    // Precios por millón de tokens (USD) por modelo
    private const PRECIOS_OPENAI = [
        'gpt-4.1'      => ['input' => 2.00, 'output' => 8.00],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
        'gpt-4o'       => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini'  => ['input' => 0.15, 'output' => 0.60],
    ];

    private function whatsappKey(): string
    {
        return config('api.whatsapp.key');
    }
    private function openaiKey(): string
    {
        return config('api.openai.key');
    }
    private function phoneNumberId(): string
    {
        return config('api.whatsapp.phone_number_id');
    }

    /**
     * Usa la IA para extraer solo el dato relevante de un mensaje conversacional.
     * Ej: "soy Juan" → "Juan", "vivo en Italia 1234" → "Italia 1234"
     */
    private function extraerDatoConIA(string $tipo, string $input): string
    {
        $prompts = [
            'nombre' => 'El usuario respondió a la pregunta "¿Cuál es tu nombre?". '
                . 'Extrae únicamente el nombre propio de la respuesta, sin saludos ni explicaciones. '
                . 'Si dice "soy Juan" devuelve "Juan". Si dice "me llamo María García" devuelve "María García". '
                . 'Devuelve solo el nombre, nada más.',

            'calle' => 'El usuario respondió a la pregunta "¿Cuál es tu calle y número?". '
                . 'Extrae únicamente la calle y número de la respuesta, sin frases adicionales. '
                . 'Si dice "vivo en Italia 1234" devuelve "Italia 1234". Si dice "mi dirección es Av. Colón 500" devuelve "Av. Colón 500". '
                . 'Devuelve solo la dirección, nada más.',
        ];

        $systemPrompt = $prompts[$tipo] ?? 'Extrae el dato principal del mensaje y devuélvelo sin explicaciones.';

        try {
            $response = Http::withToken($this->openaiKey())
                ->timeout(10)
                ->post(self::OPENAI_URL, [
                    'model'       => 'gpt-4.1-mini',
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $input],
                    ],
                    'max_tokens'  => 50,
                    'temperature' => 0,
                ])
                ->json();

            $extraido = trim($response['choices'][0]['message']['content'] ?? '');
            return $extraido !== '' ? $extraido : $input;
        } catch (\Throwable $e) {
            Log::warning("extraerDatoConIA({$tipo}) falló: " . $e->getMessage());
            return $input;
        }
    }

    // -------------------------------------------------------------------------
    // Punto de entrada principal
    // -------------------------------------------------------------------------

    public function process($client, $message, ?array $image = null): string
    {
        // Cliente en modo humano: no procesar con IA
        if ($client->estado === 'humano') {
            return '';
        }

        // Cliente en flujo de confirmación interactiva (esperando botones)
        // Si escribe texto, cancelamos silenciosamente y procesamos el mensaje con IA normalmente
        if (str_starts_with($client->estado ?? '', 'confirmando_')) {
            Cache::forget('pedido_conf_' . $client->id);
            $client->update(['estado' => 'activo']);
            $client->refresh();
        }

        // Si estaba eligiendo reparto pero escribió texto, volver a normal
        if ($client->estado === 'eligiendo_reparto') {
            $client->update(['estado' => null]);
            $client->refresh();
        }

        // Registro inicial: nombre → localidad → provincia
        $estadosVerificacion = ['verificando_nombre', 'verificando_localidad', 'verificando_calle', 'esperando_localidad', 'esperando_calle', 'esperando_dato_extra'];
        if ((empty($client->name) || $client->estado === 'esperando_nombre') && !in_array($client->estado, $estadosVerificacion)) {
            if ($client->estado !== 'esperando_nombre') {
                $config   = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());
                $nombreIa = trim($config?->nombre_ia ?? '');
                $atiende  = $config?->bot_atiende_nuevos ?? 'bot';

                // Imagen de bienvenida primero (siempre, independiente de quién atiende)
                if (!empty($config?->imagen_bienvenida)) {
                    $imgTs = $config->updated_at ? $config->updated_at->timestamp : time();
                    $this->sendWhatsappImageByUrl($client->phone, url($config->imagen_bienvenida) . '?v=' . $imgTs);
                }

                if ($atiende === 'humano') {
                    // Solo saludo; no registramos ni derivamos al bot
                    $saludo = $nombreIa
                        ? "¡Hola! Soy {$nombreIa}. En breve alguien del equipo te va a atender. ¡Gracias por escribirnos!"
                        : "¡Hola! En breve alguien del equipo te va a atender. ¡Gracias por escribirnos!";
                    $client->update(['estado' => 'humano', 'modo' => 'humano']);
                    $this->sendReply($client, $saludo);
                    return $saludo;
                }

                $client->update(['estado' => 'esperando_nombre']);
                $saludo   = $nombreIa ? "¡Hola! Soy {$nombreIa}, el asistente del negocio." : "¡Hola! Soy el asistente del negocio.";
                $response = "{$saludo} ¿Cuál es tu nombre?";
                $this->sendReply($client, $response);
                return $response;
            } else {
                $nombre = ucwords(strtolower($this->extraerDatoConIA('nombre', trim($message))));
                Cache::put('reg_nombre_' . $client->id, $nombre, now()->addMinutes(15));
                $client->update(['estado' => 'verificando_nombre']);
                $response = "¿Tu nombre es *{$nombre}*? (respondé *sí* o *no*)";
            }
            $this->sendReply($client, $response);
            return $response;
        }

        if ($client->estado === 'verificando_nombre') {
            $input = strtolower(trim($message));
            if (preg_match('/^(s[ií]|si|yes|ok|correcto|dale|exacto)$/i', $input)) {
                $nombre = Cache::pull('reg_nombre_' . $client->id) ?? $input;
                $client->update(['name' => $nombre, 'estado' => 'esperando_localidad']);
                $localidades = Localidad::where('activo', true)->pluck('nombre')->implode(', ');
                $response = "¡Perfecto, {$nombre}! ¿En qué localidad estás?"
                    . ($localidades ? " (Repartimos en: {$localidades})" : '');
            } else {
                Cache::forget('reg_nombre_' . $client->id);
                $client->update(['estado' => 'esperando_nombre']);
                $response = "Sin problema, ¿cuál es tu nombre?";
            }
            $this->sendReply($client, $response);
            return $response;
        }

        if ($client->estado === 'esperando_localidad') {
            $input       = trim($message);
            $localidades = Localidad::where('activo', true)->get();

            $match = $localidades->first(
                fn($l) => stripos($l->nombre, $input) !== false || stripos($input, $l->nombre) !== false
            );

            if ($match) {
                Cache::put('reg_localidad_' . $client->id, ['nombre' => $match->nombre, 'id' => $match->id], now()->addMinutes(15));
                $client->update(['estado' => 'verificando_localidad']);
                $response = "¿Tu localidad es *{$match->nombre}*? (respondé *sí* o *no*)";
            } else {
                $localidadTexto = ucwords(strtolower($input));
                Cache::put('reg_localidad_' . $client->id, ['nombre' => $localidadTexto, 'id' => null], now()->addMinutes(15));
                $client->update(['estado' => 'verificando_localidad']);
                $response = "¿Tu localidad es *{$localidadTexto}*? (respondé *sí* o *no*)";
            }

            $this->sendReply($client, $response);
            return $response;
        }

        if ($client->estado === 'verificando_localidad') {
            $input = strtolower(trim($message));
            if (preg_match('/^(s[ií]|si|yes|ok|correcto|dale|exacto)$/i', $input)) {
                $data = Cache::pull('reg_localidad_' . $client->id) ?? [];
                $localidades = Localidad::where('activo', true)->get();
                $client->update([
                    'localidad'    => $data['nombre'] ?? '',
                    'localidad_id' => $data['id'] ?? null,
                    'estado'       => $data['id'] ? 'esperando_calle' : 'activo',
                ]);
                if ($data['id']) {
                    $match     = $localidades->firstWhere('id', $data['id']);
                    $diasLabel = IaEmpresa::DIAS_LABEL;
                    $dias      = $match->dias_reparto ?? [];
                    $diasTexto = !empty($dias)
                        ? 'Repartimos en tu zona los: ' . implode(', ', array_map(fn($d) => $diasLabel[$d], $dias)) . '. '
                        : '';
                    $response = "{$diasTexto}¿Cuál es tu calle y número de entrega? (ej: Italia 1234)";
                } else {
                    $lista    = $localidades->pluck('nombre')->implode(', ');
                    $response = "Anotado, {$client->name}. Por el momento no tenemos reparto a tu zona."
                        . ($lista ? " Repartimos en: {$lista}." : '')
                        . " Podés pasar a retirar al local. ¿En qué puedo ayudarte?";
                }
            } else {
                Cache::forget('reg_localidad_' . $client->id);
                $client->update(['estado' => 'esperando_localidad']);
                $localidades = Localidad::where('activo', true)->pluck('nombre')->implode(', ');
                $response = "Sin problema, ¿en qué localidad estás?"
                    . ($localidades ? " (Repartimos en: {$localidades})" : '');
            }
            $this->sendReply($client, $response);
            return $response;
        }

        if ($client->estado === 'esperando_calle') {
            // Extrae la dirección limpia y luego separa "Calle 1234" → calle=Calle, numero=1234
            $input = $this->extraerDatoConIA('calle', trim($message));
            if (preg_match('/^(.+?)\s+(\d[\w-]*)$/', $input, $m)) {
                $calle  = trim($m[1]);
                $numero = $m[2];
            } else {
                $calle  = $input;
                $numero = null;
            }
            $dirTexto = trim("{$calle} {$numero}");
            Cache::put('reg_calle_' . $client->id, ['calle' => $calle, 'numero' => $numero], now()->addMinutes(15));
            $client->update(['estado' => 'verificando_calle']);
            $response = "¿Tu dirección de entrega es *{$dirTexto}*? (respondé *sí* o *no*)";
            $this->sendReply($client, $response);
            return $response;
        }

        if ($client->estado === 'verificando_calle') {
            $input = strtolower(trim($message));
            if (preg_match('/^(s[ií]|si|yes|ok|correcto|dale|exacto)$/i', $input)) {
                $data = Cache::pull('reg_calle_' . $client->id) ?? [];
                $client->update([
                    'calle'  => $data['calle'] ?? '',
                    'numero' => $data['numero'] ?? null,
                    'estado' => 'esperando_dato_extra',
                ]);
                $response = "¿Tenés algún dato extra? (piso, depto, referencia) — respondé *no* para omitir.";
            } else {
                Cache::forget('reg_calle_' . $client->id);
                $client->update(['estado' => 'esperando_calle']);
                $response = "Sin problema, ¿cuál es tu calle y número de entrega? (ej: Italia 1234)";
            }
            $this->sendReply($client, $response);
            return $response;
        }

        if ($client->estado === 'esperando_dato_extra') {
            $input = trim($message);
            $datoExtra = preg_match('/^no$/i', $input) ? null : $input;
            $client->update(['dato_extra' => $datoExtra, 'estado' => 'activo']);
            $dir      = trim("{$client->calle} {$client->numero}");
            $response = "¡Todo listo, {$client->name}! Dirección guardada: {$dir}"
                . ($datoExtra ? " ({$datoExtra})" : '') . ". ¿En qué puedo ayudarte?";
            $this->sendReply($client, $response);
            return $response;
        }

        $response = $this->askChatGPT($message, $client, $image);
        if ($response !== '') {
            $this->sendReply($client, $response);
        }
        return $response;
    }

    // -------------------------------------------------------------------------
    // ChatGPT con Function Calling
    // -------------------------------------------------------------------------

    public function askChatGPT(string $message, $cliente, ?array $image = null): string
    {
        $messages = $this->buildMessages($message, $cliente, $image);
        $empresa  = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());
        $tools    = $this->tools($empresa);

        // Primera llamada: ChatGPT decide si responde o llama una función
        $response = $this->callOpenAI($messages, $tools);
        $choice   = $response['choices'][0];

        if ($choice['finish_reason'] === 'tool_calls') {
            $puedePedir = $empresa?->bot_puede_pedir ?? true;
            $result = $this->handleToolCalls($choice, $messages, $cliente, $tools, $puedePedir);

            // Si el cliente entró en flujo interactivo, los botones ya fueron enviados: no enviar texto adicional
            $cliente->refresh();
            if (str_starts_with($cliente->estado ?? '', 'confirmando_')) {
                return '';
            }

            $this->programarActualizacionMemoria($cliente);
            return $result;
        }

        $this->programarActualizacionMemoria($cliente);
        return $choice['message']['content'];
    }

    /**
     * Dispara el job de memoria con rate limiting: máximo una vez por hora por cliente.
     */
    private function programarActualizacionMemoria($cliente): void
    {
        $cacheKey = 'memoria_job_' . $cliente->id;
        if (Cache::has($cacheKey)) return;

        Cache::put($cacheKey, true, now()->addHour());
        \App\Jobs\ActualizarMemoriaCliente::dispatch($cliente->id)->delay(now()->addMinutes(5));
    }

    // -------------------------------------------------------------------------
    // Construcción del contexto para ChatGPT
    // -------------------------------------------------------------------------

    private function buildMessages(string $message, $cliente, ?array $image = null): array
    {
        $nombre  = $cliente->name ?? 'cliente';
        $codcli  = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
        $fecha   = now()->locale('es')->isoFormat('dddd D [de] MMMM YYYY');
        $empresa = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());

        $tenantId = app(\App\Services\TenantManager::class)->get()?->id ?? 0;

        // Sin caché: siempre fresco para reflejar cambios del catálogo inmediatamente
        $todosProductos = Producto::paraBot()->get();

        // ── Localidad y días de reparto (necesarios antes de filtrar productos) ──
        $diasLabel    = IaEmpresa::DIAS_LABEL;
        $localidadObj = $cliente->localidad_id
            ? Localidad::find($cliente->localidad_id)
            : ($cliente->localidad
                ? Localidad::where('activo', true)->whereRaw('LOWER(nombre) = ?', [strtolower($cliente->localidad)])->first()
                : null);
        if ($localidadObj && !$cliente->localidad_id) {
            $cliente->update(['localidad_id' => $localidadObj->id]);
        }
        $diasConfig      = $localidadObj ? $localidadObj->diasConfig() : [];
        $fechasCerradas  = $empresa?->bot_fechas_cerrado ?? [];
        $globalHoraCorte = $empresa?->bot_hora_corte ?? null;

        // Fechas de reparto disponibles — necesario antes de verificar caché de fecha elegida
        $fechasDisponibles = $this->getFechasReparto($diasConfig, $fechasCerradas, $globalHoraCorte);

        // Fecha de reparto elegida por el cliente
        $fechaElegida = Cache::get('fecha_reparto_elegida_' . $cliente->id);
        // Si la fecha cacheada ya no está entre las disponibles, limpiarla
        if ($fechaElegida && !collect($fechasDisponibles)->contains('fecha', $fechaElegida)) {
            Cache::forget('fecha_reparto_elegida_' . $cliente->id);
            $fechaElegida = null;
        }
        // Si hay una sola fecha disponible, seleccionarla automáticamente
        if (!$fechaElegida && count($fechasDisponibles) === 1) {
            $fechaElegida = $fechasDisponibles[0]['fecha'];
            Cache::put('fecha_reparto_elegida_' . $cliente->id, $fechaElegida, now()->addHours(6));
        }

        $diaElegido = $fechaElegida ? (int) \Carbon\Carbon::parse($fechaElegida)->format('w') : null;

        // ── Filtrado de productos por localidad y día ──
        $diasLabelCorto = [0=>'Dom',1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb'];
        $prodLocConfigs = $cliente->localidad_id
            ? ProductoLocalidad::where('localidad_id', $cliente->localidad_id)->get()->keyBy('cod')
            : collect();

        if ($cliente->localidad_id && $prodLocConfigs->isNotEmpty()) {
            // Solo productos configurados para esta localidad, y si hay día elegido también por ese día
            $productos = $todosProductos->filter(function ($p) use ($prodLocConfigs, $diaElegido) {
                if (!$prodLocConfigs->has($p->cod)) return false; // no configurado para esta localidad
                if ($diaElegido === null) return true;            // sin fecha elegida: mostrar todos los de la localidad
                // Con fecha elegida: verificar restricción de días
                $diasCfg = $prodLocConfigs->get($p->cod)->dias_reparto;
                if ($diasCfg === null) return true;              // sin restricción → disponible todos los días
                if (empty($diasCfg)) return false;               // array vacío → no disponible ningún día
                $diasNum = array_map(fn($d) => is_array($d) ? (int)$d['dia'] : (int)$d, $diasCfg);
                return in_array($diaElegido, $diasNum, true);
            });
        } else {
            $productos = $todosProductos;
        }

        // Para anotar días restringidos en la lista cuando no hay fecha elegida ("solo Lun/Vie")
        $prodLocDias = (!$diaElegido && $prodLocConfigs->isNotEmpty())
            ? $prodLocConfigs->filter(fn($c) => !empty($c->dias_reparto))
            : collect();

        $formatear = function ($p) use ($diaElegido, $prodLocDias, $diasLabelCorto) {
            $linea = $p->des;
            if (!empty($p->descripcion) && $p->descripcion !== 'sinimagen.webp') {
                $linea .= " ({$p->descripcion})";
            }
            if (!empty($p->notas_ia)) {
                $linea .= " [IA: {$p->notas_ia}]";
            }
            // Si no hay fecha elegida y el producto tiene días restringidos para esta localidad, anotarlo
            if (!$diaElegido && $prodLocDias->has($p->cod)) {
                $dias = $prodLocDias->get($p->cod)->dias_reparto ?? [];
                if (!empty($dias)) {
                    $nombres = array_map(fn($d) => $diasLabelCorto[is_array($d) ? (int)$d['dia'] : (int)$d] ?? '?', $dias);
                    $linea .= ' [solo ' . implode('/', $nombres) . ']';
                }
            }
            return $linea;
        };

        $bloques = [];
        foreach (['Unidad', 'Peso'] as $tipo) {
            $tipoLabel = $tipo === 'Unidad' ? 'POR UNIDAD' : 'POR KILO';
            $grupo = $productos->where('tipo', $tipo)
                ->groupBy(fn($p) => $p->desgrupo ?: 'Sin grupo');

            if ($grupo->isEmpty()) continue;

            $bloque = "[{$tipoLabel}]";
            foreach ($grupo as $nombreGrupo => $items) {
                $bloque .= "\n  [{$nombreGrupo}]\n";
                $bloque .= $items->map(fn($p) => "  " . $formatear($p))->implode("\n");
            }
            $bloques[] = $bloque;
        }

        $lista = implode("\n\n", $bloques);

        $ultimoPedido = Pedido::where('codcli', $codcli)->latest('reg')->first();
        $ultimoPedidoTexto = $ultimoPedido
            ? "#{$ultimoPedido->nro} ({$ultimoPedido->fecha}): {$ultimoPedido->descrip} — {$ultimoPedido->estado_texto}"
            : 'ninguno';

        // Top 3 productos más pedidos por este cliente (personalización)
        $topProductos = Pedido::where('codcli', $codcli)
            ->selectRaw('descrip, COUNT(*) as veces')
            ->groupBy('descrip')
            ->orderByDesc('veces')
            ->take(3)
            ->pluck('descrip')
            ->implode(', ');

        // Contexto de cuenta comercial si está vinculada
        $localidad   = $cliente->cuenta?->loca ?? $cliente->localidad ?? null;
        $provincia   = $cliente->cuenta?->prov ?? $cliente->provincia ?? null;
        $cuentaTexto = $cliente->cuenta
            ? "\nCuenta: {$cliente->cuenta->nom} | {$cliente->cuenta->dom}, {$cliente->cuenta->loca}"
            : ($localidad ? "\nLocalidad: {$localidad}" . ($provincia ? ", {$provincia}" : '') : '');

        // Dirección preferida: primero la del perfil del cliente, si no la última usada en pedido
        if ($cliente->calle) {
            $ultimaDirTexto = "Dirección guardada del cliente: {$cliente->calle} {$cliente->numero}"
                . ($cliente->dato_extra ? " ({$cliente->dato_extra})" : '')
                . ($cliente->localidad  ? ", {$cliente->localidad}"   : '');
        } else {
            $ultimaDir = Pedidosia::where('idcliente', $cliente->id)
                ->where('tipo_entrega', 'envio')
                ->whereNotNull('calle')
                ->latest()
                ->first();
            $ultimaDirTexto = $ultimaDir
                ? "Última dirección de envío usada: {$ultimaDir->calle} {$ultimaDir->numero}"
                . ($ultimaDir->localidad  ? ", {$ultimaDir->localidad}"  : '')
                . ($ultimaDir->dato_extra ? " ({$ultimaDir->dato_extra})" : '')
                : '';
        }

        // Historial de últimos 10 mensajes para mantener contexto del pedido
        $history = Message::where('cliente_id', $cliente->id)
            ->latest()
            ->take(20)
            ->get()
            ->reverse();

        $favoritosTexto = $topProductos
            ? "Lo que más pide: {$topProductos}."
            : '';

        $messages = [];

        // --- Configuración operativa del negocio ---
        $infoNegocio   = trim($empresa?->bot_info ?? '');
        $instrucciones = trim($empresa?->bot_instrucciones ?? '');

        // $diasLabel, $localidadObj, $diasConfig, $fechasCerradas, $globalHoraCorte, $fechasDisponibles
        // ya fueron computados arriba (antes del filtrado de productos)
        $diasReparto = array_map(fn($d) => (int) $d['dia'], $diasConfig);
        if (!empty($diasReparto)) {
            $diasNombres = implode(', ', array_map(fn($d) => $diasLabel[$d] ?? $d, $diasReparto));
            $diasTexto   = $localidad
                ? "Días de reparto para {$localidad}: {$diasNombres}."
                : "Días de reparto: {$diasNombres}.";
        } else {
            $diasTexto = $localidadObj === null && $cliente->localidad
                ? "No hay reparto configurado para {$cliente->localidad}. Solo retiro en local."
                : '';
        }

        // Top 5 productos más vendidos globalmente
        $masVendidosGlobal = Cache::remember(
            'bot_mas_vendidos_' . $tenantId,
            3600,
            fn() =>
            Pedido::selectRaw('descrip, COUNT(*) as veces')
                ->groupBy('descrip')
                ->orderByDesc('veces')
                ->take(5)
                ->pluck('descrip')
                ->implode(', ')
        );

        // Todas las zonas de entrega activas con sus días (sin caché para evitar datos rancios)
        $todasLasZonas = Localidad::where('activo', true)
            ->get()
            ->map(function ($l) use ($diasLabel) {
                $dias = !empty($l->dias_reparto)
                    ? implode(', ', array_map(fn($d) => $diasLabel[is_array($d) ? $d['dia'] : (int) $d] ?? $d, $l->dias_reparto))
                    : 'días a confirmar';
                return "{$l->nombre} (reparto los: {$dias})";
            })
            ->implode(' | ');

        // Tipos de entrega habilitados
        $permiteEnvio  = $empresa?->bot_permite_envio  ?? true;
        $permiteRetiro = $empresa?->bot_permite_retiro ?? true;
        $entregasTexto = 'Tipos de entrega disponibles: ' . implode(' y ', array_filter([
            $permiteEnvio  ? 'envío' : null,
            $permiteRetiro ? 'retiro en local'   : null,
        ])) . '.';
        if (!$permiteEnvio && !$permiteRetiro) {
            $entregasTexto = 'Por el momento no se aceptan pedidos (entrega deshabilitada).';
        }

        // Medios de pago habilitados
        $mediosHabilitados = $empresa?->bot_medios_pago ?? array_keys(IaEmpresa::MEDIOS_PAGO);
        $mediosLabel       = IaEmpresa::MEDIOS_PAGO;
        $mediosTexto       = 'Medios de pago aceptados: ' . implode(', ', array_map(fn($m) => $mediosLabel[$m] ?? $m, $mediosHabilitados)) . '.';

        // Horario y calendario del local
        // $fechasCerradas, $globalHoraCorte, $fechasDisponibles ya calculados arriba
        $botHorarios = $empresa?->bot_horarios ?? [];

        $proximoRepartoTexto = '';
        $proximoRepartoFecha = '';
        $corteAviso          = '';

        if (!empty($fechasDisponibles)) {
            $primero             = $fechasDisponibles[0];
            $proximoRepartoFecha = $primero['fecha'];
            $proximoRepartoTexto = $primero['texto'];
        } elseif (!empty($diasReparto)) {
            // Hay días configurados pero ninguno disponible ahora — determinar si es por cierre o por apertura futura
            $diasConfigMap = collect($diasConfig)->keyBy('dia');
            for ($i = 0; $i <= 14; $i++) {
                $candidato = now()->addDays($i);
                $diaSemana = (int) $candidato->format('w');
                if (!\in_array($diaSemana, $diasReparto, true)) continue;
                $cfg = $diasConfigMap->get($diaSemana);

                $tieneHasta = isset($cfg['hasta_dia']) && $cfg['hasta_dia'] !== null && $cfg['hasta_dia'] !== '';
                $tieneDesde = isset($cfg['desde_dia']) && $cfg['desde_dia'] !== null && $cfg['desde_dia'] !== '';

                if ($tieneHasta) {
                    $hastaNum  = (int) $cfg['hasta_dia'];
                    $hastaHora = !empty($cfg['hasta_hora']) ? $cfg['hasta_hora'] : '23:59';
                    $diff      = ($diaSemana - $hastaNum + 7) % 7 ?: 7;
                    $fechaCierre = $candidato->copy()->subDays($diff)->setTimeFromTimeString($hastaHora);
                    if (now()->gt($fechaCierre)) {
                        $corteAviso = "⚠️ CIERRE DE PEDIDOS: Los pedidos para el reparto del "
                            . $candidato->locale('es')->isoFormat('dddd D [de] MMMM')
                            . " cerraron el {$diasLabel[$hastaNum]} a las "
                            . \Carbon\Carbon::today()->setTimeFromTimeString($hastaHora)->format('H:i') . "hs.";
                        break;
                    }
                }

                if ($tieneDesde) {
                    $desdeNum  = (int) $cfg['desde_dia'];
                    $desdeHora = !empty($cfg['desde_hora']) ? $cfg['desde_hora'] : '00:00';
                    $diff      = ($diaSemana - $desdeNum + 7) % 7 ?: 7;
                    $fechaApertura = $candidato->copy()->subDays($diff)->setTimeFromTimeString($desdeHora);
                    if (now()->lt($fechaApertura)) {
                        $corteAviso = "⚠️ PEDIDOS CERRADOS: Todavía no se pueden tomar pedidos para el reparto del "
                            . $candidato->locale('es')->isoFormat('dddd D [de] MMMM')
                            . ". La ventana de pedidos abre el {$diasLabel[$desdeNum]} a las "
                            . \Carbon\Carbon::today()->setTimeFromTimeString($desdeHora)->format('H:i') . "hs.";
                        break;
                    }
                }
            }
        }

        // Guardar la fecha calculada en cache para que iniciarConfirmacionPedido use la misma
        if ($proximoRepartoFecha) {
            Cache::put('proxima_fecha_entrega_' . $cliente->id, $proximoRepartoFecha, now()->addMinutes(30));
        }

        // Texto de fechas disponibles para el prompt
        $hayMultiplesFechas = count($fechasDisponibles) > 1;
        $fechasTexto = '';
        if (!empty($fechasDisponibles)) {
            $fechasTexto = implode(', ', array_map(fn($f) => $f['texto'], $fechasDisponibles));
        }
        $fechaYaElegida     = $fechaElegida !== null;
        $fechaElegidaTexto  = $fechaYaElegida
            ? \Carbon\Carbon::parse($fechaElegida)->locale('es')->isoFormat('dddd D [de] MMMM')
            : null;

        // Texto dinámico para el paso 3 del flujo de pedido
        $entregasOpciones = array_filter([
            $permiteEnvio  ? 'envío' : null,
            $permiteRetiro ? 'retiro en local'   : null,
        ]);
        $mediosOpciones = array_map(fn($m) => $mediosLabel[$m] ?? $m, $mediosHabilitados);

        $paso3Fecha = $proximoRepartoTexto
            ? "Informale que el próximo reparto es el {$proximoRepartoTexto}. No menciones la fecha en formato numérico, solo el día y mes en texto."
            : '';
        $paso3Entrega = count($entregasOpciones) === 1
            ? '¿Te lo ' . (in_array('envío', $entregasOpciones) ? 'enviamos' : 'pasás a buscar') . '?'
            : '¿' . implode(' o ', array_map('ucfirst', $entregasOpciones)) . '?';
        $paso3Pago = '¿Cómo abonás? (' . implode(', ', $mediosOpciones) . ')';

        $puedePedir       = $empresa?->bot_puede_pedir        ?? true;
        $puedeSupgerir    = $empresa?->bot_puede_sugerir       ?? true;
        $puedeMasVendidos = $empresa?->bot_puede_mas_vendidos  ?? false;

        $configNegocio = "\n{$entregasTexto}\n{$mediosTexto}";
        if ($diasTexto)                       $configNegocio .= "\n{$diasTexto}";
        if ($todasLasZonas)                   $configNegocio .= "\nZonas de entrega disponibles: {$todasLasZonas}";
        if ($puedeMasVendidos && $masVendidosGlobal) $configNegocio .= "\nProductos más vendidos del negocio: {$masVendidosGlobal}";

        // Horario y calendario (por día, múltiples turnos)
        if (!empty($botHorarios)) {
            $horarioLineas = [];
            foreach (IaEmpresa::DIAS_LABEL as $num => $nombre) {
                $turnos = $botHorarios[(string)$num] ?? null;
                if (!empty($turnos)) {
                    $turnosStr     = implode(' y ', array_map(fn($t) => "{$t['de']} a {$t['a']}hs", $turnos));
                    $horarioLineas[] = "{$nombre}: {$turnosStr}";
                }
            }
            if ($horarioLineas) {
                $configNegocio .= "\nHorarios de atención:\n" . implode("\n", $horarioLineas);
            }
        }
        if (!empty($fechasCerradas)) {
            $cerradasFmt = implode(', ', array_map(
                fn($f) => \Carbon\Carbon::parse($f)->locale('es')->isoFormat('D [de] MMMM'),
                $fechasCerradas
            ));
            $configNegocio .= "\nFechas en que el local estará cerrado (sin entregas ni retiros): {$cerradasFmt}.";
        }

        if ($corteAviso)     $configNegocio .= "\n\n{$corteAviso}";
        if ($puedePedir && empty($fechasDisponibles) && !empty($diasReparto)) {
            $configNegocio .= "\n\nIMPORTANTE: En este momento NO hay repartos disponibles para tomar pedidos. No podés reservar ni armar pedidos hasta que abra la próxima ventana de pedidos. Si el cliente pregunta cuándo puede pedir, indicale la información del aviso de cierre/apertura de arriba.";
        }
        if (!$puedePedir)    $configNegocio .= "\n\nIMPORTANTE: No podés tomar pedidos. Solo informás precios y describís productos. Si el cliente quiere pedir, indicale que contacte al negocio directamente.";
        if (!$puedeSupgerir) $configNegocio .= "\nNo sugieras productos de forma proactiva. Solo respondé lo que el cliente consulte.";

        if ($infoNegocio)        $configNegocio .= "\n\nInformación del negocio:\n{$infoNegocio}";
        if ($instrucciones)      $configNegocio .= "\n\nInstrucciones especiales:\n{$instrucciones}";

        $memoria = trim($cliente->memoria_ia ?? '');
        if ($memoria)            $configNegocio .= "\n\n📝 Lo que sabés de este cliente (usalo para personalizar):\n{$memoria}";

        $nombreIa  = trim($empresa?->nombre_ia ?? '');
        $identidad = $nombreIa ? "Te llamás {$nombreIa}." : '';

        $messages[] = [
            'role'    => 'system',
            'content' => "Sos el asistente virtual del negocio. {$identidad} Amable, breve y directo. Respondé siempre en español argentino.
Respondés consultas sobre: pedidos, precios, productos, horarios, dirección, formas de pago, días de reparto y cualquier información del negocio que tengas disponible.
Para cualquier otra consulta ajena al negocio, decí amablemente que no podés ayudar con eso.
Formato de precios: NUNCA uses separador de miles. Usá coma para decimales solo si hay centavos. Ejemplos correctos: \$1500 | \$36000 | \$2800,50. Nunca: \$1.500,00 ni \$36,000 ni \$21000,00.
IMPORTANTE: Los datos de días de reparto, zonas, productos disponibles y precios que aparecen en este system prompt son siempre los correctos y actuales. Si el historial de conversación dice algo diferente sobre días de reparto, zonas o productos, ignoralo — el historial puede estar desactualizado.
Hoy es {$fecha}.
Cliente: {$nombre}{$cuentaTexto}
Último pedido: {$ultimoPedidoTexto}
{$favoritosTexto}
{$ultimaDirTexto}{$configNegocio}
" . ($fechaYaElegida
    ? "Reparto elegido: {$fechaElegidaTexto}. Los productos que ves son los disponibles para ese día."
      . ($hayMultiplesFechas ? " También hay repartos disponibles para: " . implode(', ', array_filter(array_map(fn($f) => $f['fecha'] !== $fechaElegida ? $f['texto'] : null, $fechasDisponibles))) . ". Si el cliente quiere cambiar de fecha, usá elegir_reparto." : "")
    : ($fechasTexto ? "Repartos disponibles: {$fechasTexto}." : '')) . "

Productos disponibles" . ($fechaYaElegida ? " para el reparto del {$fechaElegidaTexto}" : " (mostrá estos solo si el cliente ya eligió fecha de reparto — si no eligió, usá elegir_reparto primero)") . ":
{$lista}

" . ($puedeSupgerir ? "════════════════════════════════
FLUJO 1 — SUGERIR
════════════════════════════════
Activar cuando: el cliente saluda, no sabe qué quiere, pide recomendación o menciona una ocasión (asado, cumpleaños, etc.).
Pasos:
1. Si menciona una ocasión, calculá cantidades según las porciones estándar y mostrá solo productos de la lista disponible.
2. Si no menciona ocasión, sugerí sus favoritos que estén en la lista disponible (ignorá los que no estén en la lista)" . ($puedeMasVendidos ? " o los más populares" : "") . ".
3. Ofrecé achuras cuando sea pertinente (una sola vez, sin insistir).
" . ($puedePedir ? "4. Preguntá si agrega al carrito." : "4. Informá precios si el cliente los consulta. NO ofrezcas agregar al carrito ni procesar pedidos.") . "

Porciones estándar por persona:
- Por peso: asado de tira/vacío/costilla 0.500kg | entraña/colita 0.300kg | pollo 0.300kg | cerdo 0.300kg
- Por unidad: chorizo 1u (peso aprox en descripción) | morcilla 1u | hamburguesa 2u
  Cálculo kg desde unidades: personas × unidades_por_persona × peso_unitario (indicado en descripción del producto).

Sugerencias por ocasión — usá SOLO productos que estén en la lista:
- Parrillada: asado de tira, vacío, costillas, entraña, chorizos, morcilla, achuras.
- Pollo al horno: pollo entero o en presas. Tip: 180°C, 45 min/kg.
- Disco de arado: paleta, roast beef, osobuco, chorizos.
- Guiso/estofado: osobuco, paleta, roast beef, chorizos.
- Milanesas: nalga, peceto, bola de lomo. (1 kg ≈ 6-8 milanesas)
" : "") . "
" . ($puedePedir ? "════════════════════════════════
FLUJO 2 — TOMAR PEDIDO
════════════════════════════════
Activar cuando: el cliente quiere agregar productos o ya tiene algo en mente.
Pasos:
" . ($hayMultiplesFechas && !$fechaYaElegida ? "0. ANTES de mostrar productos o agregar al carrito, usá elegir_reparto para que el cliente elija para qué fecha quiere el pedido. Los productos varían según el día de reparto." : ($fechaYaElegida ? "0. El cliente ya eligió el reparto del {$fechaElegidaTexto}. Los productos de la lista son los disponibles para ese día." . ($hayMultiplesFechas ? " Si el cliente pregunta por otras fechas o quiere cambiar, usá elegir_reparto." : "") : "")) . "
1. En cuanto tenés producto + cantidad, llamá INMEDIATAMENTE agregar_al_carrito. No pidas confirmación extra ni resumas antes. Si el cliente dice si, dale, esta bien o similar con una cantidad implícita o explícita → accioná.
2. Podés agregar múltiples productos en una sola llamada a agregar_al_carrito.
3. Mostrá el resumen con ver_carrito. Si hay alertas de precio (⚠️), ofrecé actualizar y cuando el cliente confirme llamá actualizar_precios_carrito (NO ver_carrito). Si hay ❌ producto no disponible, ofrecé eliminar el ítem con agregar_al_carrito cantidad 0.
4. Cuando el carrito esté listo, preguntá si el cliente confirma. Luego llamá DIRECTAMENTE crear_pedido. NUNCA preguntes forma de pago ni tipo de entrega antes de llamar crear_pedido — el sistema envía botones interactivos para eso. Solo pasá tipo_entrega o forma_pago como argumento si el cliente los mencionó explícitamente en esta conversación.
5. Una vez creado el pedido (ves 'Botones enviados' en la respuesta del sistema), no envíes ningún mensaje: el cliente ya está viendo los botones de confirmación.
6. Si el cliente dice el horario o turno preferido, guardalo en obs (no en fecha_entrega).

IMPORTANTE: Nunca calcules precios ni totales manualmente. Los precios pueden incluir recargos por zona que solo el sistema conoce. Siempre usá ver_carrito para mostrar importes.

Reglas de cantidad para agregar_al_carrito:
- Producto POR PESO: pasá siempre kg. '3 vacío' → 3 kg. 'medio kilo' → 0.5.
- Producto POR UNIDAD: pasá cantidad entera de unidades.
- Producto POR PESO pedido en unidades: convertí solo si la descripción del producto indica el peso unitario (ej: 'aprox. 0.15kg c/u'). Calculá kg = unidades × peso e informale. Si no tiene ese dato, pedile que indique en kg.
- Producto POR UNIDAD pedido en kg: no convertís, indicale que se pide por unidad.
- El total es siempre aproximado para productos por peso. Recordáselo.
- Formato numérico argentino: el cliente puede escribir con punto para miles y coma para decimales. Interpretá correctamente: '1.500' = 1500 | '2,5' = 2.5 | '0,750' = 0.75 | '1.200,50' = 1200.5

NUNCA menciones un producto fuera de la lista. NUNCA inventes ni estimes precios.
Al llamar agregar_al_carrito, usá el nombre del producto EXACTAMENTE como aparece en la lista de productos disponibles. NUNCA inferas el nombre a partir del historial de conversación — el historial puede referenciar pedidos anteriores que no reflejan lo que el cliente pide ahora. Si el nombre que el cliente dice puede corresponder a varios productos de la lista, llamá ver_producto primero para que el cliente elija." : "════════════════════════════════
FLUJO 2 — SOLO INFORMAR (NO SE TOMAN PEDIDOS)
════════════════════════════════
El negocio NO acepta pedidos por este canal. Solo podés informar precios, describir productos y responder consultas.
NUNCA preguntes cantidad, NUNCA ofrezcas agregar al carrito, NUNCA insinúes que podés procesar un pedido.
Si el cliente quiere comprar, indicale amablemente que contacte al negocio directamente para hacer su pedido.") . "

NUNCA menciones un producto fuera de la lista. NUNCA inventes ni estimes precios.

" . ($puedePedir ? "════════════════════════════════
FLUJO 3 — INFORMAR ESTADO
════════════════════════════════
Activar cuando: el cliente pregunta por su pedido, estado, demora o si ya está listo.
Pasos:
1. Usá ver_pedidos para obtener el historial y estado actual.
2. Informá el estado de forma clara: pendiente (en preparación), finalizado (listo).
3. Si el pedido está pendiente, decile que le avisaremos por WhatsApp cuando esté listo.
4. Si quiere cancelar un pedido pendiente, usá cancelar_pedido." : "") . "

Herramientas disponibles:
" . ($puedePedir ? "- elegir_reparto → muestra las fechas de reparto disponibles para que el cliente elija. Usala antes de agregar productos cuando hay múltiples fechas disponibles, o si el cliente quiere cambiar la fecha ya elegida
- agregar_al_carrito → agregar/modificar ítems del carrito
- ver_carrito → resumen con totales, tiempo restante y validación de precios. SIEMPRE usá esta herramienta cuando el cliente pregunta por el total, precio o detalle de su carrito; nunca calcules precios manualmente
- vaciar_carrito → limpiar el carrito
- crear_pedido → confirmar y registrar el pedido
- ver_pedidos → historial y estado de pedidos
- cancelar_pedido → cancelar un pedido pendiente
- editar_pedido → cargar los ítems de un pedido pendiente al carrito para modificarlo; luego confirmar con crear_pedido
" : "") . "- ver_precios → lista de precios actualizada (mostrala tal cual, sin reformatear)
- ver_producto → detalle e imagen de un producto específico. Usá esta herramienta cuando el cliente pregunta por un producto (disponibilidad, precio, descripción, si hay X, cómo es el X). NUNCA respondas sobre un producto puntual sin llamar primero a esta herramienta. Los precios del historial pueden estar desactualizados — usá siempre ver_producto para el precio real.
" . ($puedePedir ? "- Cuando el cliente responde afirmativamente ('sí', 'dale', 'sí quiero', etc.) luego de que se le mostró un producto: NO llamés ver_producto. Preguntale directamente la cantidad y llamá agregar_al_carrito con lo que confirme. Si ya dijo la cantidad, llamá agregar_al_carrito directamente.
" : "") . "- Si recibís una imagen, describila e intentá relacionarla con un pedido.",
        ];

        foreach ($history as $msg) {
            $content = $msg->message ?: '(imagen)';
            // En mensajes del bot, borrar precios de productos (pueden estar desactualizados)
            // pero NO en resúmenes de pedidos (Pedido #, Total:, Estado:) que son históricos y correctos
            if ($msg->direction === 'outgoing' && !preg_match('/Pedido\s*#|Total:|Estado:|Subtotal:/i', $content)) {
                // Eliminar frases con precio para que GPT no las repita ni use precios viejos
                $content = preg_replace('/cuesta\s+\$[\d\.,]+[^.]*\./i', 'ya fue informado al cliente.', $content);
                $content = preg_replace('/\$[\d\.,]+\s*(?:\/\s*(?:kg|u|unidad|por\s+\w+))?/i', '', $content);
                // Eliminar cualquier oración que mezcle días de la semana con palabras de reparto/pedido
                // (pueden estar desactualizadas — la info correcta siempre viene del system prompt)
                $diasPattern = 'lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo';
                $repartoPattern = 'repart|pedido|habilitad|ventana|abre|cierra|tomar|disponible';
                $content = preg_replace_callback(
                    '/[^.!?\n]*(?:' . $diasPattern . ')[^.!?\n]*(?:' . $repartoPattern . ')[^.!?\n]*[.!?]?/iu',
                    fn($m) => '[días de reparto actualizados]',
                    $content
                );
                $content = preg_replace_callback(
                    '/[^.!?\n]*(?:' . $repartoPattern . ')[^.!?\n]*(?:' . $diasPattern . ')[^.!?\n]*[.!?]?/iu',
                    fn($m) => '[días de reparto actualizados]',
                    $content
                );
            }
            $messages[] = [
                'role'    => $msg->direction === 'incoming' ? 'user' : 'assistant',
                'content' => $content,
            ];
        }

        if ($image) {
            $userContent = [];
            if ($message) {
                $userContent[] = ['type' => 'text', 'text' => $message];
            }
            $userContent[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => "data:{$image['mime']};base64,{$image['base64']}", 'detail' => 'auto'],
            ];
            $messages[] = ['role' => 'user', 'content' => $userContent];
        } else {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        return $messages;
    }

    // Descarga un archivo de WhatsApp y lo devuelve como base64
    public function downloadWhatsappMedia(string $mediaId): array
    {
        $waToken = $this->whatsappKey();

        $meta = Http::withToken($waToken)
            ->get("https://graph.facebook.com/v19.0/{$mediaId}")
            ->json();

        $url  = $meta['url']  ?? null;
        $mime = $meta['mime_type'] ?? 'image/jpeg';

        if (!$url) {
            throw new \RuntimeException('No se pudo obtener la URL de la imagen.');
        }

        $content = Http::withToken($waToken)->get($url)->body();

        return [
            'base64' => base64_encode($content),
            'mime'   => $mime,
        ];
    }

    // -------------------------------------------------------------------------
    // Definición de herramientas (functions)
    // -------------------------------------------------------------------------

    private function tools($empresa = null): array
    {
        $puedePedir = $empresa?->bot_puede_pedir ?? true;

        // Enums dinámicos según configuración
        $tiposEntrega = array_values(array_filter([
            ($empresa?->bot_permite_envio  ?? true) ? 'envio'  : null,
            ($empresa?->bot_permite_retiro ?? true) ? 'retiro' : null,
        ]));
        if (empty($tiposEntrega)) $tiposEntrega = ['retiro'];

        $mediosPago = $empresa?->bot_medios_pago ?? ['efectivo', 'transferencia', 'cuenta_corriente', 'otro'];

        // Si no puede pedir, solo expone las tools de consulta
        if (!$puedePedir) {
            return array_values(array_filter($this->allTools($tiposEntrega, $mediosPago), function ($tool) {
                return in_array($tool['function']['name'], ['ver_producto', 'lista_productos', 'consultar_saldo']);
            }));
        }

        return $this->allTools($tiposEntrega, $mediosPago);
    }

    private function allTools(array $tiposEntrega, array $mediosPago): array
    {
        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'elegir_reparto',
                    'description' => 'Muestra las fechas de reparto disponibles y permite al cliente elegir para cuál quiere hacer el pedido. La lista de productos se actualiza según el día elegido. Usala cuando el cliente quiere hacer un pedido y hay múltiples fechas disponibles, o cuando el cliente quiere cambiar la fecha de reparto ya elegida.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'agregar_al_carrito',
                    'description' => 'Agrega o actualiza productos en el carrito del cliente. El sistema calcula automáticamente kg, precio y neto. Usalo siempre que el cliente quiera productos o cuando sugerís una recomendación. Para quitar un producto, pasá cantidad 0.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'items' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'descrip'  => ['type' => 'string',  'description' => 'Nombre del producto tal como aparece en la lista.'],
                                        'cantidad' => ['type' => 'number',  'description' => 'Unidades si es por unidad (ej: 6). Kg si es por peso (ej: 2.5). Para peso vendido en unidades (chorizo, morcilla) pasá las unidades y el sistema convierte.'],
                                    ],
                                    'required' => ['descrip', 'cantidad'],
                                ],
                            ],
                        ],
                        'required' => ['items'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'ver_carrito',
                    'description' => 'Muestra el contenido actual del carrito con precios y total calculados por el sistema.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'vaciar_carrito',
                    'description' => 'Vacía completamente el carrito del cliente.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'crear_pedido',
                    'description' => 'Guarda en el sistema el pedido con los productos que están en el carrito. Llamalo solo cuando el cliente confirmó el carrito y respondió cómo recibe el pedido y cómo abona.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'fecha_entrega' => [
                                'type'        => 'string',
                                'description' => 'Fecha de entrega en formato Y-m-d (ej: 2026-03-20). Solo la fecha, sin hora.',
                            ],
                            'tipo_entrega' => [
                                'type'        => 'string',
                                'enum'        => $tiposEntrega,
                                'description' => 'envio: se lleva al cliente (domicilio, local comercial, etc.). retiro: el cliente pasa a buscar.',
                            ],
                            'forma_pago' => [
                                'type'        => 'string',
                                'enum'        => $mediosPago,
                                'description' => 'Cómo abona el cliente.',
                            ],
                            'calle' => [
                                'type'        => 'string',
                                'description' => 'Calle del domicilio de entrega. Solo si tipo_entrega es envio.',
                            ],
                            'numero' => [
                                'type'        => 'string',
                                'description' => 'Número de la calle del domicilio. Solo si tipo_entrega es envio.',
                            ],
                            'localidad' => [
                                'type'        => 'string',
                                'description' => 'Localidad del domicilio de entrega. Solo si tipo_entrega es envio.',
                            ],
                            'dato_extra' => [
                                'type'        => 'string',
                                'description' => 'Dato adicional del domicilio: piso, depto, referencia, etc. Opcional.',
                            ],
                            'obs' => [
                                'type'        => 'string',
                                'description' => 'Observaciones adicionales: horario, turno, corte especial, etc.',
                            ],
                        ],
                        'required' => ['fecha_entrega', 'tipo_entrega', 'forma_pago'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'ver_pedidos',
                    'description' => 'Muestra el historial y estado de los últimos pedidos del cliente. Para pedidos finalizados incluye el detalle real de la factura (kilos, precio unitario, total) y el número de comprobante.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'ver_precios',
                    'description' => 'Muestra la lista de precios de productos disponibles.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'actualizar_precios_carrito',
                    'description' => 'Actualiza todos los precios del carrito a los valores actuales. Usá esta herramienta cuando el cliente confirma que quiere actualizar los precios (después de ver alertas ⚠️). NUNCA uses ver_carrito para actualizar precios.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'ver_producto',
                    'description' => 'Muestra los detalles y la imagen de un producto cuando el cliente pregunta por él específicamente.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nombre'          => ['type' => 'string',  'description' => 'Nombre del producto tal como aparece en la lista.'],
                            'solicita_precio' => ['type' => 'boolean', 'description' => 'true si el cliente preguntó explícitamente por el precio o costo del producto. false si solo preguntó por disponibilidad, descripción o imagen.'],
                        ],
                        'required' => ['nombre', 'solicita_precio'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'cancelar_pedido',
                    'description' => 'Cancela un pedido pendiente del cliente.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nro' => ['type' => 'integer', 'description' => 'Número de pedido a cancelar.'],
                        ],
                        'required' => ['nro'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'editar_pedido',
                    'description' => 'Carga los ítems de un pedido pendiente al carrito para que el cliente pueda agregar, quitar o modificar productos. Usá esta herramienta cuando el cliente quiera modificar o agregar algo a un pedido que ya hizo. Luego el cliente puede ajustar el carrito y confirmar con crear_pedido, que reemplazará el pedido original.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nro' => ['type' => 'integer', 'description' => 'Número de pedido pendiente a editar.'],
                        ],
                        'required' => ['nro'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'consultar_saldo',
                    'description' => 'Muestra el saldo actual de la cuenta corriente del cliente (suma de débitos menos créditos). Usala cuando el cliente pregunta por su saldo, deuda, cuenta corriente o cuánto debe.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Ejecución de la función elegida por ChatGPT
    // -------------------------------------------------------------------------

    private function handleToolCalls(array $choice, array $messages, $cliente, array $tools = [], bool $puedePedir = true): string
    {
        for ($round = 0; $round < 5; $round++) {
            // Agregar el mensaje del asistente con todos sus tool_calls
            $messages[] = $choice['message'];

            // Responder a cada tool_call (OpenAI exige respuesta para cada ID)
            foreach ($choice['message']['tool_calls'] as $toolCall) {
                $funcName = $toolCall['function']['name'];
                $args     = json_decode($toolCall['function']['arguments'], true) ?? [];

                Log::info("toolCall [{$funcName}]", ['args' => $args]);

                try {
                    $result = match ($funcName) {
                        'elegir_reparto'             => $this->elegirReparto($cliente),
                        'agregar_al_carrito'         => $this->agregarAlCarrito($cliente, $args['items'] ?? []),
                        'ver_carrito'                => $this->verCarrito($cliente),
                        'vaciar_carrito'             => $this->vaciarCarrito($cliente),
                        'crear_pedido'               => $this->iniciarConfirmacionPedido($cliente, $args),
                        'ver_pedidos'                => $this->orderStatus($cliente),
                        'ver_precios'                => $this->priceList($cliente),
                        'ver_producto'               => $this->verProducto($cliente, $args['nombre'] ?? '', (bool) ($args['solicita_precio'] ?? false), $puedePedir),
                        'cancelar_pedido'            => $this->cancelOrder($cliente, (int) $this->parsearNumero($args['nro'] ?? 0)),
                        'editar_pedido'              => $this->editOrder($cliente, (int) $this->parsearNumero($args['nro'] ?? 0)),
                        'actualizar_precios_carrito' => $this->actualizarPreciosCarrito($cliente),
                        'consultar_saldo'            => $this->consultarSaldo($cliente),
                        default                      => 'Función desconocida.',
                    };
                } catch (\Throwable $e) {
                    Log::error("handleToolCalls [{$funcName}] error: " . $e->getMessage());
                    $result = 'Error interno al ejecutar la función. Informale al cliente que hubo un problema técnico y que intente nuevamente en unos minutos.';

                }

                Log::info("toolCall [{$funcName}] result", ['result' => substr($result, 0, 300)]);

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content'      => $result,
                ];
            }

            // Siguiente llamada: GPT puede responder con texto o encadenar más tools
            $response = $this->callOpenAI($messages, $tools);
            $choice   = $response['choices'][0];

            if ($choice['finish_reason'] !== 'tool_calls') {
                break;
            }
        }

        return $choice['message']['content'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Acciones del negocio
    // -------------------------------------------------------------------------

    private function consultarSaldo($client): string
    {
        $codCuenta = $client->cuenta_cod;

        if (empty($codCuenta)) {
            return 'El cliente no tiene una cuenta corriente asociada. No se puede consultar el saldo.';
        }

        $row = DB::table('ctacteclie')
            ->where('nrocuenta', $codCuenta)
            ->selectRaw('SUM(debe) as total_debe, SUM(haber) as total_haber')
            ->first();

        $debe  = (float) ($row->total_debe  ?? 0);
        $haber = (float) ($row->total_haber ?? 0);
        $saldo = $debe - $haber;

        $saldoFormateado = number_format(abs($saldo), 2, ',', '.');

        $instruccion = ' [Mostrá solo este dato, sin agregar comentarios, sugerencias ni ofrecer formas de pago.]';

        if ($saldo > 0) {
            return "Saldo de cuenta corriente (cuenta {$codCuenta}): debe \${$saldoFormateado}.{$instruccion}";
        } elseif ($saldo < 0) {
            return "Saldo de cuenta corriente (cuenta {$codCuenta}): tiene saldo a favor de \${$saldoFormateado}.{$instruccion}";
        } else {
            return "Saldo de cuenta corriente (cuenta {$codCuenta}): cuenta al día, sin saldo pendiente.{$instruccion}";
        }
    }

    // -------------------------------------------------------------------------
    // Carrito
    // -------------------------------------------------------------------------


    private function getCarrito($client): ?Carrito
    {
        return Carrito::where('cliente_id', $client->id)
            ->latest()
            ->first();
    }

    private function agregarAlCarrito($client, array $items): string
    {
        $registro    = $this->getCarrito($client);
        $carrito     = $registro ? $registro->items : [];
        $productos   = Producto::paraBot()->get();
        $localPrices = $this->getLocalPrices($client);
        $normalize  = fn(string $s) => trim(strtolower(\Illuminate\Support\Str::ascii($s)));
        $errores    = [];

        Log::info('agregarAlCarrito items recibidos', ['items' => $items, 'cliente' => $client->id]);

        foreach ($items as $item) {
            $descrip  = trim($item['descrip'] ?? '');
            $cantidad = $this->parsearNumero($item['cantidad'] ?? 0);

            Log::info("agregarAlCarrito procesando", [
                'descrip'   => $descrip,
                'cantidad'  => $cantidad,
                'normalize' => $normalize($descrip),
            ]);

            if ($descrip === '') {
                Log::warning('agregarAlCarrito: descrip vacío, se omite');
                continue;
            }

            // 1. Coincidencia exacta normalizada
            $match = $productos->first(fn($p) => $normalize($p->des) === $normalize($descrip));

            // 2. Fuzzy: busca el producto con mayor similitud (mínimo 70%)
            if (!$match) {
                $mejorPct  = 0;
                $mejorProd = null;
                foreach ($productos as $p) {
                    similar_text($normalize($p->des), $normalize($descrip), $pct);
                    if ($pct > $mejorPct) {
                        $mejorPct  = $pct;
                        $mejorProd = $p;
                    }
                }
                if ($mejorPct >= 70) {
                    Log::info("agregarAlCarrito: fuzzy match '{$descrip}' → '{$mejorProd->des}' ({$mejorPct}%)");
                    $match = $mejorProd;
                }
            }

            if (!$match) {
                Log::warning("agregarAlCarrito: '{$descrip}' no encontrado (mejor similitud insuficiente)", [
                    'normalize_buscado' => $normalize($descrip),
                    'total_productos'   => $productos->count(),
                ]);
                $errores[] = "Producto '{$descrip}' no encontrado en la lista. Llamá ver_producto para encontrar el nombre exacto correcto antes de agregar.";
                continue;
            }

            Log::info("agregarAlCarrito: match encontrado '{$match->des}'", [
                'cod'   => $match->cod,
                'tipo'  => $match->tipo,
                'precio'=> $match->precio,
            ]);

            // Verificar disponibilidad para la localidad y día de reparto elegido
            if ($cantidad > 0 && $client->localidad_id && $localPrices->isNotEmpty()) {
                // Producto no configurado para esta localidad → no disponible
                if (!$localPrices->has($match->cod)) {
                    $errores[] = "'{$match->des}' no está disponible para tu localidad. Solo podés pedir los productos del listado. Informale al cliente.";
                    continue;
                }
                // Verificar disponibilidad para el día elegido
                $fechaElegida = Cache::get('fecha_reparto_elegida_' . $client->id);
                if ($fechaElegida) {
                    $diaElegido = (int) \Carbon\Carbon::parse($fechaElegida)->format('w');
                    $diasCfg    = $localPrices->get($match->cod)->dias_reparto;
                    $disponible = true;
                    if ($diasCfg !== null) {
                        if (empty($diasCfg)) {
                            $disponible = false;
                        } else {
                            $diasNum    = array_map(fn($d) => is_array($d) ? (int)$d['dia'] : (int)$d, $diasCfg);
                            $disponible = in_array($diaElegido, $diasNum, true);
                        }
                    }
                    if (!$disponible) {
                        $diasLabel   = IaEmpresa::DIAS_LABEL;
                        $diasNombres = empty($diasCfg)
                            ? 'ningún día de reparto'
                            : implode(', ', array_map(fn($d) => $diasLabel[is_array($d) ? (int)$d['dia'] : (int)$d] ?? '?', $diasCfg));
                        $textoFecha  = \Carbon\Carbon::parse($fechaElegida)->locale('es')->isoFormat('dddd D [de] MMMM');
                        $errores[]   = "'{$match->des}' no se reparte para el {$textoFecha}. Disponible los: {$diasNombres}. Informale al cliente que no puede agregar ese producto para ese reparto.";
                        continue;
                    }
                }
            }

            // notas_ia con "precio fijo" → se cobra por unidad aunque tipo sea Peso
            $precioFijo = !empty($match->notas_ia) && stripos($match->notas_ia, 'precio fijo') !== false;
            $esPeso     = $match->tipo !== 'Unidad' && !$precioFijo;
            $precio     = $this->precioFinal((float) $match->precio, $match->cod, $localPrices);

            $cant  = 0;
            $kilos = 0;

            if ($esPeso) {
                // Si notas_ia o descripción tienen peso por unidad (ej: "10kg c/u")
                // y la cantidad es un entero, convierte unidades → kg
                $pesoUnitario = $this->parsePesoUnitario($match->notas_ia ?? '')
                             ?? $this->parsePesoUnitario($match->descripcion ?? '');
                if ($pesoUnitario && $cantidad >= 1 && floor($cantidad) === $cantidad) {
                    $cant  = (int) $cantidad;
                    $kilos = round($cantidad * $pesoUnitario, 3);
                } else {
                    $kilos = $cantidad;
                }
            } else {
                // Precio fijo o tipo Unidad: se cobra por cantidad entera
                $cant = max(1, (int) $cantidad);
            }

            $base = $esPeso ? $kilos : $cant;
            $neto = round($precio * $base, 2);

            $key = mb_strtolower($match->des);

            if ($cantidad <= 0) {
                unset($carrito[$key]);
            } else {
                $carrito[$key] = [
                    'cod'    => $match->cod,
                    'des'    => $match->des,
                    'cant'   => $cant,
                    'kilos'  => $kilos,
                    'precio' => $precio,
                    'neto'   => $neto,
                    'tipo'   => $match->tipo,
                ];
            }
        }

        $lock = Cache::lock('carrito_write_' . $client->id, 10);
        $lock->block(5);
        try {
            DB::transaction(function () use ($client, $carrito, &$registro) {
                // Obtener TODOS los carritos activos y quedarse solo con el último
                $activos = Carrito::where('cliente_id', $client->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();

                if ($activos->isNotEmpty()) {
                    // Limpiar duplicados: conservar solo el más reciente
                    Carrito::whereIn('id', $activos->slice(1)->pluck('id'))->delete();
                    $activos->first()->update([
                        'items'      => $carrito,
                        'expires_at' => now()->addYear(),
                    ]);
                    $registro = $activos->first();
                } else {
                    $registro = Carrito::create([
                        'cliente_id' => $client->id,
                        'items'      => $carrito,
                        'expires_at' => now()->addYear(),
                    ]);
                }
            });
        } finally {
            $lock->release();
        }

        $resultado = $this->formatCarrito($carrito);

        if (!empty($errores)) {
            $resultado .= "\n\n" . implode("\n", $errores);
        }

        return $resultado;
    }

    private function verCarrito($client): string
    {
        $registro = $this->getCarrito($client);

        if (!$registro || empty($registro->items)) {
            return 'El carrito está vacío.';
        }

        $localPrices = $this->getLocalPrices($client);

        $resultado = $this->formatCarrito($registro->items);

        // Validar existencia y precios actuales
        $alertas = $this->validarCarrito($registro->items, $localPrices);
        if (!empty($alertas)) {
            $resultado .= "\n\n" . implode("\n", $alertas);
            $resultado .= "\n\nPodés actualizar los precios o eliminar los productos con problema antes de confirmar.";
        }

        return $resultado;
    }

    private function vaciarCarrito($client): string
    {
        Carrito::where('cliente_id', $client->id)->delete();
        return 'Carrito vaciado.';
    }

    private function actualizarPreciosCarrito($client): string
    {
        $registro = $this->getCarrito($client);
        if (!$registro || empty($registro->items)) {
            return 'El carrito está vacío.';
        }

        $localPrices = $this->getLocalPrices($client);
        $cods        = array_column($registro->items, 'cod');
        $productos   = Producto::paraBot()
            ->whereIn('tablaplu.cod', $cods)
            ->get()
            ->keyBy('cod');

        $carrito      = $registro->items;
        $actualizados = [];

        foreach ($carrito as $key => $item) {
            if (!isset($item['cod']) || !$productos->has($item['cod'])) {
                continue;
            }
            $precioNuevo = $this->precioFinal((float) $productos[$item['cod']]->precio, $item['cod'], $localPrices);
            if (abs($precioNuevo - (float) $item['precio']) > 0.01) {
                $actualizados[] = $item['des'];
            }
            $base              = $item['tipo'] !== 'Unidad' ? $item['kilos'] : $item['cant'];
            $carrito[$key]['precio'] = $precioNuevo;
            $carrito[$key]['neto']   = round($precioNuevo * $base, 2);
        }

        $registro->update(['items' => $carrito, 'expires_at' => now()->addYear()]);

        $resultado = $this->formatCarrito($carrito);
        $prefijo   = !empty($actualizados)
            ? '✅ Precios actualizados: ' . implode(', ', $actualizados) . ".\n\n"
            : "Los precios ya estaban al día.\n\n";

        return $prefijo . $resultado;
    }

    /**
     * Carga los precios/días override por producto para la localidad del cliente.
     * Retorna colección keyed by cod, o colección vacía si el cliente no tiene localidad.
     */
    /**
     * Devuelve las fechas de reparto disponibles (ventana 21 días) ordenadas.
     * Cada elemento: ['fecha'=>'Y-m-d', 'dia'=>int(0=dom), 'texto'=>'miércoles 2 de abril']
     */
    private function getFechasReparto(array $diasConfig, array $fechasCerradas = [], ?string $globalHoraCorte = null): array
    {
        if (empty($diasConfig)) return [];

        $diasLabel     = IaEmpresa::DIAS_LABEL;
        $diasReparto   = array_map(fn($d) => (int) $d['dia'], $diasConfig);
        $diasConfigMap = collect($diasConfig)->keyBy('dia');
        $disponibles   = [];

        for ($i = 0; $i <= 21; $i++) {
            $candidato = now()->addDays($i);
            $diaSemana = (int) $candidato->format('w');
            $fechaStr  = $candidato->format('Y-m-d');

            if (!\in_array($diaSemana, $diasReparto, true)) continue;
            if (\in_array($fechaStr, $fechasCerradas, true)) continue;

            $cfg             = $diasConfigMap->get($diaSemana);
            $dentroDeVentana = true;

            $tieneHasta = isset($cfg['hasta_dia']) && $cfg['hasta_dia'] !== null && $cfg['hasta_dia'] !== '';
            $tieneDesde = isset($cfg['desde_dia']) && $cfg['desde_dia'] !== null && $cfg['desde_dia'] !== '';

            if ($tieneHasta || !empty($cfg['hasta_hora'])) {
                $hastaNum  = $tieneHasta ? (int) $cfg['hasta_dia'] : null;
                $hastaHora = !empty($cfg['hasta_hora']) ? $cfg['hasta_hora'] : '23:59';
                if ($hastaNum !== null) {
                    $diff        = ($diaSemana - $hastaNum + 7) % 7 ?: 7;
                    $fechaCierre = $candidato->copy()->subDays($diff)->setTimeFromTimeString($hastaHora);
                    if (now()->gt($fechaCierre)) $dentroDeVentana = false;
                }
            } elseif ($globalHoraCorte && $i === 0) {
                $corteHoy = \Carbon\Carbon::today()->setTimeFromTimeString($globalHoraCorte);
                if (now()->gt($corteHoy)) $dentroDeVentana = false;
            }

            if ($dentroDeVentana && ($tieneDesde || !empty($cfg['desde_hora']))) {
                $desdeNum  = $tieneDesde ? (int) $cfg['desde_dia'] : null;
                $desdeHora = !empty($cfg['desde_hora']) ? $cfg['desde_hora'] : '00:00';
                if ($desdeNum !== null) {
                    $diff          = ($diaSemana - $desdeNum + 7) % 7 ?: 7;
                    $fechaApertura = $candidato->copy()->subDays($diff)->setTimeFromTimeString($desdeHora);
                    if (now()->lt($fechaApertura)) $dentroDeVentana = false;
                }
            }

            if ($dentroDeVentana) {
                $disponibles[] = [
                    'fecha' => $fechaStr,
                    'dia'   => $diaSemana,
                    'texto' => $candidato->locale('es')->isoFormat('dddd D [de] MMMM'),
                ];
            }
        }

        return $disponibles;
    }

    /**
     * Muestra las fechas de reparto disponibles via botones interactivos.
     * El cliente elige y el sistema guarda la fecha en cache.
     */
    private function elegirReparto($client): string
    {
        $empresa    = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());
        $localidadObj = $client->localidad_id ? Localidad::find($client->localidad_id) : null;
        if (!$localidadObj && $client->localidad) {
            $localidadObj = Localidad::where('activo', true)
                ->whereRaw('LOWER(nombre) = ?', [strtolower($client->localidad)])
                ->first();
        }

        $diasConfig        = $localidadObj ? $localidadObj->diasConfig() : [];
        $fechasCerradas    = $empresa?->bot_fechas_cerrado ?? [];
        $globalHoraCorte   = $empresa?->bot_hora_corte ?? null;
        $fechasDisponibles = $this->getFechasReparto($diasConfig, $fechasCerradas, $globalHoraCorte);

        if (empty($fechasDisponibles)) {
            return 'No hay fechas de reparto disponibles en este momento para tu localidad.';
        }

        if (count($fechasDisponibles) === 1) {
            // Solo una opción: elegir automáticamente
            $f = $fechasDisponibles[0];
            Cache::put('fecha_reparto_elegida_' . $client->id, $f['fecha'], now()->addHours(6));
            Cache::put('proxima_fecha_entrega_' . $client->id, $f['fecha'], now()->addMinutes(30));
            return "El próximo reparto es el {$f['texto']}. Podés pedirme los productos que querés para ese día.";
        }

        // Múltiples fechas: enviar botones (máximo 3 por limitación de WhatsApp)
        $botones = array_map(fn($f) => [
            'id'    => 'reparto_' . $f['fecha'],
            'title' => ucfirst($f['texto']),
        ], array_slice($fechasDisponibles, 0, 3));

        $client->update(['estado' => 'eligiendo_reparto']);
        $this->sendInteractiveButtons(
            $client->phone,
            '¿Para qué fecha querés hacer el pedido?',
            'Elegí el reparto:',
            $botones
        );

        return 'Botones enviados';
    }

    /**
     * Filtra los productos según el día de reparto elegido.
     * Un producto es disponible para un día si:
     * - No tiene configuración en ia_producto_localidad para esa localidad, O
     * - Tiene configuración pero dias_reparto es null (usa todos los días de la localidad), O
     * - Tiene dias_reparto y el día está incluido.
     */
    private function filtrarProductosPorDia(\Illuminate\Support\Collection $productos, int $dia, ?int $localidadId): \Illuminate\Support\Collection
    {
        if (!$localidadId) return $productos;

        $configs = ProductoLocalidad::where('localidad_id', $localidadId)
            ->whereNotNull('dias_reparto')
            ->get()
            ->keyBy('cod');

        if ($configs->isEmpty()) return $productos;

        return $productos->filter(function ($p) use ($dia, $configs) {
            if (!$configs->has($p->cod)) return true; // sin override → disponible siempre
            $diasCfg = $configs->get($p->cod)->dias_reparto;
            if ($diasCfg === null) return true;       // sin restricción → disponible siempre
            if (empty($diasCfg)) return false;        // array vacío → no disponible ningún día
            $diasNum = array_map(fn($d) => is_array($d) ? (int) $d['dia'] : (int) $d, $diasCfg);
            return \in_array($dia, $diasNum, true);
        });
    }

    private function getLocalPrices($client): \Illuminate\Support\Collection
    {
        if (!$client->localidad_id) {
            return collect();
        }
        return ProductoLocalidad::where('localidad_id', $client->localidad_id)
            ->get()
            ->keyBy('cod');
    }

    /**
     * Precio final de un producto para un cliente.
     * Si existe un override en ia_producto_localidad con precio no nulo, lo usa.
     * Si no, usa el precio base del producto.
     */
    private function precioFinal(float $precioBase, $cod, \Illuminate\Support\Collection $localPrices): float
    {
        if ($localPrices->has($cod) && $localPrices->get($cod)->precio !== null) {
            return (float) $localPrices->get($cod)->precio;
        }
        return $precioBase;
    }

    private function formatCarrito(array $carrito): string
    {
        if (empty($carrito)) {
            return 'El carrito está vacío.';
        }

        $lineas = [];

        foreach ($carrito as $item) {
            $esPeso = $item['tipo'] !== 'Unidad';
            if ($esPeso) {
                $cant = $item['cant'] > 0
                    ? "{$item['cant']}u ({$item['kilos']}kg)"
                    : "{$item['kilos']}kg";
            } else {
                $cant = "{$item['cant']}u";
            }
            $precioFmt = $this->fmt($item['precio']);
            $netoFmt   = $this->fmt($item['neto']);
            $lineas[]  = "{$item['des']} {$cant} × {$precioFmt} \$ = {$netoFmt} \$";
        }

        $lineas[] = '[El total se muestra solo al confirmar el pedido. No informes el total al cliente en este paso.]';

        return implode("\n", $lineas);
    }

    /**
     * Actualiza precios del carrito y devuelve alertas de cambios/no disponibles.
     * Guarda el carrito con precios actualizados.
     */
    public function verificarCarritoAbandonado(Carrito $carrito, $cliente): array
    {
        $localPrices = $this->getLocalPrices($cliente);
        $items       = $carrito->items ?? [];
        $cods        = array_filter(array_column($items, 'cod'));

        $productos = Producto::paraBot()
            ->whereIn('tablaplu.cod', $cods)
            ->get()
            ->keyBy('cod');

        $alertas     = [];
        $itemsActualizados = $items;

        foreach ($items as $key => $item) {
            if (!isset($item['cod']) || !$productos->has($item['cod'])) {
                $alertas[] = "❌ {$item['des']}: no disponible actualmente";
                continue;
            }
            $precioNuevo = $this->precioFinal((float) $productos[$item['cod']]->precio, $item['cod'], $localPrices);
            if (abs($precioNuevo - (float) $item['precio']) > 0.01) {
                $alertas[] = "⚠️ {$item['des']}: precio actualizado a $" . $this->fmt($precioNuevo);
            }
            $base = ($item['tipo'] ?? '') !== 'Unidad' ? ($item['kilos'] ?? 0) : ($item['cant'] ?? 0);
            $itemsActualizados[$key]['precio'] = $precioNuevo;
            $itemsActualizados[$key]['neto']   = round($precioNuevo * $base, 2);
        }

        $carrito->update(['items' => $itemsActualizados]);

        return $alertas;
    }

    private function validarCarrito(array $carrito, ?\Illuminate\Support\Collection $localPrices = null): array
    {
        if (empty($carrito)) {
            return [];
        }

        $localPrices       = $localPrices ?? collect();
        $codsEnCarrito     = array_filter(array_column($carrito, 'cod'));
        $productosActuales = Producto::paraBot()
            ->whereIn('tablaplu.cod', $codsEnCarrito)
            ->get()
            ->keyBy('cod');

        $alertas = [];
        foreach ($carrito as $item) {
            if (!isset($item['cod']) || !$productosActuales->has($item['cod'])) {
                $alertas[] = "❌ {$item['des']}: producto no disponible actualmente";
                continue;
            }

            $preActual = $this->precioFinal((float) $productosActuales[$item['cod']]->precio, $item['cod'], $localPrices);
            if (abs($preActual - $item['precio']) > 0.01) {
                $precioViejo = $this->fmt($item['precio']);
                $precioNuevo = $this->fmt($preActual);
                $alertas[] = "⚠️ {$item['des']}: precio cambió de \${$precioViejo} a \${$precioNuevo}/u";
            }
        }

        return $alertas;
    }

    // Formatea precio sin separador de miles; omite decimales si son ,00
    private function fmt(float $val): string
    {
        return $val == (int) $val
            ? number_format($val, 0, ',', '')
            : number_format($val, 2, ',', '');
    }

    // Convierte número en formato argentino a float (ej: "1.500,75" → 1500.75, "2,5" → 2.5)
    private function parsearNumero($valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        $str = trim((string) $valor);
        // Si tiene punto Y coma: el punto es miles, la coma es decimal → "1.500,75"
        if (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
            // Si solo tiene coma: puede ser decimal argentino → "2,5" = 2.5 o "1.500" (solo punto = miles)
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }
        // Si solo tiene punto: puede ser miles ("1.500") o decimal inglés ("1.5")
        // Si hay exactamente 3 dígitos después del punto, es miles
        elseif (preg_match('/^\d+\.(\d{3})$/', $str)) {
            $str = str_replace('.', '', $str);
        }
        return (float) $str;
    }

    // Extrae el peso por unidad de un texto (ej: "10kg c/u", "aprox. 0.15kg c/u", "10 kg por unidad" → valor float)
    private function parsePesoUnitario(string $texto): ?float
    {
        // "10kg c/u" | "10 kg c/u" | "aprox. 10kg c/u"
        if (preg_match('/(?:aprox[.\s]*)?([\d]+(?:[.,]\d+)?)\s*kg\s*c\//i', $texto, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        // "10kg por unidad" | "10 kg por unidad"
        if (preg_match('/([\d]+(?:[.,]\d+)?)\s*kg\s+por\s+unidad/i', $texto, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Flujo de confirmación interactiva (botones / listas)
    // -------------------------------------------------------------------------

    private function getProximaFechaValida($client, string $tipoEntrega, $empresa, ?string $fechaSolicitada = null): string
    {
        $fechasCerradas = $empresa?->bot_fechas_cerrado ?? [];

        if ($tipoEntrega === 'retiro') {
            $horarios     = $empresa?->bot_horarios ?? [];
            $diasAbiertos = array_keys(array_filter($horarios, fn($t) => !empty($t)));
            $dias         = !empty($diasAbiertos) ? array_map('intval', $diasAbiertos) : [1, 2, 3, 4, 5, 6];
        } else {
            $localidadObj = $client->localidad_id
                ? $client->localidadObj
                : ($client->localidad
                    ? Localidad::where('activo', true)
                        ->whereRaw('LOWER(nombre) = ?', [strtolower($client->localidad)])
                        ->first()
                    : null);
            $dias = array_map('intval', $localidadObj?->dias_reparto ?? []);
        }

        // Si el cliente pidió una fecha específica, usarla si es válida
        if ($fechaSolicitada) {
            try {
                $solicitada = \Carbon\Carbon::parse($fechaSolicitada);
                if ($solicitada->gt(now())) {
                    $diaNum   = (int) $solicitada->format('w');
                    $fechaStr = $solicitada->format('Y-m-d');
                    $diaValido    = empty($dias) || \in_array($diaNum, $dias, true);
                    $noEsCerrada  = !\in_array($fechaStr, $fechasCerradas, true);
                    if ($diaValido && $noEsCerrada) {
                        return $fechaStr;
                    }
                }
            } catch (\Throwable) {}
        }

        // Si hay hora de corte y el pedido entra antes de ese horario, incluir hoy como candidato
        $horaCorte  = $empresa?->bot_hora_corte ?? null;
        $startDays  = 1; // Por defecto: desde mañana
        if ($horaCorte) {
            $corteHoy = \Carbon\Carbon::today()->setTimeFromTimeString($horaCorte);
            if (now()->lt($corteHoy)) {
                $startDays = 0; // Antes del corte: hoy es candidato
            }
        }

        for ($i = $startDays; $i <= 14; $i++) {
            $candidato = now()->addDays($i);
            $diaSemana = (int) $candidato->format('w');
            $fechaStr  = $candidato->format('Y-m-d');

            if (empty($dias)) {
                if (!\in_array($fechaStr, $fechasCerradas, true)) {
                    return $fechaStr;
                }
            } elseif (\in_array($diaSemana, $dias, true) && !\in_array($fechaStr, $fechasCerradas, true)) {
                return $fechaStr;
            }
        }

        return now()->addDay()->format('Y-m-d');
    }

    public function iniciarConfirmacionPedido($client, array $args = []): string
    {
        $empresa       = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());
        $permiteEnvio  = $empresa?->bot_permite_envio  ?? true;
        $permiteRetiro = $empresa?->bot_permite_retiro ?? true;

        // Verificar localidad para envío
        if ($permiteEnvio && !$permiteRetiro) {
            if (!$client->localidad) {
                return 'No tengo registrada tu localidad. ¿En qué localidad estás para saber si tenemos reparto en tu zona?';
            }
            $localidadObj = $client->localidad_id
                ? $client->localidadObj
                : Localidad::where('activo', true)
                    ->whereRaw('LOWER(nombre) = ?', [strtolower($client->localidad)])
                    ->first();
            if (!$localidadObj) {
                return "Lo sentimos, por el momento no tenemos reparto para {$client->localidad}.";
            }
            if (empty($localidadObj->dias_reparto)) {
                return "La localidad {$client->localidad} no tiene días de reparto configurados. Consultanos por otro medio.";
            }
        }

        $carrito = $this->getCarrito($client);
        $items   = $carrito ? $carrito->items : [];

        if (empty($items)) {
            return 'El carrito está vacío.';
        }

        $itemsText = implode("\n", array_map(
            fn($item) => '• ' . ($item['cant'] > 0 ? "{$item['cant']}u" : "{$item['kilos']}kg") . " {$item['des']} — $" . $this->fmt($item['neto']),
            $items
        ));
        $total = array_sum(array_column($items, 'neto'));

        // Tipo de entrega: del argumento de la IA, o forzado por configuración
        $tipoProvisto = null;
        if (isset($args['tipo_entrega']) && in_array($args['tipo_entrega'], ['envio', 'retiro'])) {
            // Solo usar el tipo provisto si la configuración lo permite
            if (($args['tipo_entrega'] === 'envio'  && $permiteEnvio)  ||
                ($args['tipo_entrega'] === 'retiro' && $permiteRetiro)) {
                $tipoProvisto = $args['tipo_entrega'];
            }
        }
        if ($permiteEnvio  && !$permiteRetiro) $tipoProvisto = 'envio';
        if (!$permiteEnvio && $permiteRetiro)  $tipoProvisto = 'retiro';

        // Forma de pago provista por la IA
        $mediosHabilitados = $empresa?->bot_medios_pago ?? array_keys(IaEmpresa::MEDIOS_PAGO);
        $pagoProvisto = null;
        if (!empty($args['forma_pago'])) {
            if (in_array($args['forma_pago'], $mediosHabilitados)) {
                $pagoProvisto = $args['forma_pago'];
            }
        }
        // Si solo hay una forma de pago, seleccionarla automáticamente
        if ($pagoProvisto === null && count($mediosHabilitados) === 1) {
            $pagoProvisto = $mediosHabilitados[0];
        }

        // Si ya tenemos tipo de entrega, calcular fecha y armar datos
        if ($tipoProvisto !== null) {
            $fecha = Cache::get('fecha_reparto_elegida_' . $client->id)
                  ?? Cache::get('proxima_fecha_entrega_' . $client->id)
                  ?? (!empty($args['fecha_entrega']) && \Carbon\Carbon::canBeCreatedFromFormat($args['fecha_entrega'], 'Y-m-d') ? $args['fecha_entrega'] : null)
                  ?? $this->getProximaFechaValida($client, $tipoProvisto, $empresa);
            $fechaLabel = \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM');

            $data = [
                'tipo_entrega'  => $tipoProvisto,
                'fecha_entrega' => $fecha,
            ];
            if ($tipoProvisto === 'envio') {
                $data['calle']      = $args['calle']      ?? $client->calle      ?? '';
                $data['numero']     = $args['numero']     ?? $client->numero     ?? '';
                $data['localidad']  = $args['localidad']  ?? $client->localidad  ?? '';
                $data['dato_extra'] = $args['dato_extra'] ?? $client->dato_extra ?? '';
            }
            if (!empty($args['obs'])) {
                $data['obs'] = $args['obs'];
            }

            $tipoLabel = ($tipoProvisto === 'envio' ? "📍 Envío" : "🏪 Retiro en local") . " — {$fechaLabel}";

            // Si también tenemos forma de pago, ir directo al resumen final
            if ($pagoProvisto !== null) {
                $data['medio_pago'] = $pagoProvisto;
                Cache::put('pedido_conf_' . $client->id, $data, now()->addMinutes(30));
                $client->update(['estado' => 'confirmando_final']);
                $this->mostrarResumenFinal($client, $data, $itemsText, $total);
                return 'Botones enviados';
            }

            // Solo tenemos entrega, pedir forma de pago
            Cache::put('pedido_conf_' . $client->id, $data, now()->addMinutes(30));
            $client->update(['estado' => 'confirmando_pago']);
            $this->enviarListaPago($client, $empresa, $itemsText, $total, $tipoLabel);
            return 'Botones enviados';
        }

        // Ambas opciones disponibles y la IA no indicó tipo: preguntar
        $client->update(['estado' => 'confirmando_entrega']);
        $this->sendInteractiveButtons(
            $client->phone,
            "{$itemsText}\n\n*Total: $" . $this->fmt($total) . '*',
            '¿Cómo recibís tu pedido?',
            [
                ['id' => 'entrega_envio',  'title' => 'Envío'],
                ['id' => 'entrega_retiro', 'title' => 'Retiro en local'],
            ]
        );

        return 'Botones enviados';
    }

    public function handleInteractiveResponse($client, string $id): string
    {
        $estado = $client->estado ?? '';

        if ($estado === 'eligiendo_reparto') {
            return $this->handleRepartoSeleccionado($client, $id);
        }

        if ($estado === 'confirmando_entrega') {
            return $this->handleEntregaSeleccionada($client, $id);
        }

        if ($estado === 'confirmando_pago') {
            return $this->handlePagoSeleccionado($client, $id);
        }

        if ($estado === 'confirmando_final') {
            return $this->handleConfirmacionFinal($client, $id);
        }

        return '';
    }

    private function handleRepartoSeleccionado($client, string $id): string
    {
        // id formato: reparto_2026-04-02
        if (!str_starts_with($id, 'reparto_')) {
            return '';
        }

        $fecha = substr($id, 8); // 'reparto_' tiene 8 chars
        if (!\Carbon\Carbon::canBeCreatedFromFormat($fecha, 'Y-m-d')) {
            return '';
        }

        Cache::put('fecha_reparto_elegida_' . $client->id, $fecha, now()->addHours(6));
        Cache::put('proxima_fecha_entrega_' . $client->id, $fecha, now()->addMinutes(30));
        $client->update(['estado' => null]);

        $textoFecha = \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM');
        $dia        = (int) \Carbon\Carbon::parse($fecha)->format('w');

        // Eliminar del carrito los productos que no se reparten ese día
        $carritoRegistro = $this->getCarrito($client);
        $noDisponibles   = [];
        if ($carritoRegistro && !empty($carritoRegistro->items) && $client->localidad_id) {
            $todosProductos = Producto::paraBot()->get();
            $disponibles    = $this->filtrarProductosPorDia($todosProductos, $dia, $client->localidad_id)
                ->pluck('cod')->toArray();

            $itemsFiltrados = [];
            foreach ($carritoRegistro->items as $item) {
                if (isset($item['cod']) && !\in_array($item['cod'], $disponibles)) {
                    $noDisponibles[] = $item['des'];
                } else {
                    $itemsFiltrados[] = $item;
                }
            }

            if (!empty($noDisponibles)) {
                $carritoRegistro->update(['items' => $itemsFiltrados]);
            }
        }

        // Vaciar el carrito completo al cambiar de día para que el cliente repida con los productos del día elegido
        $carritoRegistro = $this->getCarrito($client);
        if ($carritoRegistro) {
            $carritoRegistro->delete();
        }

        // Procesar con IA para que muestre los productos disponibles para ese día
        return $this->process($client, "El cliente eligió el reparto del {$textoFecha}. Se vació el carrito para que arme uno nuevo con los productos disponibles para ese día. Mostrá los productos disponibles y preguntá qué quiere pedir.");
    }

    private function handleEntregaSeleccionada($client, string $id): string
    {
        $tipoEntrega     = ($id === 'entrega_envio') ? 'envio' : 'retiro';
        $empresa         = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());
        $data = [
            'tipo_entrega'   => $tipoEntrega,
            'fecha_entrega'  => Cache::get('fecha_reparto_elegida_' . $client->id)
                             ?? Cache::get('proxima_fecha_entrega_' . $client->id)
                             ?? $this->getProximaFechaValida($client, $tipoEntrega, $empresa),
        ];
        if ($tipoEntrega === 'envio') {
            $data['calle']      = $client->calle      ?? '';
            $data['numero']     = $client->numero     ?? '';
            $data['localidad']  = $client->localidad  ?? '';
            $data['dato_extra'] = $client->dato_extra ?? '';
        }
        Cache::put('pedido_conf_' . $client->id, $data, now()->addMinutes(30));
        $client->update(['estado' => 'confirmando_pago']);

        $carrito   = $this->getCarrito($client);
        $items     = $carrito ? $carrito->items : [];
        $itemsText = implode("\n", array_map(
            fn($item) => '• ' . ($item['cant'] > 0 ? "{$item['cant']}u" : "{$item['kilos']}kg") . " {$item['des']} — $" . $this->fmt($item['neto']),
            $items
        ));
        $total     = array_sum(array_column($items, 'neto'));

        $fechaLabel = \Carbon\Carbon::parse($data['fecha_entrega'])->locale('es')->isoFormat('dddd D [de] MMMM');
        $tipoLabel  = $tipoEntrega === 'envio'
            ? "📍 Envío — {$fechaLabel}"
            : "🏪 Retiro en local — {$fechaLabel}";

        $this->enviarListaPago($client, $empresa, $itemsText, $total, $tipoLabel);

        return '';
    }

    private function enviarListaPago($client, $empresa, string $itemsText, float $total, string $entregaLabel): void
    {
        $mediosHabilitados = $empresa?->bot_medios_pago ?? array_keys(IaEmpresa::MEDIOS_PAGO);
        $allMedios         = IaEmpresa::MEDIOS_PAGO;

        $rows = [];
        foreach ($mediosHabilitados as $key) {
            if (isset($allMedios[$key])) {
                $rows[] = ['id' => 'pago_' . $key, 'title' => $allMedios[$key]];
            }
        }

        // Solo un medio de pago: lo seleccionamos automáticamente, saltamos la lista
        if (count($rows) === 1) {
            $medioPago = str_replace('pago_', '', $rows[0]['id']);
            $cacheKey  = 'pedido_conf_' . $client->id;
            $data      = Cache::get($cacheKey, []);
            $data['medio_pago'] = $medioPago;
            Cache::put($cacheKey, $data, now()->addMinutes(30));
            $client->update(['estado' => 'confirmando_final']);
            $this->mostrarResumenFinal($client, $data, $itemsText, $total);
            return;
        }

        $body = "{$itemsText}\n\n*Total: $" . $this->fmt($total) . "*\n{$entregaLabel}";

        $this->sendInteractiveList(
            $client->phone,
            $body,
            'Ver opciones',
            [['title' => 'Medios de pago', 'rows' => $rows]]
        );
    }

    private function handlePagoSeleccionado($client, string $id): string
    {
        $medioPago = str_replace('pago_', '', $id);
        $cacheKey  = 'pedido_conf_' . $client->id;
        $data      = Cache::get($cacheKey, []);
        $data['medio_pago'] = $medioPago;
        Cache::put($cacheKey, $data, now()->addMinutes(30));
        $client->update(['estado' => 'confirmando_final']);

        $carrito   = $this->getCarrito($client);
        $items     = $carrito ? $carrito->items : [];
        $itemsText = implode("\n", array_map(
            fn($item) => '• ' . ($item['cant'] > 0 ? "{$item['cant']}u" : "{$item['kilos']}kg") . " {$item['des']} — $" . $this->fmt($item['neto']),
            $items
        ));
        $total = array_sum(array_column($items, 'neto'));

        $this->mostrarResumenFinal($client, $data, $itemsText, $total);
        return '';
    }

    private function mostrarResumenFinal($client, array $data, string $itemsText, float $total): void
    {
        $allMedios  = IaEmpresa::MEDIOS_PAGO;
        $medioLabel = $allMedios[$data['medio_pago'] ?? ''] ?? ($data['medio_pago'] ?? '');
        $fechaLabel = isset($data['fecha_entrega'])
            ? ' — ' . \Carbon\Carbon::parse($data['fecha_entrega'])->locale('es')->isoFormat('dddd D [de] MMMM')
            : '';
        $tipoLabel  = (($data['tipo_entrega'] ?? 'retiro') === 'envio' ? 'Envío' : 'Retiro en local') . $fechaLabel;

        $body = "{$itemsText}\n\n"
            . "📦 {$tipoLabel}\n"
            . "💳 {$medioLabel}\n"
            . "*Total: $" . $this->fmt($total) . '*';

        $this->sendInteractiveButtons(
            $client->phone,
            $body,
            'Confirmá tu pedido',
            [
                ['id' => 'confirmar_si', 'title' => 'Confirmar pedido'],
                ['id' => 'confirmar_no', 'title' => 'Cancelar'],
            ]
        );
    }

    private function handleConfirmacionFinal($client, string $id): string
    {
        $cacheKey = 'pedido_conf_' . $client->id;
        $data     = Cache::get($cacheKey, []);
        $client->update(['estado' => 'activo']);
        Cache::forget($cacheKey);
        Cache::forget('fecha_reparto_elegida_' . $client->id);

        if ($id === 'confirmar_si') {
            $result = $this->createOrder(
                $client,
                $data['fecha_entrega'] ?? now()->format('Y-m-d'),
                $data['tipo_entrega'] ?? 'retiro',
                $data['medio_pago']   ?? 'efectivo',
                $data['calle']        ?? '',
                $data['numero']       ?? '',
                $data['localidad']    ?? '',
                $data['dato_extra']   ?? '',
                ''
            );
            $this->sendReply($client, $result);
            return $result;
        }

        $msg = '👌 Pedido cancelado. ¿En qué más puedo ayudarte?';
        $this->sendReply($client, $msg);
        return $msg;
    }

    // -------------------------------------------------------------------------

    private function createOrder($client, string $fechaEntrega = '', string $tipoEntrega = 'retiro', string $formaPago = 'efectivo', string $calle = '', string $numero = '', string $localidad = '', string $datoExtra = '', string $obs = ''): string
    {
        $registro = $this->getCarrito($client);
        $carrito  = $registro ? $registro->items : [];

        if (empty($carrito)) {
            return 'El carrito está vacío. Agregá productos antes de confirmar el pedido.';
        }

        // Si el carrito viene de editar_pedido, reutilizar el nro original
        $pedidoNroOriginal = $registro?->pedido_nro ?? null;
        if ($pedidoNroOriginal) {
            $codcliCheck = $client->cuenta ? $client->cuenta->cod : $client->id;
            Pedido::where('nro', $pedidoNroOriginal)->where('codcli', $codcliCheck)->delete();
            Pedidosia::where('nro', $pedidoNroOriginal)->delete();
            $nro = $pedidoNroOriginal;
        } else {
            $nro = (Pedido::max('nro') ?? 0) + 1;
        }
        $fecha  = $fechaEntrega ?: now()->format('Y-m-d');
        $codcli = $client->cuenta ? $client->cuenta->cod : $client->id;
        $nomcli = $client->cuenta ? $client->cuenta->nom : $client->name;
        $config = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());

        $pedidoMinimo = (float) ($config?->pedido_minimo ?? 0);
        if ($pedidoMinimo > 0) {
            $totalCarrito = array_sum(array_column($carrito, 'neto'));
            if ($totalCarrito < $pedidoMinimo) {
                return "El pedido mínimo es $" . $this->fmt($pedidoMinimo) . ". Tu carrito suma $" . $this->fmt($totalCarrito) . ". Agregá más productos para poder confirmar.";
            }
        }

        // Límite de pedidos pendientes por cliente
        $maxPendientes = (int) ($config?->max_pedidos_pendientes ?? 0);
        if ($maxPendientes > 0 && !$pedidoNroOriginal) {
            $pendientes = Pedidosia::where('idcliente', $client->id)
                ->where('estado', Pedidosia::ESTADO_PENDIENTE)
                ->count();
            if ($pendientes >= $maxPendientes) {
                $txt = $maxPendientes === 1
                    ? "Ya tenés un pedido pendiente de confirmar."
                    : "Ya tenés {$pendientes} pedidos pendientes.";
                return "{$txt} No podemos tomar un nuevo pedido hasta que el negocio procese los anteriores.";
            }
        }
        $suc    = $config?->suc ?? '';
        $pv     = $config?->pv ?? '';

        $precioActual = Producto::paraBot()
            ->whereIn('tablaplu.cod', array_column($carrito, 'cod'))
            ->get()
            ->keyBy('cod');

        $localPrices = $this->getLocalPrices($client);

        $alertas  = [];
        $omitidos = [];

        foreach ($carrito as $item) {
            $precio = $item['precio'];
            $neto   = $item['neto'];

            // Producto no encontrado en BD → omitir del pedido
            if (!isset($item['cod']) || !$precioActual->has($item['cod'])) {
                $omitidos[] = $item['des'];
                continue;
            }

            // Verificar si el precio base cambió desde que se armó el carrito
            $preConExtra = $this->precioFinal((float) $precioActual[$item['cod']]->precio, $item['cod'], $localPrices);
            if (abs($preConExtra - $precio) > 0.01) {
                $base   = $item['tipo'] !== 'Unidad' ? $item['kilos'] : $item['cant'];
                $neto   = round($preConExtra * $base, 2);
                $precio = $preConExtra;
                $alertas[] = "{$item['des']} (precio actualizado a $" . $this->fmt($preConExtra) . ')';
            }

            Pedido::create([
                'fecha'      => $fecha,
                'nro'        => $nro,
                'nomcli'     => $nomcli,
                'codcli'     => $codcli,
                'codigo'     => $item['cod'] ?? null,
                'descrip'    => $item['des'],
                'kilos'      => $item['kilos'],
                'cant'       => $item['cant'],
                'precio'     => $precio,
                'neto'       => $neto,
                'estado'     => Pedido::ESTADO_PENDIENTE,
                'obs'        => $obs,
                'pedido_at'  => now(),
                'updated_at' => now(),
                'suc'        => $suc,
                'pv'         => $pv,
                'venta'      => 0,
            ]);
        }

        DB::transaction(function () use ($nro, $codcli, $client, $nomcli, $fecha, $tipoEntrega, $calle, $numero, $localidad, $datoExtra, $formaPago, $carrito, $obs, $registro) {
            $total = array_sum(array_column($carrito, 'neto'));
            Pedidosia::create([
                'nro'          => $nro,
                'codcli'       => $codcli,
                'idcliente'    => $client->id,
                'nomcli'       => $nomcli,
                'fecha'        => $fecha,
                'tipo_entrega' => $tipoEntrega,
                'calle'        => $calle ?: null,
                'numero'       => $numero ?: null,
                'localidad'    => $localidad ?: null,
                'dato_extra'   => $datoExtra ?: null,
                'forma_pago'   => $formaPago,
                'total'        => $total,
                'obs'          => $obs ?: null,
                'estado'       => Pedidosia::ESTADO_PENDIENTE,
                'pedido_at'    => now(),
            ]);
            // Limpiar carrito dentro de la transacción
            $registro?->delete();
        });

        $extras = [];
        if (!empty($omitidos)) {
            $extras[] = '❌ No disponibles (no se incluyeron): ' . implode(', ', $omitidos);
        }
        if (!empty($alertas)) {
            $extras[] = '⚠️ Precios actualizados: ' . implode(', ', $alertas);
        }

        $resumen = implode(', ', array_map(
            fn($item) => $item['cant'] > 0 ? "{$item['cant']}u {$item['des']}" : "{$item['kilos']}kg {$item['des']}",
            $carrito
        ));

        $total = array_sum(array_column($carrito, 'neto'));

        // Notificar al local
        $config      = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());
        $telLocal    = trim($config?->telefono_pedidos ?? '');
        $notifActiva = $config?->notif_negocio_enabled ?? true;

        if ($telLocal && $notifActiva) {
            $entregaTexto = $tipoEntrega === 'envio'
                ? "Envío: {$calle} {$numero}" . ($localidad ? ", {$localidad}" : '') . ($datoExtra ? " ({$datoExtra})" : '')
                : 'Retiro en local';

            $itemsTexto = implode("\n", array_map(
                fn($item) => "  • " . ($item['cant'] > 0 ? "{$item['cant']}u" : "{$item['kilos']}kg") . " {$item['des']} — $" . $this->fmt($item['neto']),
                array_filter($carrito, fn($i) => !in_array($i['des'], $omitidos))
            ));

            $detalle = "👤 {$client->phone} | 📅 {$fecha} | 📦 {$entregaTexto} | 💳 {$formaPago}\n\n{$itemsTexto}";
            if ($obs) $detalle .= "\n📝 {$obs}";

            $this->enviarNotifPedido($telLocal, $nro, $nomcli, $detalle, $total);
        }

        $msg = "Pedido #{$nro} registrado: {$resumen}.";
        if (!empty($extras)) {
            $msg .= "\n" . implode("\n", $extras);
        }

        return $msg;
    }

    private function orderStatus($client): string
    {
        $codcli  = $client->cuenta ? $client->cuenta->cod : $client->id;
        $pedidos = Pedido::where('codcli', $codcli)
            ->orderByDesc('reg')
            ->get()
            ->groupBy('nro')
            ->take(5);

        if ($pedidos->isEmpty()) {
            return 'El cliente no tiene pedidos registrados.';
        }

        // Pre-cargar cabeceras de ia_pedidos (totales estimados)
        $nros = $pedidos->keys()->toArray();
        $cabeceras = Pedidosia::whereIn('nro', $nros)->get()->keyBy('nro');

        // Pre-cargar facturas de pedidos finalizados
        $pares = $pedidos->flatten()
            ->where('estado', Pedido::ESTADO_FINALIZADO)
            ->whereNotNull('venta')->where('venta', '>', 0)
            ->unique(fn($p) => "{$p->venta}-{$p->pv}")->values();

        $facturas = collect();
        if ($pares->isNotEmpty()) {
            $rows = Factventas::where(function ($q) use ($pares) {
                foreach ($pares as $p) {
                    $q->orWhere(fn($s) => $s->where('nro', $p->venta)->where('pv', $p->pv));
                }
            })->get();
            $facturas = $rows->groupBy(fn($f) => "{$f->nro}-{$f->pv}");
        }

        return $pedidos->map(function ($items, $nro) use ($facturas, $cabeceras) {
            $first     = $items->first();
            $fecha     = $first->fecha;
            $estado    = $first->estado_texto;
            $cabecera  = $cabeceras->get($nro);
            $totalEst  = $cabecera ? $cabecera->total : null;
            $entrega   = $cabecera ? ($cabecera->tipo_entrega === 'envio' ? 'Envío' : 'Retiro en local') : null;
            $entregaTxt = $entrega ? " | {$entrega}" : '';

            // Pedido pendiente: mostrar lo que pidió + total estimado + tipo entrega
            if ($first->estado != Pedido::ESTADO_FINALIZADO) {
                $detalle = $items->map(
                    fn($p) =>
                    $p->cant > 1 ? "{$p->cant}u {$p->descrip}" : "{$p->kilos}kg {$p->descrip}"
                )->implode(', ');
                $totalTxt = $totalEst ? ' — Total aprox: $' . $this->fmt($totalEst) : '';
                return "Pedido #{$nro} ({$fecha}): {$detalle} — {$estado}{$totalTxt}{$entregaTxt}";
            }

            // Pedido finalizado: mostrar factura real si existe
            $key   = "{$first->venta}-{$first->pv}";
            $lineas = $facturas->get($key);

            if ($lineas && $lineas->isNotEmpty()) {
                $comprobante = $lineas->first()->fact . ' ' . str_pad($first->pv, 4, '0', STR_PAD_LEFT) . '-' . str_pad($first->venta, 8, '0', STR_PAD_LEFT);
                $total = $lineas->sum('neto');
                $detalle = $lineas->map(
                    fn($f) =>
                    "{$f->descrip}: {$f->kilos}kg × $" . number_format($f->precio, 2, ',', '.') . " = $" . number_format($f->neto, 2, ',', '.')
                )->implode(' | ');
                return "Pedido #{$nro} ({$fecha}): {$estado}{$entregaTxt}\nComprobante: {$comprobante}\nDetalle real: {$detalle}\nTotal: $" . number_format($total, 2, ',', '.');
            }

            // Finalizado pero sin factura: usar total estimado del pedido
            $detalle = $items->map(
                fn($p) =>
                $p->cant > 1 ? "{$p->cant}u {$p->descrip}" : "{$p->kilos}kg {$p->descrip}"
            )->implode(', ');
            $totalTxt = $totalEst ? "\nTotal estimado al pedido: $" . $this->fmt($totalEst) . " (el monto final puede variar según el peso real)" : '';
            return "Pedido #{$nro} ({$fecha}): {$detalle} — {$estado}{$entregaTxt}{$totalTxt}";
        })->implode("\n\n");
    }



    private function cancelOrder($client, int $nro): string
    {
        if ($nro <= 0) {
            return 'Número de pedido inválido.';
        }

        $codcli  = $client->cuenta ? $client->cuenta->cod : $client->id;
        $pedidos = Pedido::where('codcli', $codcli)
            ->where('nro', $nro)
            ->where('estado', Pedido::ESTADO_PENDIENTE)
            ->get();

        if ($pedidos->isEmpty()) {
            return "No encontré el pedido #{$nro} pendiente para este cliente. Puede que ya esté procesado o no exista.";
        }

        Pedido::where('codcli', $codcli)->where('nro', $nro)->update(['estado' => Pedido::ESTADO_CANCELADO]);
        Pedidosia::where('nro', $nro)->update(['estado' => Pedidosia::ESTADO_CANCELADO]);

        return "Pedido #{$nro} cancelado correctamente.";
    }

    private function editOrder($client, int $nro): string
    {
        if ($nro <= 0) {
            return 'Número de pedido inválido.';
        }

        // Verificar estado en ia_pedidos primero
        $sia = Pedidosia::where('nro', $nro)->first();
        if ($sia && $sia->estado !== Pedidosia::ESTADO_PENDIENTE) {
            $label = $sia->estadoLabel();
            return "El pedido #{$nro} está en estado *{$label}* y no puede modificarse.";
        }

        $codcli = $client->cuenta ? $client->cuenta->cod : $client->id;
        $items  = Pedido::where('codcli', $codcli)
            ->where('nro', $nro)
            ->where('estado', Pedido::ESTADO_PENDIENTE)
            ->get();

        if ($items->isEmpty()) {
            return "No encontré el pedido #{$nro} pendiente para este cliente.";
        }

        // Convertir los ítems del pedido al formato del carrito
        $carritoItems = [];
        foreach ($items as $item) {
            $key = mb_strtolower($item->descrip);
            $esPeso = ($item->kilos ?? 0) > 0;
            $carritoItems[$key] = [
                'cod'    => $item->codigo,
                'des'    => $item->descrip,
                'cant'   => (float) $item->cant,
                'kilos'  => (float) $item->kilos,
                'precio' => (float) $item->precio,
                'neto'   => (float) $item->neto,
                'tipo'   => $esPeso ? 'Peso' : 'Unidad',
            ];
        }

        // Guardar en carrito marcando el pedido original
        Carrito::where('cliente_id', $client->id)->delete();
        Carrito::create([
            'cliente_id' => $client->id,
            'items'      => $carritoItems,
            'pedido_nro' => $nro,
            'expires_at' => now()->addYear(),
        ]);

        $count = count($carritoItems);
        return "Cargué los {$count} ítems del pedido #{$nro} al carrito. El cliente puede agregar, quitar o modificar productos y luego confirmar con crear_pedido — el pedido #{$nro} será reemplazado.";
    }


    private function verProducto($client, string $nombre, bool $solicitaPrecio = false, bool $puedePedir = true): string
    {
        // Sin cache: siempre precio e imagen actualizados desde la BD
        $todosProductos = Producto::paraBot()->get();
        // Solo productos disponibles para la localidad del cliente
        if ($client->localidad_id) {
            $locCods = ProductoLocalidad::where('localidad_id', $client->localidad_id)->pluck('cod')->toArray();
            $productos = $locCods ? $todosProductos->filter(fn($p) => in_array($p->cod, $locCods)) : $todosProductos;
        } else {
            $productos = $todosProductos;
        }
        $candidatos   = $this->buscarCandidatos($productos, $nombre);

        if ($candidatos->isEmpty()) {
            return "Producto '{$nombre}' no encontrado. Los productos disponibles son: " . $productos->pluck('des')->join(', ') . '.';
        }

        if ($candidatos->count() > 1) {
            $opciones = $candidatos->pluck('des')->map(fn($d) => "• {$d}")->implode("\n");
            return "Encontré varias opciones para '{$nombre}'. Cada una puede tener precio diferente — NO menciones precios hasta que el cliente elija una y se llame ver_producto para esa específica:\n{$opciones}\nPreguntale al cliente cuál quiere.";
        }

        $producto  = $candidatos->first();
        $normalize = fn(string $s) => strtolower(\Illuminate\Support\Str::ascii($s));

        // Si la coincidencia no es exacta (fuzzy), confirmar antes de mostrar
        if ($normalize($producto->des) !== $normalize($nombre)) {
            return "Encontré '{$producto->des}' como posible coincidencia para '{$nombre}'. Preguntale al cliente: '¿Te referís a {$producto->des}?' — si confirma (dice sí, ese, dale, etc.) y da una cantidad, llamá DIRECTAMENTE agregar_al_carrito con descrip='{$producto->des}' y la cantidad indicada. Si confirma sin dar cantidad, pedile la cantidad y luego llamá agregar_al_carrito. NO volvás a llamar ver_producto para este mismo producto.";
        }

        $caption = "*{$producto->des}*";
        if (!empty($producto->descripcion)) {
            $caption .= "\n_{$producto->descripcion}_";
        }
        if ($solicitaPrecio) {
            $localPrices = $this->getLocalPrices($client);
            $precio      = $this->fmt($this->precioFinal((float) $producto->precio, $producto->cod, $localPrices));
            $unidad      = $producto->tipo === 'Unidad' ? 'por unidad' : 'por kg';
            $caption    .= "\n\${$precio} ({$unidad})";
        }

        // Enviar imagen con descripción y precio como caption (todo en 1 mensaje)
        Log::info("verProducto [{$producto->des}] imagen='{$producto->imagen}'");
        if (!empty($producto->imagen) && $producto->imagen !== 'sinimagen.webp') {
            $path = public_path($producto->imagen);
            Log::info("verProducto path={$path} exists=" . (file_exists($path) ? 'SI' : 'NO'));
            if (file_exists($path)) {
                try {
                    $isMessenger = str_starts_with($client->phone, 'fb_') || str_starts_with($client->phone, 'ig_');
                    if ($isMessenger) {
                        $imageUrl = config('app.url') . '/' . $producto->imagen;
                        $this->sendReplyImage($client, $imageUrl, $caption);
                    } else {
                        $mime    = $this->mimeFromPath($path);
                        $mediaId = $this->uploadMediaFromPath($path, $mime);
                        $this->sendWhatsappMedia($client->phone, $mediaId, 'image', $caption);
                    }
                    return $puedePedir
                        ? "IMAGEN_ENVIADA para {$producto->des}. El cliente ya recibió imagen, descripción y precio. Preguntale '¿Lo agregamos al carrito? ¿Cuánto querés?' (en un solo mensaje, sin repetir datos del producto). Si confirma con cantidad, llamá agregar_al_carrito. Si dice 'sí' sin cantidad, pedile solo la cantidad. NO volvás a llamar ver_producto para este mismo producto."
                        : "IMAGEN_ENVIADA para {$producto->des}. El cliente ya recibió imagen, descripción y precio. NO preguntes cantidad ni ofrezcas agregar al carrito. Si quiere comprar, indicale que contacte al negocio directamente.";
                } catch (\Throwable $e) {
                    Log::error("verProducto upload error [{$producto->des}]: {$e->getMessage()}");
                }
            }
        }

        // Sin imagen: enviar el texto directo y decirle a GPT que no lo repita
        $this->sendReply($client, $caption);
        return $puedePedir
            ? "TEXTO_ENVIADO para {$producto->des}. El cliente ya recibió descripción y precio. Preguntale '¿Lo agregamos al carrito? ¿Cuánto querés?' (en un solo mensaje, sin repetir datos del producto). Si confirma con cantidad, llamá agregar_al_carrito. Si dice 'sí' sin cantidad, pedile solo la cantidad. NO volvás a llamar ver_producto para este mismo producto."
            : "TEXTO_ENVIADO para {$producto->des}. El cliente ya recibió descripción y precio. NO preguntes cantidad ni ofrezcas agregar al carrito. Si quiere comprar, indicale que contacte al negocio directamente.";
    }

    private function mimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => mime_content_type($path) ?: 'image/jpeg',
        };
    }

    /**
     * Busca candidatos en una colección de productos por niveles de prioridad.
     * Devuelve todos los que coinciden en el nivel más específico encontrado.
     */
    private function buscarCandidatos(\Illuminate\Support\Collection $productos, string $nombre): \Illuminate\Support\Collection
    {
        $normalize = fn(string $s) => strtolower(\Illuminate\Support\Str::ascii($s));
        $nd        = $normalize($nombre);
        $palabras  = array_values(array_filter(explode(' ', $nd), fn($w) => \strlen($w) > 2));

        // 1º exacto
        $r = $productos->filter(fn($p) => $normalize($p->des) === $nd);
        if ($r->isNotEmpty()) return $r->values();

        // 2º parcial (busqueda contiene nombre o nombre contiene busqueda)
        $r = $productos->filter(fn($p) => str_contains($normalize($p->des), $nd) || str_contains($nd, $normalize($p->des)));
        if ($r->isNotEmpty()) return $r->values();

        // 3º todas las palabras significativas presentes
        if (!empty($palabras)) {
            $r = $productos->filter(fn($p) => collect($palabras)->every(fn($w) => str_contains($normalize($p->des), $w)));
            if ($r->isNotEmpty()) return $r->values();

            // 4º alguna palabra significativa presente
            $r = $productos->filter(fn($p) => collect($palabras)->contains(fn($w) => str_contains($normalize($p->des), $w)));
            if ($r->isNotEmpty()) return $r->values();
        }

        return collect();
    }

    private function uploadMediaFromPath(string $path, string $mime = 'image/jpeg'): string
    {
        // WhatsApp no acepta WebP para mensajes de imagen — convertir a JPEG en memoria
        if ($mime === 'image/webp' && extension_loaded('gd') && function_exists('imagecreatefromwebp')) {
            $src = imagecreatefromwebp($path);
            if ($src) {
                ob_start();
                imagejpeg($src, null, 85);
                $contents = ob_get_clean();
                $mime     = 'image/jpeg';
                $filename = pathinfo($path, PATHINFO_FILENAME) . '.jpg';
            }
        }

        $response = Http::withToken($this->whatsappKey())
            ->attach('file', $contents ?? file_get_contents($path), $filename ?? basename($path), ['Content-Type' => $mime])
            ->attach('messaging_product', 'whatsapp')
            ->attach('type', $mime)
            ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/media');

        $mediaId = $response->json('id');

        if (!$mediaId) {
            throw new \RuntimeException('Error al subir imagen: ' . $response->body());
        }

        return $mediaId;
    }

    private function priceList($client): string
    {
        $todosProductos = Producto::paraBot()->get();

        // Solo mostrar productos configurados para la localidad del cliente
        if ($client->localidad_id) {
            $locCods = ProductoLocalidad::where('localidad_id', $client->localidad_id)->pluck('cod')->toArray();
            $productos = $locCods ? $todosProductos->filter(fn($p) => in_array($p->cod, $locCods)) : $todosProductos;
        } else {
            $productos = $todosProductos;
        }

        if ($productos->isEmpty()) {
            return 'No hay productos disponibles en este momento.';
        }

        $localPrices = $this->getLocalPrices($client);

        $bloques = [];

        foreach (['Unidad', 'Peso'] as $tipo) {
            $tipoLabel = $tipo === 'Unidad' ? '*Por unidad:*' : '*Por kilo:*';
            $grupos    = $productos->where('tipo', $tipo)
                ->groupBy(fn($p) => $p->desgrupo ?: 'General');

            if ($grupos->isEmpty()) continue;

            $lineas = [$tipoLabel];
            foreach ($grupos as $nombreGrupo => $items) {
                $lineas[] = "_{$nombreGrupo}_";
                foreach ($items as $p) {
                    $precio   = $this->fmt($this->precioFinal((float) $p->precio, $p->cod, $localPrices));
                    $unidad   = $tipo === 'Unidad' ? '/u' : '/kg';
                    $lineas[] = "• {$p->des}: \${$precio}{$unidad}";
                }
            }
            $bloques[] = implode("\n", $lineas);
        }

        return implode("\n\n", $bloques);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function callOpenAI(array $messages, array $tools = []): array
    {
        $payload = [
            'model'       => self::OPENAI_MODEL,
            'messages'    => $messages,
            'max_tokens'  => 500,
            'temperature' => 0.3,
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = null;
        $intentos = 2;
        for ($i = 0; $i < $intentos; $i++) {
            $response = Http::withToken($this->openaiKey())
                ->timeout(30)
                ->post(self::OPENAI_URL, $payload)
                ->json();

            if (isset($response['choices'])) break;

            Log::warning("OpenAI intento " . ($i + 1) . " fallido: " . json_encode($response));
            if ($i < $intentos - 1) sleep(1);
        }

        if (!isset($response['choices'])) {
            $error = $response['error']['message'] ?? json_encode($response);
            Log::error("OpenAI error tras {$intentos} intentos: {$error}");
            throw new \RuntimeException("Error al contactar OpenAI: {$error}");
        }

        // Guardar uso de tokens
        if (isset($response['usage'])) {
            try {
                $modelo     = $response['model'] ?? self::OPENAI_MODEL;
                $precios    = self::PRECIOS_OPENAI[$modelo] ?? self::PRECIOS_OPENAI[self::OPENAI_MODEL] ?? ['input' => 0, 'output' => 0];
                $input      = (int) $response['usage']['prompt_tokens'];
                $output     = (int) $response['usage']['completion_tokens'];
                $costoUsd   = round(
                    ($input  / 1_000_000 * $precios['input']) +
                        ($output / 1_000_000 * $precios['output']),
                    8
                );
                DB::table('ia_token_usos')->insert([
                    'modelo'            => $modelo,
                    'prompt_tokens'     => $input,
                    'completion_tokens' => $output,
                    'total_tokens'      => $input + $output,
                    'costo_usd'         => $costoUsd,
                    'created_at'        => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('ia_token_usos insert error: ' . $e->getMessage());
            }
        }

        return $response;
    }

    public function transcribeAudio(string $mediaId): string
    {
        $waToken = $this->whatsappKey();

        // 1. Obtener la URL de descarga del audio
        $meta = Http::withToken($waToken)
            ->get("https://graph.facebook.com/v19.0/{$mediaId}")
            ->json();

        $audioUrl = $meta['url'] ?? null;

        if (!$audioUrl) {
            throw new \RuntimeException('No se pudo obtener la URL del audio.');
        }

        // 2. Descargar el archivo de audio
        $audioContent = Http::withToken($waToken)
            ->get($audioUrl)
            ->body();

        // 3. Guardar temporalmente
        $tmpPath = sys_get_temp_dir() . '/' . $mediaId . '.ogg';
        file_put_contents($tmpPath, $audioContent);

        // 4. Enviar a Whisper para transcribir
        $response = Http::withToken($this->openaiKey())
            ->attach('file', file_get_contents($tmpPath), basename($tmpPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model'    => 'whisper-1',
                'language' => 'es',
            ]);

        unlink($tmpPath);

        return $response->json('text') ?? '';
    }

    public function uploadMedia(\Illuminate\Http\UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        $response = Http::withToken($this->whatsappKey())
            ->attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName(),
                ['Content-Type' => $mime]          // WhatsApp necesita el MIME en el attachment
            )
            ->attach('messaging_product', 'whatsapp')
            ->attach('type', $mime)
            ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/media');

        $mediaId = $response->json('id');

        if (!$mediaId) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error("WhatsApp uploadMedia error: {$error}");
            throw new \RuntimeException("Error al subir el archivo: {$error}");
        }

        return $mediaId;
    }

    public function sendWhatsappMedia(string $phone, string $mediaId, string $type, string $caption = ''): void
    {
        $body = ['id' => $mediaId];

        if ($caption) {
            $body['caption'] = $caption;
        }

        $response = Http::withToken($this->whatsappKey())
            ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/messages', [
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                'type'              => $type,
                $type               => $body,
            ]);

        if (!$response->successful()) {
            Log::error("sendWhatsappMedia error [{$type}] to {$phone}: " . $response->body());
        }
    }

    /**
     * Envía la notificación de nuevo pedido al negocio.
     * Si hay template configurado lo usa (sin restricción de ventana 24hs).
     * Si no, envía texto libre (requiere que el número haya escrito en las últimas 24hs).
     */
    public function sendRecordatorioTemplate(string $phone, string $templateRaw, string $nombre, string $mensaje): void
    {
        $parts    = explode('|', $templateRaw, 2);
        $template = trim($parts[0]);
        $langCode = isset($parts[1]) ? trim($parts[1]) : 'es_AR';

        // Los parámetros de template no admiten saltos de línea ni tabs
        $mensajePlano = preg_replace('/\s*\n\s*/', ' | ', trim($mensaje));
        $mensajePlano = preg_replace('/\t+/', ' ', $mensajePlano);
        $mensajePlano = preg_replace('/ {5,}/', '    ', $mensajePlano);

        try {
            $response = Http::withToken($this->whatsappKey())
                ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'template',
                    'template'          => [
                        'name'       => $template,
                        'language'   => ['code' => $langCode],
                        'components' => [[
                            'type'       => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $nombre],
                                ['type' => 'text', 'text' => $mensajePlano],
                            ],
                        ]],
                    ],
                ]);
            if (!$response->successful()) {
                Log::error("sendRecordatorioTemplate [{$phone}] HTTP {$response->status()}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("sendRecordatorioTemplate exception: " . $e->getMessage());
            throw $e;
        }
    }

    public function enviarNotifPedido(string $phone, int $nro, string $nomcli, string $detalle, float $total): void
    {
        $config   = IaEmpresa::first();
        $template = trim($config?->notif_template_nombre ?? '');

        if ($template) {
            // Soporte de idioma en el campo: "nombre_template|es_AR" (default es_AR)
            $parts    = explode('|', $template, 2);
            $template = trim($parts[0]);
            $langCode = isset($parts[1]) ? trim($parts[1]) : 'es_AR';

            try {
                $response = Http::withToken($this->whatsappKey())
                    ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/messages', [
                        'messaging_product' => 'whatsapp',
                        'to'                => $phone,
                        'type'              => 'template',
                        'template'          => [
                            'name'       => $template,
                            'language'   => ['code' => $langCode],
                            'components' => [[
                                'type'       => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => (string) $nro],
                                    ['type' => 'text', 'text' => $nomcli],
                                    ['type' => 'text', 'text' => $detalle],
                                    ['type' => 'text', 'text' => number_format($total, 2, ',', '.')],
                                ],
                            ]],
                        ],
                    ]);
                if (!$response->successful()) {
                    Log::error("enviarNotifPedido template error [{$phone}] HTTP {$response->status()}: " . $response->body());
                } else {
                    Log::info("enviarNotifPedido template enviada a {$phone} nro={$nro}");
                }
            } catch (\Throwable $e) {
                Log::error("enviarNotifPedido template exception: " . $e->getMessage());
            }
        } else {
            $texto = "🛒 *Pedido #{$nro}*\n👤 {$nomcli}\n\n{$detalle}\n\n*Total: $" . number_format($total, 2, ',', '.') . "*";
            $this->sendWhatsapp($phone, $texto);
        }
    }

    public function enviarNotifEstadoPedido(string $phone, string $nombre, string $mensaje): void
    {
        $config   = IaEmpresa::first();
        $template = trim($config?->notif_estado_template ?? '');

        if ($template) {
            $this->sendRecordatorioTemplate($phone, $template, $nombre, $mensaje);
        } else {
            $this->sendWhatsapp($phone, $mensaje);
        }
    }

    public function sendInteractiveButtons(string $phone, string $body, string $footer, array $buttons): void
    {
        $btnPayload = array_map(fn($b) => [
            'type'  => 'reply',
            'reply' => ['id' => $b['id'], 'title' => $b['title']],
        ], $buttons);

        try {
            $response = Http::withToken($this->whatsappKey())
                ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'interactive',
                    'interactive'       => [
                        'type'   => 'button',
                        'body'   => ['text' => $body],
                        'footer' => ['text' => $footer],
                        'action' => ['buttons' => $btnPayload],
                    ],
                ]);
            $this->lastOutgoingWamid = data_get($response->json(), 'messages.0.id');
            if (!$response->successful()) {
                Log::error("sendInteractiveButtons error [{$phone}]: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("sendInteractiveButtons exception [{$phone}]: " . $e->getMessage());
        }
    }

    public function sendInteractiveList(string $phone, string $body, string $buttonText, array $sections): void
    {
        try {
            $response = Http::withToken($this->whatsappKey())
                ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'interactive',
                    'interactive'       => [
                        'type'   => 'list',
                        'body'   => ['text' => $body],
                        'action' => [
                            'button'   => $buttonText,
                            'sections' => $sections,
                        ],
                    ],
                ]);
            $this->lastOutgoingWamid = data_get($response->json(), 'messages.0.id');
            if (!$response->successful()) {
                Log::error("sendInteractiveList error [{$phone}]: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("sendInteractiveList exception [{$phone}]: " . $e->getMessage());
        }
    }

    public function sendWhatsapp(string $phone, string $message): void
    {
        try {
            $response = Http::withToken($this->whatsappKey())
                ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ]);
            $this->lastOutgoingWamid = data_get($response->json(), 'messages.0.id');
            if (!$response->successful()) {
                Log::error("sendWhatsapp error [{$phone}] HTTP {$response->status()}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error('sendWhatsapp error: ' . $e->getMessage());
        }
    }

    public function sendWhatsappImageByUrl(string $phone, string $imageUrl, string $caption = ''): void
    {
        try {
            $body = ['link' => $imageUrl];
            if ($caption !== '') {
                $body['caption'] = $caption;
            }
            $response = Http::withToken($this->whatsappKey())
                ->post('https://graph.facebook.com/v19.0/' . $this->phoneNumberId() . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'image',
                    'image'             => $body,
                ]);
            if (!$response->successful()) {
                Log::error("sendWhatsappImageByUrl error to {$phone}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error('sendWhatsappImageByUrl error: ' . $e->getMessage());
        }
    }

    // ── Multi-canal ──────────────────────────────────────────────────────────

    /**
     * Envía texto al cliente según su canal (WhatsApp, Messenger o Instagram).
     * Para clientes de WhatsApp usa sendWhatsapp(); para Messenger/Instagram usa sendMessenger().
     * El canal se detecta por el prefijo del campo phone: "fb_" = Messenger, "ig_" = Instagram.
     */
    public function sendReply(\App\Models\Cliente $client, string $message): void
    {
        $phone = $client->phone;

        if (str_starts_with($phone, 'ig_')) {
            $recipientId = substr($phone, 3);
            $this->sendInstagram($recipientId, $message);
        } elseif (str_starts_with($phone, 'fb_')) {
            $recipientId = substr($phone, 3);
            $this->sendMessenger($recipientId, $message);
        } else {
            $this->sendWhatsapp($phone, $message);
        }
    }

    /**
     * Envía imagen al cliente según su canal.
     */
    public function sendReplyImage(\App\Models\Cliente $client, string $imageUrl, string $caption = ''): void
    {
        $phone = $client->phone;

        if (str_starts_with($phone, 'ig_')) {
            $recipientId = substr($phone, 3);
            $this->sendInstagram($recipientId, $imageUrl); // Instagram: imagen como URL en texto
            if ($caption) $this->sendInstagram($recipientId, $caption);
        } elseif (str_starts_with($phone, 'fb_')) {
            $recipientId = substr($phone, 3);
            $this->sendMessengerImage($recipientId, $imageUrl, $caption);
        } else {
            $this->sendWhatsappImageByUrl($phone, $imageUrl, $caption);
        }
    }

    /**
     * Envía un texto via Messenger / Instagram (Graph API).
     */
    public function sendMessenger(string $recipientId, string $message): void
    {
        $pageId    = config('api.messenger.fb_page_id') ?: config('api.messenger.page_id');
        $pageToken = config('api.messenger.token');

        if (!$pageId || !$pageToken) {
            Log::error('sendMessenger: page_id o token no configurados.');
            return;
        }

        Log::debug("sendMessenger → pageId={$pageId} recipientId={$recipientId} tokenLen=" . strlen($pageToken));

        try {
            $response = Http::timeout(10)
                ->post("https://graph.facebook.com/v22.0/me/messages?access_token={$pageToken}", [
                    'recipient'      => ['id' => $recipientId],
                    'message'        => ['text' => $message],
                    'messaging_type' => 'RESPONSE',
                ]);

            if (!$response->successful()) {
                Log::error('sendMessenger API error: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error('sendMessenger error: ' . $e->getMessage());
        }
    }

    /**
     * Envía texto via Instagram Direct (Graph API).
     * Usa el Instagram Business Account ID (page_id del tenant para Instagram).
     */
    public function sendInstagram(string $recipientId, string $message): void
    {
        $igAccountId = config('api.messenger.ig_account_id');
        $pageToken   = config('api.messenger.ig_token');

        if (!$igAccountId || !$pageToken) {
            Log::error('sendInstagram: ig_account_id o token no configurados.');
            return;
        }

        Log::debug("sendInstagram → igAccountId={$igAccountId} recipientId={$recipientId} tokenLen=" . strlen($pageToken));

        try {
            $response = Http::timeout(10)
                ->post("https://graph.facebook.com/v22.0/{$igAccountId}/messages?access_token={$pageToken}", [
                    'recipient'      => ['id' => $recipientId],
                    'message'        => ['text' => $message],
                    'messaging_type' => 'RESPONSE',
                ]);

            if (!$response->successful()) {
                Log::error('sendInstagram API error: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error('sendInstagram error: ' . $e->getMessage());
        }
    }

    /**
     * Envía una imagen via Messenger / Instagram (Graph API).
     */
    public function sendMessengerImage(string $recipientId, string $imageUrl, string $caption = ''): void
    {
        $pageId    = config('api.messenger.fb_page_id') ?: config('api.messenger.page_id');
        $pageToken = config('api.messenger.token');

        if (!$pageId || !$pageToken) {
            Log::error('sendMessengerImage: page_id o token no configurados.');
            return;
        }

        try {
            Http::timeout(10)
                ->post("https://graph.facebook.com/v22.0/me/messages?access_token={$pageToken}", [
                    'recipient'      => ['id' => $recipientId],
                    'message'        => [
                        'attachment' => [
                            'type'    => 'image',
                            'payload' => ['url' => $imageUrl, 'is_reusable' => true],
                        ],
                    ],
                    'messaging_type' => 'RESPONSE',
                ]);

            if ($caption) {
                // Messenger no soporta caption en attachment, enviar caption como mensaje separado
                Http::timeout(10)
                    ->post("https://graph.facebook.com/v22.0/me/messages?access_token={$pageToken}", [
                        'recipient'      => ['id' => $recipientId],
                        'message'        => ['text' => $caption],
                        'messaging_type' => 'RESPONSE',
                    ]);
            }
        } catch (\Throwable $e) {
            Log::error('sendMessengerImage error: ' . $e->getMessage());
        }
    }
}
