<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago - {{ $recibo->numero_recibo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header-info {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h2 {
            font-size: 14px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        .info-value {
            flex: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th,
        table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-section {
            margin-top: 20px;
            border-top: 2px solid #333;
            padding-top: 10px;
        }
        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5px;
        }
        .total-label {
            font-weight: bold;
            width: 200px;
            text-align: right;
            padding-right: 10px;
        }
        .total-value {
            width: 150px;
            text-align: right;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RECIBO DE PAGO</h1>
        <div class="header-info">
            <div>
                <div class="info-row">
                    <span class="info-label">Número:</span>
                    <span class="info-value">{{ $recibo->numero_recibo }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha:</span>
                    <span class="info-value">{{ $recibo->fecha_recibo->format('d/m/Y') }}</span>
                </div>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">Estado:</span>
                    <span class="info-value">{{ $recibo->status_text }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Origen:</span>
                    <span class="info-value">{{ $recibo->origen_text }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h2>Información del Estudiante</h2>
        @if($recibo->estudiante)
            <div class="info-row">
                <span class="info-label">Nombre:</span>
                <span class="info-value">{{ $recibo->estudiante->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $recibo->estudiante->email }}</span>
            </div>
            @if($recibo->estudiante->documento)
                <div class="info-row">
                    <span class="info-label">Documento:</span>
                    <span class="info-value">{{ $recibo->estudiante->documento }}</span>
                </div>
            @endif
        @else
            <div class="info-row">
                <span class="info-value">No aplica</span>
            </div>
        @endif
    </div>

    <div class="info-section">
        <h2>Información de la Sede</h2>
        <div class="info-row">
            <span class="info-label">Sede:</span>
            <span class="info-value">{{ $recibo->sede->nombre }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Dirección:</span>
            <span class="info-value">{{ $recibo->sede->direccion }}</span>
        </div>
        @if($recibo->sede->poblacion)
            <div class="info-row">
                <span class="info-label">Ciudad:</span>
                <span class="info-value">{{ $recibo->sede->poblacion->nombre }}</span>
            </div>
        @endif
    </div>

    @if($recibo->conceptosPago->count() > 0)
        <div class="info-section">
            <h2>Conceptos de Pago</h2>
            <table>
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th>Producto</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-right">Unitario</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recibo->conceptosPago as $concepto)
                        <tr>
                            <td>{{ $concepto->nombre }}</td>
                            <td>{{ $concepto->pivot->producto ?? '-' }}</td>
                            <td class="text-center">{{ $concepto->pivot->cantidad }}</td>
                            <td class="text-right">${{ number_format($concepto->pivot->unitario, 2, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($concepto->pivot->subtotal, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($recibo->productos->count() > 0)
        <div class="info-section">
            <h2>Productos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-right">Precio Unitario</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recibo->productos as $producto)
                        <tr>
                            <td>{{ $producto->nombre }}</td>
                            <td class="text-center">{{ $producto->pivot->cantidad }}</td>
                            <td class="text-right">${{ number_format($producto->pivot->precio_unitario, 2, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($producto->pivot->subtotal, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($recibo->descuentos->count() > 0)
        <div class="info-section">
            <h2>Descuentos Aplicados</h2>
            <table>
                <thead>
                    <tr>
                        <th>Descuento</th>
                        <th class="text-right">Valor Original</th>
                        <th class="text-right">Valor Descuento</th>
                        <th class="text-right">Valor Final</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recibo->descuentos as $descuento)
                        <tr>
                            <td>{{ $descuento->nombre }}</td>
                            <td class="text-right">${{ number_format($descuento->pivot->valor_original, 2, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($descuento->pivot->valor_descuento, 2, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($descuento->pivot->valor_final, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="info-section">
        <h2>Medios de Pago</h2>
        <table>
            <thead>
                <tr>
                    <th>Medio de Pago</th>
                    <th>Referencia</th>
                    <th>Banco</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recibo->mediosPago as $medio)
                    <tr>
                        <td>{{ ucfirst($medio->medio_pago) }}</td>
                        <td>{{ $medio->referencia ?? '-' }}</td>
                        <td>{{ $medio->banco ?? '-' }}</td>
                        <td class="text-right">${{ number_format($medio->valor, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="total-section">
        <div class="total-row">
            <span class="total-label">Subtotal:</span>
            <span class="total-value">${{ number_format($recibo->valor_total, 2, ',', '.') }}</span>
        </div>
        @if($recibo->descuento_total > 0)
            <div class="total-row">
                <span class="total-label">Descuento Total:</span>
                <span class="total-value">${{ number_format($recibo->descuento_total, 2, ',', '.') }}</span>
            </div>
        @endif
        <div class="total-row" style="font-size: 16px; font-weight: bold; margin-top: 10px;">
            <span class="total-label">TOTAL A PAGAR:</span>
            <span class="total-value">${{ number_format($recibo->valor_total - $recibo->descuento_total, 2, ',', '.') }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Recibo generado el {{ now()->format('d/m/Y H:i:s') }}</p>
        <p>Cajero: {{ $recibo->cajero->name ?? 'N/A' }}</p>
        @if($recibo->cierre)
            <p>Cierre de caja: {{ $recibo->cierre }}</p>
        @endif
    </div>
</body>
</html>

