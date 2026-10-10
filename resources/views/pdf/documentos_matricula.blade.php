<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.documentacion_estilos')
    <style>
        .salto { page-break-before: always; }

        .hoja { line-height: 1.2; }
        .hoja table { width: 100%; border-collapse: collapse; }
        .hoja-cabecera td { vertical-align: top; padding: 0 0 6px 0; }
        .hoja-cabecera .derecha { text-align: right; }
        .hoja-titulo { font-size: 15px; font-weight: bold; text-transform: uppercase; color: #213360; }
        .hoja-codigo { font-size: 13px; font-weight: bold; color: #213360; }
        .hoja-sub { font-size: 10px; color: #555; }

        .hoja-seccion { margin-top: 6px; padding: 2px 0; font-size: 10px; font-weight: bold;
                        text-transform: uppercase; color: #213360; border-bottom: 1px solid #213360; }
        .hoja-campos td { width: 25%; vertical-align: top; padding: 2px 4px; font-size: 10px;
                          border-bottom: 1px solid #eee; }
        .hoja-campos td span { font-size: 8px; color: #777; }
        .hoja-campos.resaltado td { background: #eef3fb; }

        .hoja-firmas { margin-top: 10px; page-break-inside: avoid; }
        .hoja-firmas td { vertical-align: bottom; padding: 0 6px; }
        .hoja-firmas .caja { width: 72px; height: 94px; border: 1px solid #ccc; padding: 0; }
        .hoja-firmas .caja img { width: 72px; height: 94px; }
        .hoja-firmas .rotulo { font-size: 9px; padding-top: 2px; }
        .hoja-firmas .firma { width: 32%; border-top: 1px solid #555; padding-top: 3px; font-size: 10px; vertical-align: top; }
        .hoja-firmas .firma span { font-size: 9px; color: #777; }
    </style>
</head>
<body>

@include('pdf.partials.documentacion_encabezado')

<footer>
    <span class="numero">Matrícula {{ $codigo }} · Impreso el {{ now()->format('d/m/Y') }}</span>
    <span class="pagina"></span>
</footer>

@include('pdf.partials.hoja_matricula')

@foreach ($documentos as $documento)
    <div class="salto">
        <div class="titulo">{{ $documento['tipo_documento'] }}</div>

        {{-- El contenido llega con las variables y los bloques ya resueltos y escapados. --}}
        <div class="contenido">
            {!! $documento['contenido'] !!}
        </div>
    </div>
@endforeach

</body>
</html>
