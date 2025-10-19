<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio KpiMetadataService
 *
 * Maneja la obtención de metadatos de modelos para la configuración de KPIs.
 * Proporciona información sobre campos disponibles y modelos permitidos.
 */
class KpiMetadataService
{
    /**
     * Obtiene los campos disponibles de un modelo específico.
     *
     * @param string $modelClass Clase del modelo a analizar
     * @return array Lista de campos con sus metadatos
     */
    public function getModelFields(string $modelClass): array
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $modelInstance = new $modelClass();
        $tableName = $modelInstance->getTable();

        if (!Schema::hasTable($tableName)) {
            return [];
        }

        $columns = Schema::getColumnListing($tableName);
        $fieldDetails = [];

        foreach ($columns as $columnName) {
            $allowedFields = config("kpis.available_kpi_models.{$modelClass}.fields");
            if ($allowedFields && !in_array($columnName, $allowedFields)) {
                continue;
            }

            $fieldDetails[] = [
                'name' => $columnName,
                'type' => Schema::getColumnType($tableName, $columnName),
                'display_name' => ucwords(str_replace('_', ' ', $columnName)),
            ];
        }

        return $fieldDetails;
    }

    /**
     * Obtiene la lista de modelos disponibles para KPIs.
     *
     * @return array Lista de modelos con sus metadatos
     */
    public function getAvailableKpiModels(): array
    {
        $models = [];
        foreach (config('kpis.available_kpi_models') as $modelClass => $config) {
            $models[] = [
                'class' => $modelClass,
                'display_name' => $config['display_name'] ?? class_basename($modelClass),
            ];
        }
        return $models;
    }

    /**
     * Valida si un modelo está permitido para KPIs.
     *
     * @param string $modelClass Clase del modelo a validar
     * @return bool True si el modelo está permitido
     */
    public function isModelAllowed(string $modelClass): bool
    {
        return array_key_exists($modelClass, config('kpis.available_kpi_models', []));
    }

    /**
     * Obtiene la configuración de un modelo específico.
     *
     * @param string $modelClass Clase del modelo
     * @return array|null Configuración del modelo o null si no existe
     */
    public function getModelConfig(string $modelClass): ?array
    {
        return config("kpis.available_kpi_models.{$modelClass}");
    }
}
