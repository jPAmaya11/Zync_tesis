<?php

use Illuminate\Database\Migrations\Migration;
use Modules\GestionProyectos\Models\GpUserColumnPreference;

/**
 * Inserta la columna nueva 'categoria' justo DESPUÉS de 'solicitado_por' en las prefs de
 * columnas guardadas de cada espacio y de cada usuario.
 *
 * Sin esto la columna aparecería, pero AL FINAL de la tabla en todos los espacios que ya
 * existen: CustomFieldService mezcla las columnas del catálogo que falten usando
 * $maxOrder++, o sea que las cuelga al final. Los espacios nuevos sí la verían en su
 * sitio (salen de buildDefaultColumns, que respeta el orden del config).
 *
 * Quirúrgica: solo inserta 'categoria' y renumera 'order'. NO cambia el orden relativo de
 * ninguna otra columna, cosa que sí hacía la migración de normalización de junio.
 *
 * IDEMPOTENTE: si ya está justo tras 'solicitado_por', no toca nada. Mismo patrón exacto
 * que 2026_06_11_000020_insert_validado_por_column_in_prefs.
 */
return new class extends Migration
{
    public function up(): void
    {
        GpUserColumnPreference::query()->get()->each(function (GpUserColumnPreference $pref) {
            $cols = $pref->columns;

            if (! is_array($cols) || empty($cols)) {
                return;
            }

            usort($cols, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

            $keys    = array_column($cols, 'key');
            $solIdx  = array_search('solicitado_por', $keys, true);
            $catIdx  = array_search('categoria', $keys, true);

            // Sin 'solicitado_por' en esta pref: que la mezcle el servicio al final.
            if ($solIdx === false) {
                return;
            }

            // Ya está justo después de 'solicitado_por' → nada que hacer.
            if ($catIdx !== false && $catIdx === $solIdx + 1) {
                return;
            }

            $catCol = $catIdx !== false
                ? $cols[$catIdx]
                : ['key' => 'categoria', 'name' => 'Categoría', 'type' => 'text', 'visible' => true];

            $catCol['visible'] = true;

            if ($catIdx !== false) {
                array_splice($cols, $catIdx, 1);
            }

            $solIdx = array_search('solicitado_por', array_column($cols, 'key'), true);
            array_splice($cols, $solIdx + 1, 0, [$catCol]);

            foreach ($cols as $i => $c) {
                $cols[$i]['order'] = $i + 1;
            }

            $pref->columns = $cols;
            $pref->save();
        });
    }

    public function down(): void
    {
        // No-op: la columna sigue en el catálogo; quitarla del orden no aporta nada.
    }
};
