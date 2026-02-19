<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - {{ $ticket->ticket_number }}</title>
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
            max-width: 800px;
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

        .header p {
            font-size: 12px;
            color: #666;
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

        .movie-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #e74c3c;
            margin-bottom: 20px;
        }

        .movie-info h3 {
            font-size: 16px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .movie-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 12px;
        }

        .detail-row {
            display: flex;
            flex-direction: column;
        }

        .detail-row label {
            font-weight: bold;
            color: #666;
            margin-bottom: 3px;
        }

        .detail-row value {
            color: #1a1a1a;
        }

        .seats-section {
            margin-bottom: 20px;
        }

        .seats-section h3 {
            font-size: 14px;
            color: #1a1a1a;
            margin-bottom: 10px;
            font-weight: bold;
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 5px;
        }

        .seats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .seats-table thead {
            background-color: #1a1a1a;
            color: white;
        }

        .seats-table th {
            padding: 10px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
        }

        .seats-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }

        .seats-table tbody tr:nth-child(even) {
            background-color: #f5f5f5;
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

        .qr-section {
            text-align: center;
            padding: 20px;
            border: 2px dashed #ccc;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #fafafa;
        }

        .qr-section h3 {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .qr-code {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            padding: 10px;
            background-color: white;
            border: 1px solid #ddd;
        }

        .qr-section p {
            font-size: 10px;
            color: #999;
            margin-top: 10px;
        }

        .verification-url {
            font-size: 9px;
            color: #666;
            word-break: break-all;
            margin-top: 10px;
            padding: 10px;
            background-color: white;
            border-radius: 3px;
        }

        .footer {
            text-align: center;
            border-top: 2px solid #ddd;
            padding-top: 15px;
            margin-top: 20px;
            font-size: 11px;
            color: #999;
        }

        .ticket-number {
            font-weight: bold;
            font-size: 14px;
            color: #e74c3c;
            margin-bottom: 10px;
        }

        .total-price {
            font-size: 16px;
            font-weight: bold;
            color: #1a1a1a;
            text-align: right;
            padding: 15px;
            background-color: #f0f0f0;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #664d03;
        }

        .status-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #842029;
        }

        .status-cancelled {
            background-color: #e2e3e5;
            color: #383d41;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>ENTRADA ELECTRÓNICA</h1>
            <p>Boleto de Acceso - {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <!-- Ticket Number -->
        <div class="ticket-number">
            Boleto: {{ $ticket->ticket_number }}
            <span class="status-badge status-{{ strtolower($ticket->status) }}">
                {{ strtoupper($ticket->status) }}
            </span>
        </div>

        <!-- Cinema & Room Info -->
        <div class="cinema-info">
            <h2>{{ $cinema->name }}</h2>
            <p>Sala: {{ $room->name }}</p>
        </div>

        <!-- Movie Info -->
        <div class="movie-info">
            <h3>{{ $movie->title }}</h3>
            <div class="movie-details">
                <div class="detail-row">
                    <label>Fecha:</label>
                    <value>{{ $screening->start_time->format('d/m/Y') }}</value>
                </div>
                <div class="detail-row">
                    <label>Hora:</label>
                    <value>{{ $screening->start_time->format('H:i') }}</value>
                </div>
                <div class="detail-row">
                    <label>Formato:</label>
                    <value>{{ $screening->format ?? 'Estándar' }}</value>
                </div>
                <div class="detail-row">
                    <label>Cantidad de Asientos:</label>
                    <value>{{ count($seats) }}</value>
                </div>
            </div>
        </div>

        <!-- Seats -->
        <div class="seats-section">
            <h3>ASIENTOS COMPRADOS</h3>
            <table class="seats-table">
                <thead>
                    <tr>
                        <th>Código de Asiento</th>
                        <th>Fila</th>
                        <th>Número</th>
                        <th>Precio</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($seats as $seat)
                    <tr>
                        <td><strong>{{ $seat['seat_code'] }}</strong></td>
                        <td>{{ $seat['row_number'] }}</td>
                        <td>{{ $seat['seat_number'] }}</td>
                        <td>${{ $seat['price'] }}</td>
                        <td>
                            <span class="status-badge status-completed">
                                VÁLIDO
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Total Price -->
        <div class="total-price">
            Precio Total: ${{ number_format($ticket->price, 2, '.', ',') }}
        </div>

        <!-- Customer Info -->
        <div class="customer-section">
            <h3>INFORMACIÓN DE COMPRADOR</h3>
            <div class="customer-details">
                <div class="detail-row">
                    <label>Nombre:</label>
                    <value>{{ $ticket->customer_name }}</value>
                </div>
                <div class="detail-row">
                    <label>Email:</label>
                    <value>{{ $ticket->customer_email }}</value>
                </div>
                <div class="detail-row">
                    <label>Teléfono:</label>
                    <value>{{ $ticket->customer_phone ?? 'N/A' }}</value>
                </div>
                <div class="detail-row">
                    <label>Comprado:</label>
                    <value>{{ $ticket->created_at->format('d/m/Y H:i') }}</value>
                </div>
            </div>
        </div>

        <!-- QR Code -->
        <div class="qr-section">
            <h3>CÓDIGO QR DE VALIDACIÓN</h3>
            <img src="{{ $qr_code }}" alt="QR Code" class="qr-code">
            <p>Présenta este código QR al ingreso del cine</p>
            <div class="verification-url">
                URL de Verificación: {{ $verify_url }}
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                Este boleto es válido únicamente para el comprador. Conserve este documento para su presentación.<br>
                No es un comprobante de pago. Un comprobante fiscal ha sido enviado a su correo electrónico.
            </p>
            <p style="margin-top: 10px; font-size: 9px;">
                Générá: {{ now()->format('Y-m-d H:i:s') }}
            </p>
        </div>
    </div>
</body>
</html>
