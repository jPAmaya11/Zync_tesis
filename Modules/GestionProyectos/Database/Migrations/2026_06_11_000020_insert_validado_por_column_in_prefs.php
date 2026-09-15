<?php

use Illuminate\Database\Migrations\Migration;
use Modules\GestionProyectos\Models\GpUserColumnPreference;

/**
 * Inserta la columna nueva 'validado_por' justo DESPUÉS de 'aprobado_por' en las prefs
 * de columnas guardadas de cada espacio. Sin esto, al ser una columna nueva del catálogo,
 * mergeFreshColumns la agregaría al FINAL de los espacios existentes (no en su posición).
 *
 * Quirúrgica: solo inserta 'validado_por' (visible) y renumera 'order'. NO cambia el orden
 * relativo de las demás columnas. Idempotente: si ya está justo tras 'aprobado_por', no toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        GpUserColumnPreference::query()->get()->each(function (GpUserColumnPreference $pref) {
            $cols = $pref->columns;
            if (!is_array($cols) || empty($cols)) {
                return;
            }

            usort($cols, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

            $keys     = array_column($cols, 'key');
            $apIdx    = array_search('aprobado_por', $keys, true);
            $valIdx   = array_search('validado_por', $keys, true);

            // Sin 'aprobado_por' en esta pref: dejar que mergeFreshColumns la agregue al final.
            if ($apIdx === false) {
                return;
            }

            // Ya está justo después de 'aprobado_por' → idempotente.
            if ($valIdx !== false && $valIdx === $apIdx + 1) {
                return;
            }

            // Quitar 'validado_por' de donde esté (si existe) y reinsertar tras 'aprobado_por'.
            $valCol = $valIdx !== false
                ? $cols[$valIdx]
                : ['key' => 'validado_por', 'name' => 'Validado Por', 'type' => 'user', 'visible' => true];
            $valCol['visible'] = true;

            if ($valIdx !== false) {
                array_splice($cols, $valIdx, 1);
            }
            $apIdx = array_search('aprobado_por', array_column($cols, 'key'), true);
            array_splice($cols, $apIdx + 1, 0, [$valCol]);

            foreach ($cols as $i => $c) {
                $cols[$i]['order'] = $i + 1;
            }

            $pref->columns = $cols;
            $pref->save();
        });
    }

    public function down(): void
    {
        // No-op (la columna sigue en el catálogo; quitarla del orden no aporta).
    }
};
