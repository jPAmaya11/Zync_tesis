<?php

namespace Modules\GestionProyectos\Services;

use Modules\GestionProyectos\Models\GpCustomField;
use Modules\GestionProyectos\Models\GpUserColumnPreference;
use Modules\GestionProyectos\Services\Contracts\CustomFieldServiceInterface;

class CustomFieldService implements CustomFieldServiceInterface
{
    // ─── Tipos de campo disponibles ───────────────────────────────────────────

    public function getAvailableFieldTypes(): array
    {
        return [
            ['value' => 'text',      'label' => 'Short text',  'icon' => 'Aa',  'description' => 'Texto corto de una sola línea.',           'beta' => false],
            ['value' => 'paragraph', 'label' => 'Paragraph',   'icon' => '≡',   'description' => 'Bloque de texto multilínea.',              'beta' => false],
            ['value' => 'timestamp', 'label' => 'Timestamp',   'icon' => '🕒',  'description' => 'Fecha y hora de creación/modificación.',   'beta' => false],
            ['value' => 'dropdown',  'label' => 'Dropdown',    'icon' => '▽',   'description' => 'Lista de opciones para selección única.',  'beta' => false],
            ['value' => 'date',      'label' => 'Date',        'icon' => '📅',  'description' => 'Selector de calendario (día/mes/año).',    'beta' => false],
            ['value' => 'number',    'label' => 'Number',      'icon' => '123', 'description' => 'Valores numéricos enteros o decimales.',   'beta' => false],
            ['value' => 'labels',    'label' => 'Labels',      'icon' => '🏷️',  'description' => 'Etiquetas o categorías organizativas.',    'beta' => false],
            ['value' => 'checkbox',  'label' => 'Checkbox',    'icon' => '☑️',  'description' => 'Valor binario (Sí/No, Verdadero/Falso).', 'beta' => false],
            ['value' => 'people',    'label' => 'People',      'icon' => '👤',  'description' => 'Asignación de usuarios o miembros.',       'beta' => false],
            ['value' => 'url',       'label' => 'Url',         'icon' => '🔗',  'description' => 'Enlace web con validación de formato.',    'beta' => false],
        ];
    }

    // ─── Catálogo de columnas del sistema ─────────────────────────────────────

    public function getCatalogColumns(): array
    {
        return config('gestion-proyectos.catalog_columns', []);
    }

    // ─── CRUD de campos personalizados ────────────────────────────────────────

    public function getCustomFields(string $projectKey): array
    {
        return GpCustomField::where('project_key', $projectKey)
            ->active()
            ->ordered()
            ->get()
            ->map(fn (GpCustomField $f) => $f->toFrontend())
            ->values()
            ->all();
    }

    public function addCustomField(string $projectKey, array $data): GpCustomField
    {
        if (! \Modules\GestionProyectos\Models\GpProject::where('key', $projectKey)->exists()) {
            throw new \InvalidArgumentException("El espacio '{$projectKey}' no existe.");
        }

        // Determinar el siguiente orden
        $maxOrder = GpCustomField::where('project_key', $projectKey)->max('order') ?? 0;

        return GpCustomField::create([
            'project_key' => $projectKey,
            'name'        => $data['name'],
            'type'        => $data['type']    ?? 'text',
            'options'     => $data['options'] ?? null,
            'order'       => $data['order']   ?? ($maxOrder + 1),
            'active'      => true,
        ]);
    }

    public function updateCustomField(int $id, array $data): GpCustomField
    {
        $field = GpCustomField::findOrFail($id);
        $field->update(array_filter([
            'name'    => $data['name']    ?? null,
            'type'    => $data['type']    ?? null,
            'options' => $data['options'] ?? null,
            'order'   => $data['order']   ?? null,
        ], fn ($v) => $v !== null));

        return $field->fresh();
    }

    public function deleteCustomField(int $id): void
    {
        GpCustomField::findOrFail($id)->update(['active' => false]);
    }

    // ─── Preferencias de columnas ─────────────────────────────────────────────
    //
    //  Cascada de resolución:
    //    1. Preferencias personales del usuario  (user_id = $userId)
    //    2. Default del proyecto                 (user_id = null)
    //    3. Config por defecto del sistema       (buildDefaultColumns)

    public function getColumnPreferences(int $userId, string $projectKey): array
    {
        // 1. Preferencias personales
        $pref = GpUserColumnPreference::where('user_id', $userId)
            ->where('project_key', $projectKey)
            ->first();

        // 2. Default del proyecto
        if (!$pref) {
            $pref = GpUserColumnPreference::whereNull('user_id')
                ->where('project_key', $projectKey)
                ->first();
        }

        if ($pref) {
            return $this->mergeFreshColumns($pref->columns, $projectKey);
        }

        // 3. Config por defecto del sistema
        return $this->buildDefaultColumns($projectKey);
    }

    /**
     * Guarda el default de columnas del proyecto (user_id = null).
     * Todos los usuarios con sólo permiso de ver heredarán este default.
     */
    public function saveColumnPreferences(string $projectKey, array $columns): void
    {
        GpUserColumnPreference::updateOrCreate(
            ['user_id' => null, 'project_key' => $projectKey],
            ['columns' => $columns]
        );
    }

    /**
     * Guarda preferencias personales de un usuario concreto.
     * Útil en el futuro si se quieren overrides por persona.
     */
    public function savePersonalColumnPreferences(int $userId, string $projectKey, array $columns): void
    {
        GpUserColumnPreference::updateOrCreate(
            ['user_id' => $userId, 'project_key' => $projectKey],
            ['columns' => $columns]
        );
    }

    // ─── Helper: mezclar columnas guardadas con campos nuevos ─────────────────

    private function mergeFreshColumns(array $savedColumns, string $projectKey): array
    {
        // Refrescar nombres de columnas del catálogo (por si cambiaron en config)
        $catalogByKey = collect($this->getCatalogColumns())->keyBy('key');
        $savedColumns = array_map(function ($col) use ($catalogByKey) {
            if (isset($catalogByKey[$col['key']])) {
                $col['name'] = $catalogByKey[$col['key']]['name'];
                $col['type'] = $catalogByKey[$col['key']]['type'] ?? 'text';
                
                // Forzar visibilidad a true para columnas críticas del catálogo
                if (in_array($col['key'], ['dias_estimados', 'fecha_entrega', 'fecha_aprobacion', 'aprobado_por', 'validado_por', 'updated_at'], true)) {
                    $col['visible'] = true;
                }
            }
            return $col;
        }, $savedColumns);

        $customFields    = GpCustomField::where('project_key', $projectKey)->active()->ordered()->get();

        // Refrescar metadatos de custom fields ya guardados.
        // Sin esto, columnas guardadas antes de que se añadiera 'type' o 'field_id'
        // quedan con valores obsoletos, rompiendo el editor inline y saveCustomFieldValue.
        $customFieldsByKey = $customFields->keyBy('column_key');
        $savedColumns = array_map(function ($col) use ($customFieldsByKey) {
            if (isset($customFieldsByKey[$col['key']])) {
                $cf              = $customFieldsByKey[$col['key']];
                $col['name']     = $cf->name;
                $col['type']     = $cf->type;
                $col['field_id'] = $cf->id;
            }
            return $col;
        }, $savedColumns);

        $savedKeys       = collect($savedColumns)->pluck('key')->all();
        $maxOrder        = collect($savedColumns)->max('order') ?? count($savedColumns);
        $newColumns      = [];

        foreach ($customFields as $cf) {
            if (!in_array($cf->column_key, $savedKeys, true)) {
                $maxOrder++;
                $newColumns[] = [
                    'key'      => $cf->column_key,
                    'name'     => $cf->name,
                    'visible'  => true,
                    'order'    => $maxOrder,
                    'field_id' => $cf->id,
                    'type'     => $cf->type,
                ];
            }
        }

        // Mezclar también columnas del catálogo que falten en las preferencias guardadas
        $catalogColumns = $this->getCatalogColumns();
        foreach ($catalogColumns as $col) {
            if (!in_array($col['key'], $savedKeys, true)) {
                $maxOrder++;
                $newColumns[] = [
                    'key'     => $col['key'],
                    'name'    => $col['name'],
                    'type'    => $col['type'] ?? 'text',
                    'visible' => $col['visible_by_default'] ?? true,
                    'order'   => $maxOrder,
                ];
            }
        }

        $merged = array_merge($savedColumns, $newColumns);

        // Eliminar columnas de custom fields que ya no están activos
        $activeCustomKeys = $customFields->map(fn ($f) => $f->column_key)->all();
        $catalogKeys      = collect($this->getCatalogColumns())->pluck('key')->all();
        $allowedKeys      = array_merge($catalogKeys, $activeCustomKeys);

        return array_values(array_filter(
            $merged,
            fn ($col) => in_array($col['key'], $allowedKeys, true)
        ));
    }

    public function buildDefaultColumns(string $projectKey = ''): array
    {
        $catalog = collect($this->getCatalogColumns());
        $order   = 1;

        // Columnas fijas del catálogo (por defecto todas visibles)
        $columns = $catalog->map(function ($col) use (&$order) {
            $visible = $col['visible_by_default'] ?? true;
            if (in_array($col['key'], ['dias_estimados', 'fecha_entrega', 'fecha_aprobacion', 'aprobado_por', 'validado_por', 'updated_at'], true)) {
                $visible = true;
            }
            return [
                'key'     => $col['key'],
                'name'    => $col['name'],
                'type'    => $col['type'] ?? 'text',
                'visible' => $visible,
                'order'   => $order++,
            ];
        })->values()->all();

        // Campos personalizados del proyecto (todos visibles por defecto)
        $customFields = GpCustomField::where('project_key', $projectKey)
            ->active()
            ->ordered()
            ->get();

        foreach ($customFields as $cf) {
            $columns[] = [
                'key'      => $cf->column_key,
                'name'     => $cf->name,
                'visible'  => true,
                'order'    => $order++,
                'field_id' => $cf->id,
                'type'     => $cf->type,
            ];
        }

        return $columns;
    }

    // ─── Seeding de campos por defecto al crear proyecto ──────────────────────

    /**
     * Campos personalizados que se crean automáticamente para cada proyecto nuevo.
     * Cada uno tendrá is_default=true (no se puede eliminar, sí renombrar).
     */
    private const DEFAULT_PROJECT_FIELDS = [];

    public function seedDefaultFields(string $projectKey): void
    {
        $order = 1;
        $createdFields = [];

        foreach (self::DEFAULT_PROJECT_FIELDS as $field) {
            $createdFields[] = GpCustomField::create([
                'project_key' => $projectKey,
                'name'        => $field['name'],
                'type'        => $field['type'],
                'options'     => null,
                'order'       => $order++,
                'active'      => true,
                'is_default'  => true,
            ]);
        }

        // Sembrar preferencias de columnas default del proyecto (user_id = null)
        // para que los campos aparezcan visibles inmediatamente al abrir el proyecto.
        $columns       = $this->buildDefaultColumns($projectKey);
        $this->saveColumnPreferences($projectKey, $columns);
    }
}
