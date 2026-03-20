<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Carrito;
use App\Models\Empresa;
use App\Models\IaEmpresa;
use App\Models\Factventas;
use App\Models\Localidad;
use App\Models\Pedidosia;
use App\Models\Producto;
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

    // -------------------------------------------------------------------------
    // Punto de entrada principal
    // -------------------------------------------------------------------------

    public function process($client, $message, ?array $image = null): string
    {
        // Cliente en modo humano: no procesar con IA
        if ($client->estado === 'humano') {
            return '';
        }

        // Registro inicial: nombre → localidad → provincia
        if (empty($client->name) || $client->estado === 'esperando_nombre') {
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
                    $client->update(['estado' => 'humano']);
                    $this->sendWhatsapp($client->phone, $saludo);
                    return $saludo;
                }

                $client->update(['estado' => 'esperando_nombre']);
                $saludo   = $nombreIa ? "¡Hola! Soy {$nombreIa}, el asistente del negocio." : "¡Hola! Soy el asistente del negocio.";
                $response = "{$saludo} ¿Cuál es tu nombre?";
                $this->sendWhatsapp($client->phone, $response);
                return $response;
            } else {
                $nombre = ucfirst(strtolower(trim($message)));
                $client->update(['name' => $nombre, 'estado' => 'esperando_localidad']);
                $localidades = Localidad::where('activo', true)->pluck('nombre')->implode(', ');
                $response = "¡Hola, {$nombre}! ¿En qué localidad estás?"
                    . ($localidades ? " (Repartimos en: {$localidades})" : '');
            }
            $this->sendWhatsapp($client->phone, $response);
            return $response;
        }

        if ($client->estado === 'esperando_localidad') {
            $input       = trim($message);
            $localidades = Localidad::where('activo', true)->get();

            $match = $localidades->first(
                fn($l) => stripos($l->nombre, $input) !== false || stripos($input, $l->nombre) !== false
            );

            if ($match) {
                $client->update([
                    'localidad'    => $match->nombre,
                    'localidad_id' => $match->id,
                    'estado'       => 'esperando_calle',
                ]);
                $diasLabel = IaEmpresa::DIAS_LABEL;
                $dias      = $match->dias_reparto ?? [];
                $diasTexto = !empty($dias)
                    ? 'Repartimos en tu zona los: ' . implode(', ', array_map(fn($d) => $diasLabel[$d], $dias)) . '. '
                    : '';
                $response = "{$diasTexto}¿Cuál es tu calle y número de entrega? (ej: Italia 1234)";
            } else {
                $client->update([
                    'localidad'    => ucwords(strtolower($input)),
                    'localidad_id' => null,
                    'estado'       => 'activo',
                ]);
                $lista    = $localidades->pluck('nombre')->implode(', ');
                $response = "Anotado, {$client->name}. Por el momento no tenemos reparto a tu zona."
                    . ($lista ? " Repartimos en: {$lista}." : '')
                    . " Podés pasar a retirar al local. ¿En qué puedo ayudarte?";
            }

            $this->sendWhatsapp($client->phone, $response);
            return $response;
        }

        if ($client->estado === 'esperando_calle') {
            // Intenta separar "Calle 1234" → calle=Calle, numero=1234
            $input = trim($message);
            if (preg_match('/^(.+?)\s+(\d[\w-]*)$/', $input, $m)) {
                $client->update(['calle' => trim($m[1]), 'numero' => $m[2], 'estado' => 'esperando_dato_extra']);
            } else {
                $client->update(['calle' => $input, 'numero' => null, 'estado' => 'esperando_dato_extra']);
            }
            $response = "¿Tenés algún dato extra? (piso, depto, referencia) — respondé *no* para omitir.";
            $this->sendWhatsapp($client->phone, $response);
            return $response;
        }

        if ($client->estado === 'esperando_dato_extra') {
            $input = trim($message);
            $datoExtra = preg_match('/^no$/i', $input) ? null : $input;
            $client->update(['dato_extra' => $datoExtra, 'estado' => 'activo']);
            $dir      = trim("{$client->calle} {$client->numero}");
            $response = "¡Todo listo, {$client->name}! Dirección guardada: {$dir}"
                . ($datoExtra ? " ({$datoExtra})" : '') . ". ¿En qué puedo ayudarte?";
            $this->sendWhatsapp($client->phone, $response);
            return $response;
        }

        $response = $this->askChatGPT($message, $client, $image);
        $this->sendWhatsapp($client->phone, $response);
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
            return $this->handleToolCalls($choice, $messages, $cliente, $tools, $puedePedir);
        }

        return $choice['message']['content'];
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

        // Lista cacheada 5 minutos — solo nombres, sin precios
        // Los precios se obtienen siempre frescos via tool ver_producto
        $tenantId = app(\App\Services\TenantManager::class)->get()?->id ?? 0;
        $lista = Cache::remember('productos_bot_lista_' . $tenantId, 300, function () {
            $productos = Producto::paraBot()->get();

            $formatear = function ($p) {
                $linea = $p->des;
                if (!empty($p->descripcion) && $p->descripcion !== 'sinimagen.webp') {
                    $linea .= " ({$p->descripcion})";
                }
                if (!empty($p->notas_ia)) {
                    $linea .= " [IA: {$p->notas_ia}]";
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

            return implode("\n\n", $bloques);
        });

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

        // Días de reparto: usa la localidad del cliente si tiene una configurada, si no la global
        $diasLabel     = IaEmpresa::DIAS_LABEL;
        $localidadObj = $cliente->localidad_id
            ? Localidad::find($cliente->localidad_id)
            : ($cliente->localidad
                ? Localidad::where('activo', true)
                ->whereRaw('LOWER(nombre) = ?', [strtolower($cliente->localidad)])
                ->first()
                : null);

        // Si encontró la localidad por nombre pero el cliente no tiene localidad_id, lo actualiza
        if ($localidadObj && !$cliente->localidad_id) {
            $cliente->update(['localidad_id' => $localidadObj->id]);
        }
        $diasReparto   = $localidadObj?->dias_reparto ?? $empresa?->bot_dias_reparto ?? [];
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

        // Todas las zonas de entrega activas con sus días
        $todasLasZonas = Cache::remember('bot_zonas_entrega_' . $tenantId, 3600, function () use ($diasLabel) {
            return Localidad::where('activo', true)
                ->get()
                ->map(function ($l) use ($diasLabel) {
                    $dias = !empty($l->dias_reparto)
                        ? implode(', ', array_map(fn($d) => $diasLabel[$d] ?? $d, $l->dias_reparto))
                        : 'días a confirmar';
                    return "{$l->nombre} (reparto los: {$dias})";
                })
                ->implode(' | ');
        });

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

        // Próximo día de reparto disponible para este cliente
        $proximoRepartoTexto = '';
        if (!empty($diasReparto)) {
            for ($i = 1; $i <= 7; $i++) {
                $candidato = now()->addDays($i);
                if (in_array((int) $candidato->format('w'), $diasReparto)) {
                    $proximoRepartoTexto = $candidato->locale('es')->isoFormat('dddd D [de] MMMM');
                    break;
                }
            }
        }

        // Texto dinámico para el paso 3 del flujo de pedido
        $entregasOpciones = array_filter([
            $permiteEnvio  ? 'envío' : null,
            $permiteRetiro ? 'retiro en local'   : null,
        ]);
        $mediosOpciones = array_map(fn($m) => $mediosLabel[$m] ?? $m, $mediosHabilitados);

        $paso3Fecha = $proximoRepartoTexto
            ? "¿Lo querés para el próximo {$proximoRepartoTexto}?" . (count($diasReparto) > 1 ? ' (o indicá otra fecha de reparto disponible)' : '')
            : '¿Para cuándo?';
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

        if (!$puedePedir)    $configNegocio .= "\n\nIMPORTANTE: No podés tomar pedidos. Solo informás precios y describís productos. Si el cliente quiere pedir, indicale que contacte al negocio directamente.";
        if (!$puedeSupgerir) $configNegocio .= "\nNo sugieras productos de forma proactiva. Solo respondé lo que el cliente consulte.";

        $costoExtra = $this->costoExtraCliente($cliente);
        if ($costoExtra > 0) {
            $configNegocio .= "\n\n⚠️ IMPORTANTE — PRECIOS PARA ESTE CLIENTE: Este cliente es de {$cliente->localidad}. A TODOS los precios de la lista debés sumarle \${$this->fmt($costoExtra)} antes de responder. Ejemplo: si el precio base es \$18000/kg, respondé \$" . $this->fmt(18000 + $costoExtra) . "/kg. NUNCA menciones recargo, zona ni costo extra. Solo dá el precio final.";
        }

        if ($infoNegocio)        $configNegocio .= "\n\nInformación del negocio:\n{$infoNegocio}";
        if ($instrucciones)      $configNegocio .= "\n\nInstrucciones especiales:\n{$instrucciones}";

        $nombreIa  = trim($empresa?->nombre_ia ?? '');
        $identidad = $nombreIa ? "Te llamás {$nombreIa}." : '';

        $messages[] = [
            'role'    => 'system',
            'content' => "Sos el asistente virtual del negocio. {$identidad} Amable, breve y directo. Respondé siempre en español argentino.
Respondés consultas sobre: pedidos, precios, productos, horarios, dirección, formas de pago, días de reparto y cualquier información del negocio que tengas disponible.
Para cualquier otra consulta ajena al negocio, decí amablemente que no podés ayudar con eso.
Formato de precios: NUNCA uses separador de miles. Usá coma para decimales solo si hay centavos. Ejemplos correctos: \$1500 | \$36000 | \$2800,50. Nunca: \$1.500,00 ni \$36,000 ni \$21000,00.
Hoy es {$fecha}.
Cliente: {$nombre}{$cuentaTexto}
Último pedido: {$ultimoPedidoTexto}
{$favoritosTexto}
{$ultimaDirTexto}{$configNegocio}

Productos disponibles (solo nombres — para precios usá siempre ver_producto):
{$lista}

" . ($puedeSupgerir ? "════════════════════════════════
FLUJO 1 — SUGERIR
════════════════════════════════
Activar cuando: el cliente saluda, no sabe qué quiere, pide recomendación o menciona una ocasión (asado, cumpleaños, etc.).
Pasos:
1. Si menciona una ocasión, calculá cantidades según las porciones estándar y mostrá solo productos de la lista disponible.
2. Si no menciona ocasión, sugerí sus favoritos" . ($puedeMasVendidos ? " o los más populares" : "") . ".
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
1. En cuanto tenés producto + cantidad, llamá INMEDIATAMENTE agregar_al_carrito. No pidas confirmación extra ni resumas antes. Si el cliente dice si, dale, esta bien o similar con una cantidad implícita o explícita → accioná.
2. Podés agregar múltiples productos en una sola llamada a agregar_al_carrito.
3. Mostrá el resumen con ver_carrito. Si hay alertas de precio (⚠️) o producto no disponible (❌), ofrecé actualizar o eliminar el ítem antes de continuar.
4. Preguntá en un solo mensaje: {$paso3Fecha} | {$paso3Entrega} | {$paso3Pago}
5. Si eligió ENVÍO:
   - Si hay última dirección registrada, ofrecésela para confirmar o cambiar.
   - Si no hay, pedí calle, número y localidad (dato extra como piso/depto es opcional).
   - Confirmale la dirección antes de proceder.
6. Cuando tenés todos los datos (fecha, tipo_entrega, forma_pago, dirección si aplica), llamá DIRECTAMENTE a crear_pedido sin enviar ningún mensaje previo.
   - Fecha en obs si el cliente dice horario o turno (no en fecha_entrega).
7. Una vez creado el pedido (ves 'Pedido #X registrado' en la respuesta del sistema), confirmáselo al cliente y no vuelvas a pedir confirmación. Si pregunta por el total o detalle, usá ver_pedidos.

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
" . ($puedePedir ? "- agregar_al_carrito → agregar/modificar ítems del carrito
- ver_carrito → resumen con totales, tiempo restante y validación de precios. SIEMPRE usá esta herramienta cuando el cliente pregunta por el total, precio o detalle de su carrito; nunca calcules precios manualmente
- vaciar_carrito → limpiar el carrito
- crear_pedido → confirmar y registrar el pedido
- ver_pedidos → historial y estado de pedidos
- cancelar_pedido → cancelar un pedido pendiente
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
                return in_array($tool['function']['name'], ['ver_producto', 'lista_productos']);
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

                $result = match ($funcName) {
                    'agregar_al_carrito' => $this->agregarAlCarrito($cliente, $args['items'] ?? []),
                    'ver_carrito'        => $this->verCarrito($cliente),
                    'vaciar_carrito'     => $this->vaciarCarrito($cliente),
                    'crear_pedido'       => $this->createOrder($cliente, $args['fecha_entrega'] ?? now()->addDay()->format('Y-m-d'), $args['tipo_entrega'] ?? 'retiro', $args['forma_pago'] ?? 'efectivo', $args['calle'] ?? '', $args['numero'] ?? '', $args['localidad'] ?? '', $args['dato_extra'] ?? '', $args['obs'] ?? ''),
                    'ver_pedidos'        => $this->orderStatus($cliente),
                    'ver_precios'        => $this->priceList($cliente),
                    'ver_producto'       => $this->verProducto($cliente, $args['nombre'] ?? '', (bool) ($args['solicita_precio'] ?? false), $puedePedir),
                    'cancelar_pedido'    => $this->cancelOrder($cliente, (int) $this->parsearNumero($args['nro'] ?? 0)),
                    default              => 'Función desconocida.',
                };

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

    // -------------------------------------------------------------------------
    // Carrito
    // -------------------------------------------------------------------------


    private function getCarrito($client): ?Carrito
    {
        return Carrito::where('cliente_id', $client->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    private function agregarAlCarrito($client, array $items): string
    {
        $registro   = $this->getCarrito($client);
        $carrito    = $registro ? $registro->items : [];
        $productos  = Producto::paraBot()->get();
        $costoExtra = $this->costoExtraCliente($client);
        $normalize  = fn(string $s) => strtolower(\Illuminate\Support\Str::ascii($s));
        $errores    = [];

        foreach ($items as $item) {
            $descrip  = trim($item['descrip'] ?? '');
            $cantidad = $this->parsearNumero($item['cantidad'] ?? 0);

            if ($descrip === '') continue;

            // Solo coincidencia exacta (normalizada): el nombre debe ser igual al de la lista
            $match = $productos->first(fn($p) => $normalize($p->des) === $normalize($descrip));

            if (!$match) {
                $errores[] = "Producto '{$descrip}' no encontrado en la lista. Llamá ver_producto para encontrar el nombre exacto correcto antes de agregar.";
                continue;
            }

            // notas_ia con "precio fijo" → se cobra por unidad aunque tipo sea Peso
            $precioFijo = !empty($match->notas_ia) && stripos($match->notas_ia, 'precio fijo') !== false;
            $esPeso     = $match->tipo !== 'Unidad' && !$precioFijo;
            $precio     = (float) $match->precio + $costoExtra;

            $cant  = 0;
            $kilos = 0;

            if ($esPeso) {
                // Si la descripción tiene peso por unidad (ej: "aprox. 0.15kg c/u")
                // y la cantidad es un entero, PHP convierte unidades → kg
                $pesoUnitario = $this->parsePesoUnitario($match->descripcion ?? '');
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

        if ($registro) {
            $registro->update([
                'items'      => $carrito,
                'expires_at' => now()->addMinutes(15),
            ]);
        } else {
            $registro = Carrito::create([
                'cliente_id' => $client->id,
                'items'      => $carrito,
                'expires_at' => now()->addMinutes(15),
            ]);
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

        $costoExtra = $this->costoExtraCliente($client);

        $resultado = $this->formatCarrito($registro->items);

        // Tiempo restante
        $minutos = max(0, (int) now()->diffInMinutes($registro->expires_at, false));
        if ($minutos <= 0) {
            $resultado .= "\n\n⏱ El carrito está por vencer. Confirmá tu pedido ahora.";
        } elseif ($minutos <= 5) {
            $resultado .= "\n\n⏱ El carrito vence en {$minutos} min. Confirmá pronto.";
        } else {
            $resultado .= "\n\n⏱ El carrito vence en {$minutos} min.";
        }

        // Validar existencia y precios actuales
        $alertas = $this->validarCarrito($registro->items, $costoExtra);
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

    private function costoExtraCliente($client): float
    {
        if ($client->localidad_id) {
            return (float) (Localidad::find($client->localidad_id)?->costo_extra ?? 0);
        }
        if ($client->localidad) {
            $loc = Localidad::where('activo', true)
                ->whereRaw('LOWER(nombre) = ?', [strtolower($client->localidad)])
                ->first();
            if ($loc) {
                $client->update(['localidad_id' => $loc->id]);
                return (float) ($loc->costo_extra ?? 0);
            }
        }
        return 0.0;
    }

    private function formatCarrito(array $carrito): string
    {
        if (empty($carrito)) {
            return 'El carrito está vacío.';
        }

        $lineas = [];
        $total  = 0;

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
            $total    += $item['neto'];
        }

        $lineas[] = 'TOTAL aprox.: $' . $this->fmt($total) . ' _(puede variar según el peso final)_';

        return implode("\n", $lineas);
    }

    private function validarCarrito(array $carrito, float $costoExtra = 0): array
    {
        if (empty($carrito)) {
            return [];
        }

        $codsEnCarrito = array_filter(array_column($carrito, 'cod'));
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

            $preActual = (float) $productosActuales[$item['cod']]->precio + $costoExtra;
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

    // Extrae el peso por unidad de la descripción del producto (ej: "aprox. 0.15kg c/u" → 0.15)
    private function parsePesoUnitario(string $descripcion): ?float
    {
        if (preg_match('/aprox[.\s]*(\d+(?:[.,]\d+)?)\s*kg\s*c\//i', $descripcion, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        return null;
    }

    private function createOrder($client, string $fechaEntrega = '', string $tipoEntrega = 'retiro', string $formaPago = 'efectivo', string $calle = '', string $numero = '', string $localidad = '', string $datoExtra = '', string $obs = ''): string
    {
        $registro = $this->getCarrito($client);
        $carrito  = $registro ? $registro->items : [];

        if (empty($carrito)) {
            return 'El carrito está vacío. Agregá productos antes de confirmar el pedido.';
        }

        $nro    = (Pedido::max('nro') ?? 0) + 1;
        $fecha  = $fechaEntrega ?: now()->format('Y-m-d');
        $codcli = $client->cuenta ? $client->cuenta->cod : $client->id;
        $nomcli = $client->cuenta ? $client->cuenta->nom : $client->name;

        $precioActual = Producto::paraBot()
            ->whereIn('tablaplu.cod', array_column($carrito, 'cod'))
            ->get()
            ->keyBy('cod');

        $costoExtra = $this->costoExtraCliente($client);

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
            $preActual     = (float) $precioActual[$item['cod']]->precio;
            $preConExtra   = $preActual + $costoExtra;
            if (abs($preConExtra - $precio) > 0.01) {
                $base   = $item['tipo'] !== 'Unidad' ? $item['kilos'] : $item['cant'];
                $neto   = round($preConExtra * $base, 2);
                $precio = $preConExtra;
                $alertas[] = "{$item['des']} (precio actualizado a $" . $this->fmt($preConExtra) . ')';
            }

            Pedido::create([
                'fecha'     => $fecha,
                'nro'       => $nro,
                'nomcli'    => $nomcli,
                'codcli'    => $codcli,
                'codigo'    => $item['cod'] ?? null,
                'descrip'   => $item['des'],
                'kilos'     => $item['kilos'],
                'cant'      => $item['cant'],
                'precio'    => $precio,
                'neto'      => $neto,
                'estado'    => Pedido::ESTADO_PENDIENTE,
                'obs'       => $obs,
                'pedido_at' => now(),
                'venta'     => 0,
            ]);
        }

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

        $registro?->delete();

        // Notificar al local
        $config = Cache::remember('bot_empresa_config_' . (app(\App\Services\TenantManager::class)->get()?->id ?? 0), 300, fn() => IaEmpresa::first());
        $telLocal = trim($config?->telefono_pedidos ?? '');
        if ($telLocal) {
            $entregaTexto = $tipoEntrega === 'envio'
                ? "Envío: {$calle} {$numero}" . ($localidad ? ", {$localidad}" : '') . ($datoExtra ? " ({$datoExtra})" : '')
                : 'Retiro en local';

            $itemsTexto = implode("\n", array_map(
                fn($item) => "  • " . ($item['cant'] > 0 ? "{$item['cant']}u" : "{$item['kilos']}kg") . " {$item['des']} — $" . $this->fmt($item['neto']),
                array_filter($carrito, fn($i) => !in_array($i['des'], $omitidos))
            ));

            $notif = "🛒 *Pedido #{$nro}*\n"
                . "👤 {$nomcli} | {$client->phone}\n"
                . "📅 Entrega: {$fecha}\n"
                . "📦 {$entregaTexto}\n"
                . "💳 Pago: {$formaPago}\n\n"
                . "{$itemsTexto}\n\n"
                . "*Total: $" . $this->fmt($total) . "*";

            if ($obs) $notif .= "\n📝 {$obs}";

            $this->sendWhatsapp($telLocal, $notif);
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

        return $pedidos->map(function ($items, $nro) use ($facturas) {
            $first  = $items->first();
            $fecha  = $first->fecha;
            $estado = $first->estado_texto;

            // Pedido pendiente: mostrar lo que pidió
            if ($first->estado != Pedido::ESTADO_FINALIZADO) {
                $detalle = $items->map(
                    fn($p) =>
                    $p->cant > 1 ? "{$p->cant}u {$p->descrip}" : "{$p->kilos}kg {$p->descrip}"
                )->implode(', ');
                return "Pedido #{$nro} ({$fecha}): {$detalle} — {$estado}";
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
                return "Pedido #{$nro} ({$fecha}): {$estado}\nComprobante: {$comprobante}\nDetalle real: {$detalle}\nTotal: $" . number_format($total, 2, ',', '.');
            }

            // Finalizado pero sin factura aún
            $detalle = $items->map(
                fn($p) =>
                $p->cant > 1 ? "{$p->cant}u {$p->descrip}" : "{$p->kilos}kg {$p->descrip}"
            )->implode(', ');
            return "Pedido #{$nro} ({$fecha}): {$detalle} — {$estado} (sin comprobante asociado aún)";
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

        $pedidos->each->delete();

        return "Pedido #{$nro} cancelado correctamente.";
    }

    private function verProducto($client, string $nombre, bool $solicitaPrecio = false, bool $puedePedir = true): string
    {
        // Sin cache: siempre precio e imagen actualizados desde la BD
        $productos    = Producto::paraBot()->get();
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
            return "Encontré '{$producto->des}' como posible coincidencia para '{$nombre}'. Preguntale al cliente: '¿Te referís a {$producto->des}?' — si confirma (dice sí, ese, dale, etc.) y da una cantidad, llamá DIRECTAMENTE agregar_al_carrito con nombre='{$producto->des}' y la cantidad indicada. Si confirma sin dar cantidad, pedile la cantidad y luego llamá agregar_al_carrito. NO volvás a llamar ver_producto para este mismo producto.";
        }

        $caption = "*{$producto->des}*";
        if (!empty($producto->descripcion)) {
            $caption .= "\n_{$producto->descripcion}_";
        }
        if ($solicitaPrecio) {
            $costoExtra = $this->costoExtraCliente($client);
            $precio     = $this->fmt((float) $producto->precio + $costoExtra);
            $unidad     = $producto->tipo === 'Unidad' ? 'por unidad' : 'por kg';
            $caption   .= "\n\${$precio} ({$unidad})";
        }

        // Enviar imagen con descripción y precio como caption (todo en 1 mensaje)
        Log::info("verProducto [{$producto->des}] imagen='{$producto->imagen}'");
        if (!empty($producto->imagen) && $producto->imagen !== 'sinimagen.webp') {
            $path = public_path($producto->imagen);
            Log::info("verProducto path={$path} exists=" . (file_exists($path) ? 'SI' : 'NO'));
            if (file_exists($path)) {
                try {
                    $mime    = $this->mimeFromPath($path);
                    $mediaId = $this->uploadMediaFromPath($path, $mime);
                    $this->sendWhatsappMedia($client->phone, $mediaId, 'image', $caption);
                    return $puedePedir
                        ? "IMAGEN_ENVIADA para {$producto->des}. El cliente ya recibió imagen, descripción y precio. Preguntale '¿Lo agregamos al carrito? ¿Cuánto querés?' (en un solo mensaje, sin repetir datos del producto). Si confirma con cantidad, llamá agregar_al_carrito. Si dice 'sí' sin cantidad, pedile solo la cantidad. NO volvás a llamar ver_producto para este mismo producto."
                        : "IMAGEN_ENVIADA para {$producto->des}. El cliente ya recibió imagen, descripción y precio. NO preguntes cantidad ni ofrezcas agregar al carrito. Si quiere comprar, indicale que contacte al negocio directamente.";
                } catch (\Throwable $e) {
                    Log::error("verProducto upload error [{$producto->des}]: {$e->getMessage()}");
                }
            }
        }

        // Sin imagen: enviar el texto directo y decirle a GPT que no lo repita
        $this->sendWhatsapp($client->phone, $caption);
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
        $productos = Producto::paraBot()->get();

        if ($productos->isEmpty()) {
            return 'No hay productos disponibles en este momento.';
        }

        $costoExtra = $this->costoExtraCliente($client);

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
                    $precio   = $this->fmt((float) $p->precio + $costoExtra);
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
}
