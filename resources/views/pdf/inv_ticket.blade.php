<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 14px; }
        .header h2 { font-size: 16px; margin-bottom: 2px; }
        .header p { font-size: 11px; color: #555; }
        .section { margin-bottom: 14px; }
        .section-title { font-weight: bold; font-size: 11px; text-transform: uppercase;
                         border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 6px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 3px; font-size: 11px; }
        .row .label { color: #555; }
        .row .value { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        table th { background: #f0f0f0; text-align: left; padding: 5px 6px; border: 1px solid #ddd; }
        table td { padding: 5px 6px; border: 1px solid #ddd; }
        .text-right { text-align: right; }
        .totals { margin-top: 10px; }
        .totals .row { font-size: 12px; }
        .totals .total-final { font-size: 14px; font-weight: bold; border-top: 2px solid #333; padding-top: 6px; margin-top: 6px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .badge-activo     { background: #d1ecf1; color: #0c5460; }
        .badge-pagado     { background: #d4edda; color: #155724; }
        .badge-entregando { background: #fff3cd; color: #856404; }
        .badge-entregado  { background: #cce5ff; color: #004085; }
        .badge-cancelado  { background: #f8d7da; color: #721c24; }
        .footer { text-align: center; font-size: 10px; color: #888; border-top: 1px solid #ccc; padding-top: 8px; margin-top: 20px; }
    </style>
</head>
<body>

{{-- Encabezado --}}
<div class="header">
    <h2>{{ $pedido->sede?->nombre ?? 'Ticket de Venta' }}</h2>
    <p>Ticket de Pedido de Inventario</p>
    <p>{{ now()->format('d/m/Y H:i') }}</p>
</div>

{{-- Datos del pedido --}}
<div class="section">
    <div class="section-title">Datos del Pedido</div>
    <div class="row">
        <span class="label">N.° Pedido:</span>
        <span class="value">#{{ $pedido->id }}</span>
    </div>
    <div class="row">
        <span class="label">Fecha:</span>
        <span class="value">{{ $pedido->created_at->format('d/m/Y H:i') }}</span>
    </div>
    <div class="row">
        <span class="label">Estado:</span>
        <span class="value badge badge-{{ $pedido->status }}">{{ strtoupper($pedido->status) }}</span>
    </div>
    <div class="row">
        <span class="label">Almacén:</span>
        <span class="value">{{ $pedido->almacen?->nombre ?? '—' }}</span>
    </div>
</div>

{{-- Datos del estudiante --}}
<div class="section">
    <div class="section-title">Cliente</div>
    <div class="row">
        <span class="label">Nombre:</span>
        <span class="value">{{ $pedido->estudiante?->name ?? '—' }}</span>
    </div>
    @if($pedido->estudiante?->email)
    <div class="row">
        <span class="label">Email:</span>
        <span class="value">{{ $pedido->estudiante->email }}</span>
    </div>
    @endif
</div>

{{-- Productos --}}
<div class="section">
    <div class="section-title">Productos</div>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="text-right">Cant.</th>
                <th class="text-right">P. Lista</th>
                <th class="text-right">Descuento</th>
                <th class="text-right">P. Final</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->items as $item)
            <tr>
                <td>{{ $item->producto?->nombre ?? "ID #{$item->producto_id}" }}</td>
                <td class="text-right">{{ $item->cantidad }}</td>
                <td class="text-right">${{ number_format($item->precio_lista ?? $item->precio_unitario, 0, ',', '.') }}</td>
                <td class="text-right">
                    @if(($item->descuento_unitario ?? 0) > 0)
                        ${{ number_format($item->descuento_unitario, 0, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-right">${{ number_format($item->precio_unitario, 0, ',', '.') }}</td>
                <td class="text-right">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Totales --}}
<div class="section totals">
    <div class="section-title">Resumen</div>
    <div class="row">
        <span class="label">Valor Total:</span>
        <span class="value">${{ number_format($pedido->valor_total, 0, ',', '.') }}</span>
    </div>
    <div class="row">
        <span class="label">Abonado:</span>
        <span class="value">${{ number_format($pedido->abono_acumulado, 0, ',', '.') }}</span>
    </div>
    @if((float)$pedido->saldo > 0)
    <div class="row total-final">
        <span class="label">Saldo Pendiente:</span>
        <span class="value">${{ number_format($pedido->saldo, 0, ',', '.') }}</span>
    </div>
    @else
    <div class="row total-final">
        <span class="label">PAGADO TOTAL:</span>
        <span class="value">${{ number_format($pedido->valor_total, 0, ',', '.') }}</span>
    </div>
    @endif
</div>

{{-- Recibos vinculados --}}
@if($pedido->reciboLinks?->count())
<div class="section">
    <div class="section-title">Recibos de Pago</div>
    @foreach($pedido->reciboLinks as $link)
    <div class="row">
        <span class="label">Recibo #{{ $link->recibo_pago_id }}</span>
        <span class="value">${{ number_format($link->monto_abonado, 0, ',', '.') }} — {{ $link->created_at->format('d/m/Y') }}</span>
    </div>
    @endforeach
</div>
@endif

{{-- Observaciones --}}
@if($pedido->observaciones)
<div class="section">
    <div class="section-title">Observaciones</div>
    <p style="font-size:11px;">{{ $pedido->observaciones }}</p>
</div>
@endif

{{-- Cajero --}}
<div class="footer">
    Atendido por: {{ $pedido->cajero?->name ?? '—' }}
    &nbsp;|&nbsp;
    Sede: {{ $pedido->sede?->nombre ?? '—' }}
</div>

</body>
</html>
