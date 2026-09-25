<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 120px 50px 90px 50px; }

        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; line-height: 1.5; margin: 0; }

        header { position: fixed; top: -95px; left: 0; right: 0; height: 80px;
                 border-bottom: 1px solid #ccc; padding-bottom: 6px; }
        header .instituto { font-size: 15px; font-weight: bold; }
        header .datos { font-size: 10px; color: #555; margin-top: 2px; }
        header .logo { float: right; max-height: 52px; }

        footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 45px;
                 border-top: 1px solid #ccc; padding-top: 6px; font-size: 9px; color: #777; }
        footer .numero { float: left; }
        footer .pagina { float: right; }
        footer .pagina:after { content: counter(page) " / " counter(pages); }

        .titulo { text-align: center; font-size: 15px; font-weight: bold;
                  text-transform: uppercase; margin-bottom: 18px; }

        .contenido table { width: 100%; border-collapse: collapse; }
        .contenido table td, .contenido table th { border: 1px solid #ddd; padding: 5px 6px; }
        .contenido img { max-width: 100%; }
        .contenido h1 { font-size: 16px; }
        .contenido h2 { font-size: 14px; }
        .contenido h3 { font-size: 13px; }
        .bloque-tabla { margin: 10px 0; font-size: 11px; }
        .bloque-tabla thead th { background: #f0f0f0; text-align: left; }
        .bloque-tabla tfoot th { background: #f7f7f7; text-align: left; }
        .bloque-vacio { color: #777; font-style: italic; }
    </style>
</head>
<body>

<header>
    @if ($logoBase64)
        <img src="{{ $logoBase64 }}" alt="" class="logo">
    @endif
    <div class="instituto">{{ $instituto['nombre'] }}</div>
    <div class="datos">
        @if (!empty($instituto['nit'])) NIT {{ $instituto['nit'] }} @endif
        @if (!empty($instituto['direccion'])) · {{ $instituto['direccion'] }} @endif
        @if (!empty($instituto['telefono'])) · Tel. {{ $instituto['telefono'] }} @endif
    </div>
</header>

<footer>
    <span class="numero">{{ $tipoDocumento->nombre }} · Impreso el {{ now()->format('d/m/Y') }}</span>
    <span class="pagina"></span>
</footer>

<div class="titulo">{{ $tipoDocumento->nombre }}</div>

{{-- El contenido llega con las variables y los bloques ya resueltos y escapados. --}}
<div class="contenido">
    {!! $contenido !!}
</div>

</body>
</html>
