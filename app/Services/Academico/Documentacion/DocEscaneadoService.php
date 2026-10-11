<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Models\User;
use App\Services\ArchivoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio DocEscaneadoService
 *
 * Guarda los documentos firmados y escaneados de una matrícula, uno por
 * documento: el escaneado de un tipo (contrato, pagaré) o, sin tipo, el de la
 * hoja de matrícula u otro soporte identificado por su descripción.
 *
 * Subir de nuevo el mismo documento lo reemplaza: el anterior pasa a la papelera
 * de la bitácora (soft delete) con su archivo intacto, por si hay que
 * restaurarlo; el archivo solo se borra al eliminarlo definitivamente.
 *
 * Los archivos van al disco privado, todos en la carpeta `documentos` con el
 * nombre {idUsuario}_{fecha}_{hora} (ArchivoService), mientras no esté lista la
 * integración con Google Drive.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocEscaneadoService
{
    public function __construct(private readonly ArchivoService $archivos)
    {
    }

    /**
     * Guarda el escaneado de un documento de la matrícula y reemplaza el anterior.
     *
     * @param Matricula             $matricula
     * @param UploadedFile          $archivo
     * @param DocTipoDocumento|null $tipoDocumento Tipo del documento; null si se identifica por descripción.
     * @param string|null           $descripcion   Qué documento es, cuando no tiene tipo.
     * @param User|null             $usuario
     * @return DocDocumento
     */
    public function subir(
        Matricula $matricula,
        UploadedFile $archivo,
        ?DocTipoDocumento $tipoDocumento,
        ?string $descripcion,
        ?User $usuario
    ): DocDocumento {
        // Los datos del archivo se leen antes de moverlo: después el temporal ya no existe
        $metadatos = [
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type'       => $archivo->getMimeType(),
            'tamano_bytes'    => $archivo->getSize(),
        ];
        $ruta = $this->archivos->guardar($archivo, DocDocumento::DISCO_ARCHIVOS, ArchivoService::CARPETA_DOCUMENTOS, $usuario?->id);

        return DB::transaction(function () use ($matricula, $metadatos, $tipoDocumento, $descripcion, $usuario, $ruta) {
            DocDocumento::subidos()
                ->where('entidad_type', Matricula::class)
                ->where('entidad_id', $matricula->id)
                ->when(
                    $tipoDocumento,
                    fn ($q) => $q->where('tipo_documento_id', $tipoDocumento->id),
                    fn ($q) => $q->whereNull('tipo_documento_id')->where('descripcion', $descripcion)
                )
                ->get()
                ->each->delete();

            return DocDocumento::create([
                'tipo_documento_id' => $tipoDocumento?->id,
                'entidad_type'      => Matricula::class,
                'entidad_id'        => $matricula->id,
                'origen'            => DocDocumento::ORIGEN_SUBIDO,
                'descripcion'       => $tipoDocumento ? null : $descripcion,
                'nombre_original'   => $metadatos['nombre_original'],
                'mime_type'         => $metadatos['mime_type'],
                'tamano_bytes'      => $metadatos['tamano_bytes'],
                'archivo_path'      => $ruta,
                'generado_por'      => $usuario?->id,
            ]);
        });
    }

    /**
     * Borra del disco el archivo de un escaneado, si lo tiene.
     *
     * @param DocDocumento $documento
     * @return void
     */
    public function eliminarArchivo(DocDocumento $documento): void
    {
        if ($documento->archivo_path) {
            Storage::disk(DocDocumento::DISCO_ARCHIVOS)->delete($documento->archivo_path);
        }
    }
}
