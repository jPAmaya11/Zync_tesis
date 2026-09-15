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
            DB::statement(
                "UPDATE gp_proyectos p
                 JOIN gp_custom_field_values v ON v.proyecto_id = p.id AND v.custom_field_id = ?
                 SET p.fecha_entrega = v.value
                 WHERE p.fecha_entrega IS NULL
                   AND v.value IS NOT NULL AND v.value <> ''
                   AND v.value REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'",
                [$stageId]
            );
        }

        // Subida Producción → fecha_aprobacion
        $prodId = DB::table('gp_custom_fields')->whereIn('name', $this->prodNames)->value('id');
        if ($prodId) {
            DB::statement(
                "UPDATE gp_proyectos p
                 JOIN gp_custom_field_values v ON v.proyecto_id = p.id AND v.custom_field_id = ?
                 SET p.fecha_aprobacion = v.value
                 WHERE p.fecha_aprobacion IS NULL
                   AND v.value IS NOT NULL AND v.value <> ''
                   AND v.value REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'",
                [$prodId]
            );
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
};
