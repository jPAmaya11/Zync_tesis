<?php

namespace Modules\GestionProyectos\Services\Contracts;

use Modules\GestionProyectos\Models\GpCustomField;

interface CustomFieldServiceInterface
{
    /**
     * Tipos de campo soportados por el sistema.
     * @return array [['value' => 'text', 'label' => 'Texto'], ...]
     */
    public function getAvailableFieldTypes(): array;

    /**
     * Columnas del catálogo del sistema (campos fijos predefinidos).
     * Vienen de config('gestion-proyectos.catalog_columns').
     * @return array
     */
    public function getCatalogColumns(): array;

    /**
     * Obtener campos personalizados activos de un proyecto ordenados.
     * @param  string $projectKey
     * @return GpCustomField[]
     */
    public function getCustomFields(string $projectKey): array;

    /**
     * Crear un campo personalizado en un proyecto.
     * @param  string $projectKey
     * @param  array  $data [name, type, options?, order?]
     * @return GpCustomField
     */
    public function addCustomField(string $projectKey, array $data): GpCustomField;

    /**
     * Actualizar un campo personalizado.
     * @param  int   $id
     * @param  array $data
     * @return GpCustomField
     */
    public function updateCustomField(int $id, array $data): GpCustomField;

    /**
     * Desactivar (soft delete lógico) un campo personalizado.
     * @param  int $id
     * @return void
     */
    public function deleteCustomField(int $id): void;

    /**
     * Obtener preferencias de columnas para un usuario en un proyecto.
     * Cascada: personal del usuario → default del proyecto (user_id=null) → config por defecto.
     * @param  int    $userId
     * @param  string $projectKey
     * @return array  [{ key, visible, order, name, field_id? }, ...]
     */
    public function getColumnPreferences(int $userId, string $projectKey): array;

    /**
     * Guardar el default de columnas del proyecto (user_id = null).
     * Todos los usuarios sin preferencias personales heredarán este default.
     * @param  string $projectKey
     * @param  array  $columns
     * @return void
     */
    public function saveColumnPreferences(string $projectKey, array $columns): void;

    /**
     * Guardar preferencias personales de un usuario concreto.
     * @param  int    $userId
     * @param  string $projectKey
     * @param  array  $columns
     * @return void
     */
    public function savePersonalColumnPreferences(int $userId, string $projectKey, array $columns): void;

    /**
     * Generar la configuración de columnas por defecto para un proyecto.
     * Combina catalog_columns fijas + custom fields activos del proyecto.
     * @param  string $projectKey
     * @return array
     */
    public function buildDefaultColumns(string $projectKey): array;

    /**
     * Sembrar los campos personalizados por defecto al crear un proyecto.
     * Crea: Estimación (Días), Fecha Programada, Fecha Ejecución,
     * F. Subida Stage, F. Subida Producción. Marcados con is_default=true.
     * Además sembra la preferencia de columnas default del proyecto (user_id=null)
     * para que los campos aparezcan visibles inmediatamente.
     * @param  string $projectKey
     * @return void
     */
    public function seedDefaultFields(string $projectKey): void;
}
