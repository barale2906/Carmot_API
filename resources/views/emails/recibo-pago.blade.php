<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            padding: 20px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recibo de Pago</h1>
            <p><strong>Número:</strong> {{ $recibo->numero_recibo }}</p>
            <p><strong>Fecha:</strong> {{ $recibo->fecha_recibo->format('d/m/Y') }}</p>
        </div>

        <div class="content">
            <p>Estimado/a {{ $recibo->estudiante->name ?? 'Cliente' }},</p>

            <p>Le informamos que se ha generado su recibo de pago con el número <strong>{{ $recibo->numero_recibo }}</strong>.</p>

            <p><strong>Detalles del pago:</strong></p>
            <ul>
                <li><strong>Valor Total:</strong> ${{ number_format($recibo->valor_total, 2, ',', '.') }}</li>
                @if($recibo->descuento_total > 0)
                    <li><strong>Descuento:</strong> ${{ number_format($recibo->descuento_total, 2, ',', '.') }}</li>
                @endif
                <li><strong>Total a Pagar:</strong> ${{ number_format($recibo->valor_total - $recibo->descuento_total, 2, ',', '.') }}</li>
            </ul>

            <p>Puede encontrar el recibo completo adjunto en formato PDF.</p>

            <p>Si tiene alguna pregunta o necesita asistencia, no dude en contactarnos.</p>
        </div>

        <div class="footer">
            <p>Este es un correo automático, por favor no responda a este mensaje.</p>
            <p>&copy; {{ date('Y') }} - Todos los derechos reservados</p>
        </div>
    </div>
</body>
</html>

