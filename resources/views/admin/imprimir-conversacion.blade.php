<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversación — {{ $cliente->name ?? $cliente->phone }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            font-size: 13px;
        }

        .page {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            padding: 32px 28px;
        }

        /* Cabecera */
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 2px solid #b91c1c;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }

        .header-left h1 {
            font-size: 18px;
            font-weight: 800;
            color: #b91c1c;
        }

        .header-left p {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }

        .header-right {
            font-size: 11px;
            color: #888;
            text-align: right;
        }

        .header-right strong {
            display: block;
            font-size: 12px;
            color: #444;
            font-weight: 600;
        }

        /* Separadores de fecha */
        .date-separator {
            text-align: center;
            margin: 16px 0 10px;
            position: relative;
        }

        .date-separator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0; right: 0;
            height: 1px;
            background: #e5e7eb;
        }

        .date-separator span {
            position: relative;
            background: #fff;
            padding: 0 10px;
            font-size: 11px;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Burbujas */
        .bubble-wrap {
            display: flex;
            margin-bottom: 6px;
        }

        .bubble-wrap.out { justify-content: flex-end; }
        .bubble-wrap.in  { justify-content: flex-start; }

        .bubble {
            max-width: 72%;
            padding: 8px 12px;
            border-radius: 16px;
            line-height: 1.45;
            word-break: break-word;
        }

        .bubble.out {
            background: #b91c1c;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .bubble.in {
            background: #f3f4f6;
            color: #1f2937;
            border-bottom-left-radius: 4px;
        }

        .bubble img {
            max-width: 100%;
            border-radius: 8px;
            display: block;
            margin-bottom: 4px;
        }

        .bubble .time {
            font-size: 10px;
            opacity: 0.6;
            margin-top: 4px;
            text-align: right;
        }

        .bubble.in .time { text-align: left; }

        /* Label del remitente (primera burbuja del bloque) */
        .sender-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin-bottom: 2px;
            padding: 0 4px;
            color: #6b7280;
        }

        .sender-label.out { text-align: right; color: #b91c1c; }
        .sender-label.in  { text-align: left;  color: #374151; }

        /* Resumen inferior */
        .summary {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #9ca3af;
        }

        /* Botón imprimir (solo pantalla) */
        .print-btn {
            display: block;
            text-align: center;
            margin: 24px auto 0;
            background: #b91c1c;
            color: #fff;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            padding: 10px 32px;
            cursor: pointer;
            letter-spacing: 0.03em;
        }

        .print-btn:hover { background: #991b1b; }

        @media print {
            body { background: #fff; }
            .print-btn { display: none; }
            .page { padding: 0; max-width: 100%; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Cabecera --}}
    <div class="header">
        <div class="header-left">
            <h1>🥩 Conversación WhatsApp</h1>
            <p>{{ $cliente->name ?? 'Sin nombre' }} &mdash; {{ $cliente->phone }}</p>
            @if($cliente->cuenta)
                <p style="margin-top:4px; font-size:11px; color:#4b5563;">Cuenta: {{ $cliente->cuenta->nom }} #{{ $cliente->cuenta->cod }}</p>
            @endif
        </div>
        <div class="header-right">
            <strong>Período</strong>
            {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
            <br><span style="margin-top:6px;display:inline-block;">Impreso: {{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    {{-- Mensajes --}}
    @php
        $currentDate = null;
        $prevDir = null;
        $total = $mensajes->count();
        $totalIn  = $mensajes->where('direction','incoming')->count();
        $totalOut = $mensajes->where('direction','outgoing')->count();
    @endphp

    @forelse($mensajes as $msg)
        @php
            $msgDate = $msg->created_at->format('d/m/Y');
            $dir = $msg->direction === 'outgoing' ? 'out' : 'in';
            $showSender = ($dir !== $prevDir);
            $prevDir = $dir;
        @endphp

        @if($msgDate !== $currentDate)
            @php $currentDate = $msgDate; @endphp
            <div class="date-separator"><span>{{ $msg->created_at->translatedFormat('l j \d\e F \d\e Y') }}</span></div>
            @php $prevDir = null; $showSender = true; @endphp
        @endif

        @if($showSender)
            <div class="sender-label {{ $dir }}">
                {{ $dir === 'out' ? 'Nosotros' : ($cliente->name ?? $cliente->phone) }}
            </div>
        @endif

        <div class="bubble-wrap {{ $dir }}">
            <div class="bubble {{ $dir }}">
                @if($msg->media_path)
                    <img src="{{ public_path($msg->media_path) }}" alt="Imagen">
                @endif
                @if($msg->message)
                    @php
                        $txt = e($msg->message);
                        $txt = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $txt);
                        $txt = preg_replace('/_([^_]+)_/', '<em>$1</em>', $txt);
                        $txt = nl2br($txt);
                    @endphp
                    <div>{!! $txt !!}</div>
                @endif
                <div class="time">{{ $msg->created_at->format('H:i') }}</div>
            </div>
        </div>
    @empty
        <p style="text-align:center; color:#9ca3af; padding: 40px 0; font-size:14px;">
            No hay mensajes en el período seleccionado.
        </p>
    @endforelse

    {{-- Resumen --}}
    <div class="summary">
        <span>Total mensajes: <strong style="color:#374151;">{{ $total }}</strong></span>
        <span>Recibidos: <strong style="color:#374151;">{{ $totalIn }}</strong></span>
        <span>Enviados: <strong style="color:#374151;">{{ $totalOut }}</strong></span>
    </div>

</div>

<button class="print-btn" onclick="window.print()">🖨 Imprimir</button>

</body>
</html>
