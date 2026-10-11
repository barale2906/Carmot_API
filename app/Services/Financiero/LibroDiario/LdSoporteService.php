<?php

namespace App\Services\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdSoporte;
use App\Models\User;
use App\Services\ArchivoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio LdSoporteService
 *
 * Guarda y elimina los archivos que soportan los registros del libro diario.
 * Los archivos van al disco privado, todos en la carpeta `libro-diario` con el
 * nombre {idUsuario}_{fecha}_{hora} (ArchivoService).
 */
class LdSoporteService
{
    public function __construct(private readonly ArchivoService $archivos) {}

    /**
     * Guarda el archivo y lo asocia al registro.
     *
     * @param  \App\Models\Financiero\LibroDiario\LdMovimiento|\App\Models\Financiero\LibroDiario\LdConsignacion  $registro
     *
     * @throws \RuntimeException Si el archivo no se pudo escribir.
     */
    public function subir(Model $registro, UploadedFile $archivo, string $tipo, User $usuario): LdSoporte
    {
        // Los datos del archivo se leen antes de moverlo: después el temporal ya no existe
        $metadatos = [
            'tipo' => $tipo,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'usuario_id' => $usuario->id,
        ];

        $ruta = $this->archivos->guardar($archivo, LdSoporte::DISCO, ArchivoService::CARPETA_LIBRO_DIARIO, $usuario->id);

        return $registro->soportes()->create($metadatos + ['archivo_path' => $ruta]);
    }

    /**
     * Elimina el soporte y su archivo.
     */
    public function eliminar(LdSoporte $soporte): void
    {
        Storage::disk(LdSoporte::DISCO)->delete($soporte->archivo_path);
        $soporte->delete();
    }
}
