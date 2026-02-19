<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden - {{ $order->order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            background-color: #fff;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #1a1a1a;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .order-number {
            font-size: 14px;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #664d03;
        }

        .status-processing {
            background-color: #e7d4f5;
            color: #663399;
        }

        .status-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #842029;
        }

        .cinema-info {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }

        .cinema-info h2 {
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .cinema-info p {
            font-size: 13px;
            color: #666;
        }

        .order-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .info-box {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-weight: bold;
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 13px;
            color: #1a1a1a;
        }

        .tickets-section {
            margin-bottom: 20px;
        }

        .tickets-section h3 {
            font-size: 16px;
            color: #1a1a1a;
            margin-bottom: 15px;
            font-weight: bold;
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 10px;
        }

        .ticket-card {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .ticket-number {
            font-weight: bold;
            font-size: 13px;
            color: #e74c3c;
        }

        .ticket-status {
            font-size: 10px;
        }

        .ticket-details {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .ticket-seats {
            background-color: white;
            padding: 10px;
            border-radius: 3px;
            margin-top: 10px;
        }

        .seat-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .seat-badge {
            background-color: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }

        .qr-mini {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 15px;
            padding: 10px;
            background-color: white;
            border-radius: 3px;
        }

        .qr-image {
            width: 100px;
            height: 100px;
            margin-bottom: 5px;
        }

        .qr-label {
            font-size: 9px;
            color: #666;
            text-align: center;
        }

        .customer-section {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .customer-section h3 {
            font-size: 14px;
            color: #1a1a1a;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .customer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 12px;
        }

        .detail-row {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 3px;
        }

        .detail-value {
            color: #1a1a1a;
        }

        .total-section {
            text-align: right;
            padding: 20px;
            background-color: #1a1a1a;
            color: white;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .total-section .label {
            font-size: 14px;
            margin-bottom: 10px;
        }

        .total-section .amount {
            font-size: 28px;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            border-top: 2px solid #ddd;
            padding-top: 15px;
            margin-top: 20px;
            font-size: 11px;
            color: #999;
        }

        .movie-title {
            font-weight: bold;
            color: #1a1a1a;
        }

        .screening-info {
            font-size: 12px;
            color: #666;
        }

        @page {
            size: A4;
            margin: 10mm;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                padding: 0;
            }
            .ticket-card {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>COMPROBANTE DE COMPRA</h1>
            <div class="order-number">Orden: {{ $order->order_number }}</div>
            <span class="status-badge status-{{ strtolower($order->status) }}">
                {{ strtoupper($order->status) }}
            </span>
        </div>

        <!-- Cinema Info -->
        <div class="cinema-info">
            <h2>{{ $cinema->name }}</h2>
            <p>Sala: {{ $room->name }}</p>
        </div>

        <!-- Order Info -->
        <div class="order-info">
            <div class="info-box">
                <div class="info-label">Película</div>
                <div class="info-value">{{ $movie->title }}</div>
            </div>
            <div class="info-box">
                <div class="info-label">Fecha de Función</div>
                <div class="info-value">{{ $screening->start_time->format('d/m/Y H:i') }}</div>
            </div>
            <div class="info-box">
                <div class="info-label">Total de Boletos</div>
                <div class="info-value">{{ count($tickets) }}</div>
            </div>
        </div>

        <!-- Tickets -->
        <div class="tickets-section">
            <h3>ENTRADAS COMPRADAS</h3>

            @foreach($tickets as $index => $ticketData)
            <div class="ticket-card">
                <div class="ticket-header">
                    <div class="ticket-number">
                        Boleto #{{ $index + 1 }}: {{ $ticketData['ticket']['ticket_number'] }}
                    </div>
                    <span class="status-badge ticket-status status-{{ strtolower($ticketData['ticket']['status']) }}">
                        {{ strtoupper($ticketData['ticket']['status']) }}
                    </span>
                </div>

                <div class="ticket-details">
                    <div class="detail-row">
                        <div class="detail-label">Asientos</div>
                        <div class="detail-value">{{ count($ticketData['seats']) }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Precio</div>
                        <div class="detail-value">${{ number_format($ticketData['ticket']['price'], 2, '.', ',') }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Comprado</div>
                        <div class="detail-value">{{ $ticketData['ticket']['created_at'] }}</div>
                    </div>
                </div>

                <div class="ticket-seats">
                    <strong style="font-size: 11px;">Asientos del Boleto:</strong>
                    <div class="seat-list">
                        @foreach($ticketData['seats'] as $seat)
                        <div class="seat-badge">{{ $seat['seat_code'] }}</div>
                        @endforeach
                    </div>
                </div>

                <div class="qr-mini">
                    <img src="{{ $ticketData['qr_code'] }}" alt="QR Code" class="qr-image">
                    <div class="qr-label">Escanear para validar entrada</div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Customer Info -->
        <div class="customer-section">
            <h3>INFORMACIÓN DE COMPRADOR</h3>
            <div class="customer-details">
                <div class="detail-row">
                    <div class="detail-label">Nombre:</div>
                    <div class="detail-value">{{ $order->customer_name }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value">{{ $order->customer_email }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Teléfono:</div>
                    <div class="detail-value">{{ $order->customer_phone ?? 'N/A' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Fecha de Compra:</div>
                    <div class="detail-value">{{ $order->created_at->format('d/m/Y H:i:s') }}</div>
                </div>
            </div>
        </div>

        <!-- Total -->
        <div class="total-section">
            <div class="label">MONTO TOTAL</div>
            <div class="amount">${{ $total_amount }}</div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                Este documento es un comprobante de compra de entradas. Conserve este o una copia digital para su presentación en la entrada del cine.<br>
                Cada entrada (boleto) incluye un código QR único que debe ser presentado al ingreso.
            </p>
            <p style="margin-top: 10px; font-size: 9px;">
                Generado: {{ now()->format('Y-m-d H:i:s') }} | Orden: {{ $order->uuid }}
            </p>
        </div>
    </div>
</body>
</html>
