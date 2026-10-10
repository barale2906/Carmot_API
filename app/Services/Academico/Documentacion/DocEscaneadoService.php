<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
 * Los archivos van al disco privado (storage/app), fuera del alcance público,
 * mientras no esté lista la integración con Google Drive.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocEscaneadoService
{
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
        $ruta = $this->guardarArchivo($matricula, $archivo);

        return DB::transaction(function () use ($matricula, $archivo, $tipoDocumento, $descripcion, $usuario, $ruta) {
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
                'nombre_original'   => $archivo->getClientOriginalName(),
                'mime_type'         => $archivo->getMimeType(),
                'tamano_bytes'      => $archivo->getSize(),
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

    /**
     * Guarda el archivo en la carpeta privada de la matrícula y devuelve su ruta.
     *
     * @param Matricula    $matricula
     * @param UploadedFile $archivo
     * @return string Ruta relativa al disco privado.
     *
     * @throws RuntimeException Si el archivo no se pudo escribir.
     */
    private function guardarArchivo(Matricula $matricula, UploadedFile $archivo): string
    {
        $nombre = now()->format('YmdHis') . '_' . uniqid() . '.' . strtolower($archivo->getClientOriginalExtension());

        $ruta = $archivo->storeAs('documentos/matriculas/' . $matricula->id, $nombre, DocDocumento::DISCO_ARCHIVOS);

        if (!$ruta) {
            throw new RuntimeException('No se pudo guardar el archivo escaneado.');
        }

        return $ruta;
    }
}
