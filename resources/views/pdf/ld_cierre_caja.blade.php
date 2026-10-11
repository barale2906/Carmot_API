@php
    $r = $turno->resumen ?? [];
    $cop = fn ($v) => '$ ' . number_format((float) $v, 0, ',', '.');
    $medios = \App\Models\Financiero\ReciboPago\ReciboPagoMedioPago::MEDIOS;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; padding: 28px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 14px; }
        .header h2 { font-size: 16px; margin-bottom: 2px; }
        .header p { font-size: 11px; color: #555; }
        .section { margin-bottom: 14px; }
        .section-title { font-weight: bold; font-size: 11px; text-transform: uppercase;
                         border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        table th { background: #f0f0f0; text-align: left; padding: 5px 6px; border: 1px solid #ddd; }
        table td { padding: 5px 6px; border: 1px solid #ddd; }
        .text-right { text-align: right; }
        .total td { font-weight: bold; background: #fafafa; }
        .alerta { color: #721c24; font-weight: bold; }
        .firmas { margin-top: 50px; width: 100%; }
        .firmas td { border: none; text-align: center; padding-top: 40px; }
        .firmas span { display: inline-block; border-top: 1px solid #333; width: 70%; padding-top: 4px; }
        .footer { text-align: center; font-size: 10px; color: #888; border-top: 1px solid #ccc; padding-top: 8px; margin-top: 20px; }
    </style>
</head>
<body>

<div class="header">
    <h2>{{ $turno->sede?->nombre }}</h2>
    <p>
        {{ $turno->estaAbierto() ? 'Arqueo de caja (turno abierto)' : 'Cierre de caja' }} N.° {{ $turno->id }}
        @if ($turno->estaPorAprobar()) — PENDIENTE DE APROBACIÓN @endif
    </p>
    <p>Generado: {{ now()->format('d/m/Y H:i') }}</p>
</div>

<div class="section">
    <div class="section-title">Turno</div>
    <table>
        <tr><th>Cajero</th><td>{{ $turno->cajero?->name }}</td><th>Fecha</th><td>{{ $turno->fecha?->format('d/m/Y') }}</td></tr>
        <tr><th>Apertura</th><td>{{ $turno->abierto_at?->format('d/m/Y H:i') }}</td><th>Cierre</th><td>{{ $turno->cerrado_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        <tr><th>Cerrado por</th><td>{{ $turno->cerradoPor?->name ?? '—' }}</td><th>Ajustado</th><td>{{ $turno->ajustado ? 'Sí (anulaciones posteriores al cierre)' : 'No' }}</td></tr>
        <tr><th>Aprobado por</th><td>{{ $turno->aprobadoPor?->name ?? '—' }}</td><th>Aprobación</th><td>{{ $turno->aprobado_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Ingresos y egresos</div>
    <table>
        <tr><td>Recibos académicos</td><td class="text-right">{{ $cop($r['ingresos']['academico'] ?? 0) }}</td></tr>
        <tr><td>Recibos de inventario</td><td class="text-right">{{ $cop($r['ingresos']['inventario'] ?? 0) }}</td></tr>
        <tr><td>Otros ingresos</td><td class="text-right">{{ $cop($r['ingresos']['otros'] ?? 0) }}</td></tr>
        <tr class="total"><td>Total ingresos</td><td class="text-right">{{ $cop($r['ingresos']['total'] ?? 0) }}</td></tr>
        <tr><td>Egresos</td><td class="text-right">{{ $cop($r['egresos'] ?? 0) }}</td></tr>
        <tr><td>Consignaciones</td><td class="text-right">{{ $cop($r['consignaciones'] ?? 0) }}</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Por medio de pago</div>
    <table>
        <thead>
            <tr>
                <th>Medio</th>
                <th class="text-right">Académico</th>
                <th class="text-right">Inventario</th>
                <th class="text-right">Otros ingresos</th>
                <th class="text-right">Total ingresos</th>
                <th class="text-right">Egresos</th>
            </tr>
        </thead>
        <tbody>
        @foreach (($r['por_medio_pago'] ?? []) as $medio => $valores)
            <tr>
                <td>{{ $medios[$medio] ?? $medio }}</td>
                <td class="text-right">{{ $cop($valores['academico'] ?? 0) }}</td>
                <td class="text-right">{{ $cop($valores['inventario'] ?? 0) }}</td>
                <td class="text-right">{{ $cop($valores['otros'] ?? 0) }}</td>
                <td class="text-right">{{ $cop($valores['ingresos'] ?? 0) }}</td>
                <td class="text-right">{{ $cop($valores['egresos'] ?? 0) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Arqueo de efectivo</div>
    <table>
        <tr><td>Base inicial</td><td class="text-right">{{ $cop($turno->base_inicial) }}</td></tr>
        @if ($verArqueo)
            <tr><td>Efectivo esperado</td><td class="text-right">{{ $cop($r['efectivo_esperado'] ?? 0) }}</td></tr>
        @endif
        <tr><td>Efectivo contado</td><td class="text-right">{{ $turno->efectivo_contado !== null ? $cop($turno->efectivo_contado) : '—' }}</td></tr>
        @if ($verArqueo)
            <tr class="total">
                <td>Diferencia{{ abs((float) $turno->diferencia) >= 0.01 ? ' — DESCUADRE' : '' }}</td>
                <td class="text-right {{ (float) $turno->diferencia !== 0.0 ? 'alerta' : '' }}">{{ $turno->diferencia !== null ? $cop($turno->diferencia) : '—' }}</td>
            </tr>
        @endif
    </table>
    @if (($r['transferencias_pendientes']['cantidad'] ?? 0) > 0)
        <p style="margin-top:6px;font-size:10px;color:#555;">
            Transferencias por aprobar (no incluidas): {{ $r['transferencias_pendientes']['cantidad'] }}
            por {{ $cop($r['transferencias_pendientes']['valor']) }}.
        </p>
    @endif
    @if ($turno->observaciones_cierre)
        <p style="margin-top:6px;"><strong>Observaciones:</strong> {{ $turno->observaciones_cierre }}</p>
    @endif
</div>

<table class="firmas">
    <tr>
        <td><span>Cajero</span></td>
        <td><span>Aprueba</span></td>
    </tr>
</table>

<div class="footer">
    Recibos: {{ $r['cantidades']['recibos'] ?? 0 }} · Movimientos: {{ $r['cantidades']['movimientos'] ?? 0 }} · Consignaciones: {{ $r['cantidades']['consignaciones'] ?? 0 }}
</div>

</body>
</html>
