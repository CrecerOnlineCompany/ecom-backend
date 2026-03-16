<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket Termico - {{ $ticket->ticket_number }}</title>
    <style>
        @page {
            margin: 3mm;
        }
        body {
            font-family: "Courier New", monospace;
            font-size: 10px;
            color: #000;
            margin: 0;
            padding: 0;
            width: 74mm;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .row {
            display: block;
            margin: 2px 0;
        }
        .title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .seats {
            margin-top: 4px;
        }
        .seat-item {
            margin-bottom: 2px;
        }
        .qr {
            margin-top: 8px;
            text-align: center;
        }
        .qr img {
            width: 90px;
            height: 90px;
        }
        .small {
            font-size: 9px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="center">
        <div class="title">{{ $cinema->name ?? ($ticket->cinema_name ?? 'CINEA') }}</div>
        <div class="bold">{{ $room->name ?? ($ticket->room_name ?? 'Sala') }}</div>
    </div>

    <div class="line"></div>

    <div class="row bold">Ticket: {{ $ticket->ticket_number }}</div>
    <div class="row">Estado: {{ strtoupper((string) $ticket->status) }}</div>
    @if(!empty($order?->order_number))
        <div class="row">Orden: {{ $order->order_number }}</div>
    @endif
    <div class="row">Cliente: {{ $ticket->customer_name ?? 'N/A' }}</div>

    <div class="line"></div>

    <div class="row bold">{{ $movie->title ?? ($ticket->movie_title ?? 'Pelicula') }}</div>
    <div class="row">
        Fecha:
        {{ optional($screening?->start_time)->format('d/m/Y') ?? optional($ticket->screening_start_time)->format('d/m/Y') ?? '-' }}
    </div>
    <div class="row">
        Hora:
        {{ optional($screening?->start_time)->format('H:i') ?? optional($ticket->screening_start_time)->format('H:i') ?? '-' }}
    </div>
    <div class="row">Formato: {{ $ticket->screening_format ?? '-' }}</div>

    <div class="line"></div>

    <div class="bold">Asientos</div>
    <div class="seats">
        @forelse($seats as $seat)
            <div class="seat-item">
                {{ $seat['seat_code'] ?? '-' }} - ${{ $seat['price'] ?? '0.00' }}
            </div>
        @empty
            <div class="seat-item">Sin asientos asociados</div>
        @endforelse
    </div>

    <div class="line"></div>

    <div class="row bold center">TOTAL: ${{ number_format((float) $ticket->price, 2, '.', ',') }}</div>
    <div class="row center">
        Compra: {{ optional($ticket->purchased_at ?? $ticket->created_at)->format('d/m/Y H:i') ?? '-' }}
    </div>

    <div class="qr">
        <img src="{{ $qr_code }}" alt="QR">
    </div>
    <div class="small center">{{ $verify_url }}</div>

    <div class="line"></div>
    <div class="center small">Valido solo con QR</div>
</body>
</html>
