{{-- Tabla genérica de los bloques de documento: columnas y filas ya formateadas. --}}
<table class="bloque-tabla">
    <thead>
        <tr>
            @foreach ($columnas as $columna)
                <th>{{ $columna['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                @foreach ($columnas as $clave => $columna)
                    <td>{{ $fila[$clave] ?? '' }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columnas) }}" class="bloque-vacio">Sin registros.</td>
            </tr>
        @endforelse
    </tbody>
    @if ($resumen)
        <tfoot>
            <tr>
                @foreach ($columnas as $clave => $columna)
                    @if ($loop->first)
                        <th>{{ $resumen['label'] }}</th>
                    @else
                        <th>{{ $resumen['valores'][$clave] ?? '' }}</th>
                    @endif
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
