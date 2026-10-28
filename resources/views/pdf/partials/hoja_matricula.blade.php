{{--
    Hoja de matrícula para el paquete PDF de documentos de la matrícula.

    Replica en dompdf (tablas, sin flex/grid) la hoja que el frontend imprime
    desde MatriculaPrintModal.vue: si se agrega o quita un campo allá, hay que
    reflejarlo aquí.

    dompdf repite páginas en blanco cuando una celda de tabla contiene elementos
    de bloque y el documento tiene encabezado/pie fijos: dentro de las celdas solo
    se usan elementos en línea separados con <br>.

    Recibe: $matricula (con estudiante, curso, ciclo.sede, eps, lugarOrigen y
    matriculadoPor cargados) y $fotoBase64 (data URI o null).
--}}
@php
    $estudiante = $matricula->estudiante;
    $nombres    = $estudiante?->primer_nombre
        ? trim($estudiante->primer_nombre . ' ' . $estudiante->segundo_nombre)
        : ($estudiante?->name ?? '');
    $apellidos  = trim(($estudiante?->primer_apellido ?? '') . ' ' . ($estudiante?->segundo_apellido ?? ''));
    $fecha      = fn ($valor) => $valor ? \Carbon\Carbon::parse($valor)->format('d/m/Y') : '—';
    $texto      = fn ($valor) => filled($valor) ? $valor : '—';
    $lugar      = $matricula->lugarOrigen
        ? $matricula->lugarOrigen->nombre . ($matricula->lugarOrigen->provincia ? ' (' . $matricula->lugarOrigen->provincia . ')' : '')
        : null;
@endphp

<div class="hoja">
    <table class="hoja-cabecera">
        <tr>
            <td>
                <span class="hoja-titulo">Hoja de matrícula</span><br>
                <span class="hoja-sub">Sede: {{ $texto($matricula->ciclo?->sede?->nombre ?? $matricula->sede?->nombre) }}</span>
            </td>
            <td class="derecha">
                <span class="hoja-codigo">{{ $codigo }}</span><br>
                <span class="hoja-sub">Fecha: {{ $fecha($matricula->fecha_matricula) }}</span>
            </td>
        </tr>
    </table>

    <div class="hoja-seccion">Información personal</div>
    <table class="hoja-campos">
        <tr>
            <td><span>Nombres</span><br>{{ $texto($nombres) }}</td>
            <td><span>Apellidos</span><br>{{ $texto($apellidos) }}</td>
            <td><span>Tipo de identificación</span><br>{{ $texto($matricula->tipo_identificacion_texto) }}</td>
            <td><span>Documento</span><br>{{ $texto($estudiante?->documento) }}</td>
        </tr>
        <tr>
            <td><span>Expedición</span><br>{{ $texto(collect([$matricula->departamento_expedicion, $matricula->ciudad_expedicion])->filter()->join(', ')) }}</td>
            <td><span>Fecha de nacimiento</span><br>{{ $fecha($matricula->fecha_nacimiento) }}</td>
            <td><span>Lugar de origen</span><br>{{ $texto($lugar) }}</td>
            <td><span>Género</span><br>{{ $texto($matricula->genero_texto) }}</td>
        </tr>
        <tr>
            <td><span>Estado civil</span><br>{{ $texto($matricula->estado_civil_texto) }}</td>
            <td colspan="3"><span>Tipo de sangre</span><br>{{ $texto(trim(($matricula->grupo_sanguineo ?? '') . ' ' . ($matricula->rh_texto ?? ''))) }}</td>
        </tr>
    </table>

    <div class="hoja-seccion">Información de contacto</div>
    <table class="hoja-campos">
        <tr>
            <td colspan="2"><span>Dirección</span><br>{{ $texto($matricula->direccion) }}</td>
            <td><span>Teléfono fijo</span><br>{{ $texto($matricula->telefono) }}</td>
            <td><span>Celular</span><br>{{ $texto($matricula->celular) }}</td>
        </tr>
        <tr>
            <td colspan="4"><span>Correo electrónico</span><br>{{ $texto($estudiante?->email) }}</td>
        </tr>
    </table>

    <div class="hoja-seccion">Información académica y laboral</div>
    <table class="hoja-campos">
        <tr>
            <td><span>Nivel de estudios</span><br>{{ $texto($matricula->nivel_educacion_texto) }}</td>
            <td><span>Ocupación</span><br>{{ $texto($matricula->ocupacion) }}</td>
            <td><span>Régimen de salud</span><br>{{ $texto($matricula->regimen_salud_texto) }}</td>
            <td><span>EPS</span><br>{{ $texto($matricula->eps?->nombre) }}</td>
        </tr>
        <tr>
            <td colspan="3"><span>Empresa</span><br>{{ $texto($matricula->empresa) }}</td>
            <td><span>Estrato</span><br>{{ $texto($matricula->estrato) }}</td>
        </tr>
    </table>

    <div class="hoja-seccion">Información médica y otros</div>
    <table class="hoja-campos">
        <tr>
            <td><span>Enfermedades de atención prioritaria</span><br>{{ $matricula->enfermedad_prioritaria ? ($matricula->enfermedad_detalle ?: 'Sí, requiere atención') : 'Ninguna' }}</td>
            <td><span>Discapacidad</span><br>{{ $matricula->discapacidad ? ($matricula->tipo_discapacidad_texto ?: 'Sí') : 'Ninguna' }}</td>
            <td><span>Aprueba uso de imagen</span><br>{{ $matricula->aprueba_uso_imagen ? 'Sí' : 'No' }}</td>
            <td><span>Multiculturalidad</span><br>{{ $matricula->multiculturalidad ?: 'Ninguna' }}</td>
        </tr>
    </table>

    <div class="hoja-seccion">Información del curso</div>
    <table class="hoja-campos resaltado">
        <tr>
            <td colspan="2"><span>Técnico</span><br>{{ $texto($matricula->curso?->nombre) }}</td>
            <td><span>Ciclo</span><br>{{ $texto($matricula->ciclo?->nombre) }}</td>
            <td><span>Fecha de inicio</span><br>{{ $fecha($matricula->fecha_inicio) }}</td>
        </tr>
    </table>

    <div class="hoja-seccion">Overol / botas y contacto de emergencia</div>
    <table class="hoja-campos">
        <tr>
            <td><span>Talla overol</span><br>{{ $texto($matricula->talla_overol) }}</td>
            <td><span>Talla botas</span><br>{{ $texto($matricula->talla_botas) }}</td>
            <td><span>Contacto de emergencia</span><br>{{ $texto($matricula->nombre_contacto) }}</td>
            <td><span>Teléfono / correo</span><br>{{ $texto(collect([$matricula->telefono_contacto, $matricula->correo_contacto])->filter()->join(' · ')) }}</td>
        </tr>
    </table>

    @if (filled($matricula->observaciones))
        <div class="hoja-seccion">Observaciones</div>
        <table class="hoja-campos">
            <tr><td>{{ $matricula->observaciones }}</td></tr>
        </table>
    @endif

    <table class="hoja-firmas">
        <tr>
            <td class="caja">
                @if ($fotoBase64)
                    <img src="{{ $fotoBase64 }}" alt="">
                @endif
            </td>
            <td class="caja"></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="rotulo">Foto del estudiante</td>
            <td class="rotulo">Huella del estudiante</td>
            <td class="firma">
                <strong>Firma del estudiante</strong><br>
                <span>C.C. {{ $texto($estudiante?->documento) }}</span>
            </td>
            <td class="firma">
                <strong>Firma asistente {{ $instituto['nombre'] }}</strong><br>
                <span>{{ $matricula->matriculadoPor?->name ?: 'Autorizado' }}</span>
            </td>
        </tr>
    </table>
</div>
