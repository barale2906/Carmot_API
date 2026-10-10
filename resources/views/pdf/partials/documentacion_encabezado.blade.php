{{-- Encabezado institucional fijo, repetido en cada página. --}}
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
