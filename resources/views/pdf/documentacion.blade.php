<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.documentacion_estilos')
</head>
<body>

@include('pdf.partials.documentacion_encabezado')

<footer>
    <span class="numero">{{ $tipoDocumento->nombre }} · Impreso el {{ now()->format('d/m/Y') }}</span>
</footer>

<div class="titulo">{{ $tipoDocumento->nombre }}</div>

{{-- El contenido llega con las variables y los bloques ya resueltos y escapados. --}}
<div class="contenido">
    {!! $contenido !!}
</div>

</body>
</html>
