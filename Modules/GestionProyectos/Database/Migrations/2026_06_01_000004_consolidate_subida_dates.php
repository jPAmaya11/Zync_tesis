<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unifica el duplicado de "Subida Stage" / "Subida Producción".
 *
 * El import de Jira dejó las fechas en custom fields ("Subida Stage" / "Subida
 * Producción"), mientras las columnas nativas del flujo (fecha_entrega /
 * fecha_aprobacion) quedaron vacías → el concepto aparecía dos veces.
 *
 * Esta migración:
 *  1) COPIA los valores de los custom a las columnas nativas (canónicas) donde la
 *     nativa esté vacía. NO borra ningún valor (los custom_field_values quedan).
 *  2) DESACTIVA los custom duplicados → desaparecen de la tabla; quedan las nativas
 *     "Fecha Subida Stage" / "Fecha Subida Producción".
 *
 * Los IDs de los custom se resuelven POR NOMBRE para que funcione igual en prod.
 * Reversible (down → reactiva los custom; los valores copiados a las nativas quedan).
 *
 * Nota: se usa el query builder (en vez de un UPDATE...JOIN con REGEXP, sintaxis
 * exclusiva de MySQL) para que funcione igual en MySQL y en PostgreSQL.
 */
return new class extends Migration
{
    private array $stageNames = ['Subida Stage'];
    private array $prodNames  = ['Subida Producción', 'Subida Produccion'];

    public function up(): void
    {
        // Subida Stage  → fecha_entrega
        $stageId = DB::table('gp_custom_fields')->whereIn('name', $this->stageNames)->value('id');
        if ($stageId) {
            $this->copyCustomFieldToNative($stageId, 'fecha_entrega');
        }

        // Subida Producción → fecha_aprobacion
        $prodId = DB::table('gp_custom_fields')->whereIn('name', $this->prodNames)->value('id');
        if ($prodId) {
            $this->copyCustomFieldToNative($prodId, 'fecha_aprobacion');
        }

        // Desactivar los custom duplicados (sin borrar sus valores).
        DB::table('gp_custom_fields')
            ->whereIn('name', array_merge($this->stageNames, $this->prodNames))
            ->update(['active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('gp_custom_fields')
            ->whereIn('name', array_merge($this->stageNames, $this->prodNames))
            ->update(['active' => true, 'updated_at' => now()]);
    }

    /**
     * Copia value de gp_custom_field_values → la columna nativa indicada en
     * gp_proyectos, solo donde la nativa esté vacía y el value tenga pinta de
     * fecha ISO (YYYY-MM-DD...). Portable entre MySQL y PostgreSQL.
     */
    private function copyCustomFieldToNative(int $customFieldId, string $nativeColumn): void
    {
        DB::table('gp_custom_field_values')
            ->where('custom_field_id', $customFieldId)
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($nativeColumn) {
                foreach ($rows as $row) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $row->value)) {
                        continue;
                    }

                    DB::table('gp_proyectos')
                        ->where('id', $row->proyecto_id)
                        ->whereNull($nativeColumn)
                        ->update([$nativeColumn => $row->value]);
                }
            });
    }
};
