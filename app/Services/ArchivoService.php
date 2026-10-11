<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Servicio ArchivoService
 *
 * Guarda los archivos que suben los usuarios con una convención única para todo
 * el sistema: una sola carpeta base por tipo de documento (sin subcarpetas por
 * fecha ni por registro) y el nombre `{idUsuario}_{AAAAMMDD}_{HHMMSS}.{ext}`.
 * Si el mismo usuario sube dos archivos en el mismo segundo, se agrega `_2`, `_3`…
 *
 * Se usa `move()` de PHP en lugar de Flysystem: en Docker `putFileAs()` falla
 * silenciosamente por el chmod que aplica Flysystem.
 */
class ArchivoService
{
    /** Carpetas base por tipo de documento. */
    public const CARPETA_LIBRO_DIARIO = 'libro-diario';

    public const CARPETA_DOCUMENTOS = 'documentos';

    public const CARPETA_COMPROBANTES = 'recibos_transferencia';

    public const CARPETA_BIBLIOTECA = 'biblioteca';

    public const CARPETA_FOTOS_MATRICULA = 'fotos_matricula';

    public const CARPETA_PRODUCTOS = 'productos';

    /** Tamaño máximo, en kilobytes, de documentos e imágenes que suben los usuarios (10 MB). */
    public const MAX_KB = 10240;

    /**
     * Guarda el archivo en la carpeta base del disco y devuelve su ruta relativa al disco.
     *
     * @param  string  $disco  Disco de Laravel ('local' privado o 'public')
     * @param  string  $carpeta  Carpeta base (una de las constantes CARPETA_*)
     * @param  int|null  $usuarioId  Usuario que sube el archivo; por defecto el autenticado
     *
     * @throws RuntimeException Si el archivo no se pudo escribir.
     */
    public function guardar(UploadedFile $archivo, string $disco, string $carpeta, ?int $usuarioId = null): string
    {
        $directorio = Storage::disk($disco)->path($carpeta);
        if (! is_dir($directorio) && ! mkdir($directorio, 0755, true) && ! is_dir($directorio)) {
            throw new RuntimeException("No se pudo crear la carpeta de archivos {$carpeta}.");
        }

        $nombre = $this->nombreDisponible($directorio, $usuarioId ?? (int) auth()->id(), $this->extension($archivo));

        try {
            $archivo->move($directorio, $nombre);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo guardar el archivo: '.$e->getMessage(), 0, $e);
        }

        return "{$carpeta}/{$nombre}";
    }

    /**
     * Nombre `{usuario}_{fecha}_{hora}.{ext}` que no exista aún en la carpeta.
     */
    private function nombreDisponible(string $directorio, int $usuarioId, string $extension): string
    {
        $base = $usuarioId.'_'.now()->format('Ymd_His');
        $sufijo = $extension !== '' ? ".{$extension}" : '';

        $nombre = $base.$sufijo;
        for ($n = 2; file_exists("{$directorio}/{$nombre}"); $n++) {
            $nombre = "{$base}_{$n}{$sufijo}";
        }

        return $nombre;
    }

    /**
     * Extensión del archivo en minúsculas (la original o la deducida del contenido).
     */
    private function extension(UploadedFile $archivo): string
    {
        return strtolower($archivo->getClientOriginalExtension() ?: (string) $archivo->extension());
    }
}
