<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
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
     * @param int $modelId ID del modelo a analizar
     * @return array Lista de campos con sus metadatos
     */
    public function getModelFields(int $modelId): array
    {
        $modelConfig = $this->getModelConfigById($modelId);
        if (!$modelConfig) {
            return [];
        }

        $modelClass = $modelConfig['class'];

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
        $configuredFields = $modelConfig['fields'] ?? [];

        foreach ($columns as $columnName) {
            // Verificar si el campo está permitido en la configuración
            if (!array_key_exists($columnName, $configuredFields)) {
                continue;
            }

            $fieldDetails[] = [
                'name' => $columnName,
                'type' => Schema::getColumnType($tableName, $columnName),
                'display_name' => $configuredFields[$columnName], // Usar el alias del config
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
        try {
            Log::info('=== INICIO getAvailableKpiModels ===');

            // Obtener configuración desde el archivo config/kpis.php
            $config = config('kpis.available_kpi_models', []);

            Log::info('Using config from kpis.php:', $config);

            $models = [];
            foreach ($config as $modelId => $modelConfig) {
                Log::info("Processing modelId: $modelId", ['modelConfig' => $modelConfig]);

                if (!is_array($modelConfig)) {
                    Log::error("ModelConfig for ID $modelId is not an array:", gettype($modelConfig));
                    continue;
                }

                if (!isset($modelConfig['class'])) {
                    Log::error("ModelConfig for ID $modelId missing 'class' key", $modelConfig);
                    continue;
                }

                $model = [
                    'id' => $modelId,
                    'class' => $modelConfig['class'],
                    'display_name' => $modelConfig['display_name'] ?? class_basename($modelConfig['class']),
                ];

                Log::info("Created model for ID $modelId:", $model);
                $models[] = $model;
            }

            Log::info('Final models array:', $models);
            return $models;

        } catch (\Exception $e) {
            Log::error('Exception in getAvailableKpiModels: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Valida si un modelo está permitido para KPIs.
     *
     * @param int $modelId ID del modelo a validar
     * @return bool True si el modelo está permitido
     */
    public function isModelAllowed(int $modelId): bool
    {
        return array_key_exists($modelId, config('kpis.available_kpi_models', []));
    }


    /**
     * Obtiene la configuración de un modelo específico por clase.
     *
     * @param string $modelClass Clase del modelo
     * @return array|null Configuración del modelo o null si no existe
     */
    public function getModelConfig(string $modelClass): ?array
    {
        $models = config('kpis.available_kpi_models', []);
        foreach ($models as $config) {
            if ($config['class'] === $modelClass) {
                return $config;
            }
        }
        return null;
    }

    /**
     * Obtiene los campos permitidos para un modelo por su clase.
     * Método de compatibilidad para el sistema existente.
     *
     * @param string $modelClass Clase del modelo
     * @return array Lista de campos permitidos
     */
    public function getModelFieldsByClass(string $modelClass): array
    {
        $config = $this->getModelConfig($modelClass);
        return $config['fields'] ?? [];
    }

    /**
     * Verifica si un modelo está permitido por su clase.
     * Método de compatibilidad para el sistema existente.
     *
     * @param string $modelClass Clase del modelo
     * @return bool True si el modelo está permitido
     */
    public function isModelAllowedByClass(string $modelClass): bool
    {
        return $this->getModelConfig($modelClass) !== null;
    }

    /**
     * Obtiene la configuración completa de un modelo por su ID.
     *
     * @param int $modelId ID del modelo
     * @return array|null Configuración completa del modelo
     */
    public function getModelConfigById(int $modelId): ?array
    {
        return config("kpis.available_kpi_models.{$modelId}");
    }

    /**
     * Obtiene todos los modelos disponibles con su información completa.
     *
     * @return array Lista de modelos con información completa
     */
    public function getAllModelsWithInfo(): array
    {
        $models = [];
        $availableModels = config('kpis.available_kpi_models', []);

        foreach ($availableModels as $modelId => $config) {
            $models[] = [
                'id' => $modelId,
                'class' => $config['class'],
                'display_name' => $config['display_name'],
                'fields' => $config['fields'] ?? [],
                'fields_count' => count($config['fields'] ?? [])
            ];
        }

        return $models;
    }
}
