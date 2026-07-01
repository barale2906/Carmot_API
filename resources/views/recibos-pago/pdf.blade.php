<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Pago - {{ $recibo->numero_recibo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; padding: 1cm; }

        /* ── Encabezado institucional ─────────────────────────────────────── */
        .header-outer { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .logo-box {
            background-color: #213360;
            padding: 7px 12px;
            border-radius: 6px;
            width: 1%;
            white-space: nowrap;
        }
        .logo-img  { height: 40px; width: auto; display: block; }
        .logo-text { color: #ffffff; font-size: 17px; font-weight: bold; letter-spacing: 1px; }
        .logo-sub  { color: #b0bcdd; font-size: 8px; letter-spacing: 2px; }
        .org-cell  { padding-left: 12px; vertical-align: top; }
        .org-name  { font-size: 13px; font-weight: bold; color: #1a1a2e; }
        .org-nit   { font-size: 9px;  color: #666; margin-top: 2px; }
        .org-sede  { font-size: 9px;  color: #444; margin-top: 2px; }
        .badge-cell { text-align: right; vertical-align: top; white-space: nowrap; }
        .recibo-badge {
            background-color: #213360;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 5px;
            display: inline-block;
        }
        .recibo-date   { font-size: 9px;  color: #666;  margin-top: 4px; }
        .recibo-status { font-size: 9px;  color: #333;  margin-top: 2px; }

        /* ── Divisor y banner ─────────────────────────────────────────────── */
        .divider { border-top: 2px solid #213360; margin: 8px 0; }
        .banner {
            background-color: #213360;
            color: #ffffff;
            text-align: center;
            padding: 6px 0;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        /* ── Datos generales ──────────────────────────────────────────────── */
        .data-grid {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
            border-radius: 6px;
        }
        .data-grid td { padding: 5px 10px; }
        .data-label { font-size: 9px;  color: #94a3b8; display: block; }
        .data-value { font-size: 11px; color: #1e293b; font-weight: 500; }

        /* ── Tablas de conceptos y medios ─────────────────────────────────── */
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #213360;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .items-table th {
            font-size: 10px;
            font-weight: bold;
            color: #475569;
            border-bottom: 2px solid #213360;
            padding: 5px 4px;
            text-align: left;
        }
        .items-table th.right, .items-table td.right { text-align: right; }
        .items-table td {
            font-size: 10px;
            color: #334155;
            padding: 5px 4px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .concepto-nombre { font-weight: 500; }
        .concepto-obs    { font-size: 9px; color: #64748b; display: block; margin-top: 1px; }

        /* Badges de estado de cartera — colores iguales al frontend */
        .badge {
            font-size: 8px;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            display: inline-block;
            margin-top: 2px;
        }
        .badge-0 { background-color: #dbeafe; color: #1e40af; } /* Activa */
        .badge-1 { background-color: #fef3c7; color: #92400e; } /* Abonada */
        .badge-2 { background-color: #d1fae5; color: #065f46; } /* Cerrada */
        .badge-3 { background-color: #f1f5f9; color: #64748b; } /* Anulada */
        .badge-4 { background-color: #ede9fe; color: #5b21b6; } /* En Acuerdo */

        /* ── Totales ──────────────────────────────────────────────────────── */
        .totals-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .totals-table td { padding: 3px 4px; }
        .total-sep { border-top: 2px solid #213360; }
        .total-label { text-align: right; color: #475569; font-size: 11px; }
        .total-value { text-align: right; font-size: 11px; width: 110px; }
        .total-descuento { color: #059669; font-weight: 600; }
        .total-grand { font-size: 13px; font-weight: bold; color: #213360; }

        /* ── Firmas ───────────────────────────────────────────────────────── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 32px; margin-bottom: 14px; }
        .sig-table td { width: 50%; padding: 0 12px; vertical-align: top; }
        .sig-line { border-top: 1px solid #94a3b8; padding-top: 4px; }
        .sig-name { font-size: 10px; font-weight: 500; color: #334155; }
        .sig-sub  { font-size: 9px;  color: #94a3b8; margin-top: 1px; }

        /* ── Pie de página ────────────────────────────────────────────────── */
        .footer {
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 8px;
            margin-top: 6px;
        }
    </style>
</head>
<body>

    {{-- ── Encabezado institucional ───────────────────────────────────────── --}}
    <table class="header-outer">
        <tr>
            <td class="logo-box">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo">
                @else
                    <div class="logo-text">CARMOT</div>
                    <div class="logo-sub">CAPACITACIONES</div>
                @endif
            </td>
            <td class="org-cell">
                <div class="org-name">Centro de Capacitaciones CARMOT</div>
                <div class="org-nit">NIT: 1.048.849.874-0</div>
                @if($recibo->sede)
                    <div class="org-sede">Sede: {{ $recibo->sede->nombre }}</div>
                @endif
            </td>
            <td class="badge-cell">
                <div class="recibo-badge">{{ $recibo->numero_recibo }}</div>
                <div class="recibo-date">Fecha: {{ $recibo->fecha_recibo->format('d/m/Y') }}</div>
                <div class="recibo-status">Estado: <strong>{{ $recibo->status_text }}</strong></div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="banner">Recibo de pago</div>

    {{-- ── Datos generales ─────────────────────────────────────────────────── --}}
    <table class="data-grid">
        <tr>
            <td style="width:50%; border-right:1px solid #e2e8f0;">
                <span class="data-label">Estudiante</span>
                <span class="data-value">{{ $recibo->estudiante->name ?? 'ID ' . $recibo->estudiante_id }}</span>
            </td>
            <td style="width:50%;">
                <span class="data-label">Programa</span>
                <span class="data-value">{{ $recibo->matricula?->curso?->nombre ?? '—' }}</span>
            </td>
        </tr>
        <tr style="border-top:1px solid #e2e8f0;">
            <td style="border-right:1px solid #e2e8f0;">
                <span class="data-label">Responsable</span>
                <span class="data-value">{{ $recibo->cajero->name ?? 'ID ' . $recibo->cajero_id }}</span>
            </td>
            <td>
                <span class="data-label">Fecha de transacción</span>
                <span class="data-value">
                    {{ ($recibo->fecha_transaccion ?? $recibo->fecha_recibo)?->format('d/m/Y') }}
                </span>
            </td>
        </tr>
    </table>

    {{-- ── Conceptos de pago ───────────────────────────────────────────────── --}}
    <div class="section-title">Conceptos de pago</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="right" style="width:45px;">Cant.</th>
                <th class="right" style="width:90px;">Valor unitario</th>
                <th class="right" style="width:90px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recibo->conceptosPago as $cp)
                @php
                    $idRel   = $cp->pivot->id_relacional;
                    $cartera = $idRel ? ($carteras[$idRel] ?? null) : null;
                @endphp
                <tr>
                    <td>
                        <span class="concepto-nombre">{{ $cp->nombre }}</span>
                        @if($cp->pivot->observaciones)
                            <span class="concepto-obs">— {{ $cp->pivot->observaciones }}</span>
                        @endif
                        @if($cartera)
                            <span class="badge badge-{{ $cartera->status }}">{{ $cartera->status_text }}</span>
                        @endif
                    </td>
                    <td class="right">{{ $cp->pivot->cantidad }}</td>
                    <td class="right">$ {{ number_format($cp->pivot->unitario, 0, ',', '.') }}</td>
                    <td class="right">$ {{ number_format($cp->pivot->subtotal, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center; color:#94a3b8; padding:10px;">
                        Sin conceptos registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Descuento y total --}}
    <table class="totals-table">
        @if($recibo->descuento_total > 0)
            <tr>
                <td class="total-label total-descuento">Descuento aplicado:</td>
                <td class="total-value total-descuento">− $ {{ number_format($recibo->descuento_total, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr class="total-sep">
            <td class="total-label total-grand">Total pagado:</td>
            <td class="total-value total-grand">$ {{ number_format($recibo->valor_total, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- ── Medios de pago ──────────────────────────────────────────────────── --}}
    <div class="section-title">Medios de pago</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Medio</th>
                <th>Referencia</th>
                <th class="right" style="width:90px;">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recibo->mediosPago as $mp)
                <tr>
                    <td>{{ ucwords(str_replace('_', ' ', $mp->medio_pago)) }}</td>
                    <td>{{ $mp->referencia ?? '—' }}</td>
                    <td class="right">$ {{ number_format($mp->valor, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align:center; color:#94a3b8; padding:10px;">
                        Sin medios de pago registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Firmas ───────────────────────────────────────────────────────────── --}}
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-line">
                    <div class="sig-name">Firma del estudiante</div>
                    <div class="sig-sub">{{ $recibo->estudiante->name ?? '' }}</div>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <div class="sig-name">Responsable de caja</div>
                    <div class="sig-sub">{{ $recibo->cajero->name ?? '' }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Pie de página ───────────────────────────────────────────────────── --}}
    <div class="footer">
        Centro de Capacitaciones CARMOT — NIT: 1.048.849.874-0<br>
        Generado el: {{ now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>
