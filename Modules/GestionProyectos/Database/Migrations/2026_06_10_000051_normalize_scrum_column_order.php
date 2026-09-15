<?php

use Illuminate\Database\Migrations\Migration;
use Modules\GestionProyectos\Models\GpUserColumnPreference;

/**
 * Normaliza el ORDEN COMPLETO de columnas guardado por espacio en
 * gp_user_column_preferences para que TODOS los espacios queden con la formación
 * canónica del catálogo (config gestion-proyectos.catalog_columns):
 *
 *   Tipo → Actividad → Creada → Fecha Inicio → Fecha Límite → Stage → Producción → (resto)
 *
 * Motivo: en producción hay espacios con un orden ANTIGUO (fechas dispersas) que nunca
 * tomó los cambios de orden posteriores; solo el espacio ERP quedó bien (reordenado a
 * mano). Reordenar un único campo NO los arregla: hay que reconstruir la secuencia entera.
 *
 * Reglas:
 *  - Las columnas del CATÁLOGO se colocan en el orden exacto del config.
 *  - Se PRESERVA la visibilidad existente de cada columna (mostrar/ocultar del usuario).
 *  - Los campos PERSONALIZADOS (keys que no están en el catálogo) se conservan y se
 *    anexan después, respetando su orden relativo actual.
 *  - Se renumera 'order' (1..N) según la nueva secuencia.
 *
 * Aplica a TODAS las prefs (default del espacio user_id=null y las personales). Es
 * IDEMPOTENTE: re-ejecutarla deja el mismo resultado canónico (un espacio ya correcto
 * como ERP queda igual).
 */
return new class extends Migration
{
    public function up(): void
    {
        $catalog = config('gestion-proyectos.catalog_columns', []);
        if (empty($catalog)) {
            return;
        }
        $catalogKeys = array_column($catalog, 'key');

        GpUserColumnPreference::query()->get()->each(function (GpUserColumnPreference $pref) use ($catalog, $catalogKeys) {
            $cols = $pref->columns;
            if (!is_array($cols) || empty($cols)) {
                return;
            }

            // Estado existente por key: se usa para PRESERVAR la visibilidad (y todo el
            // objeto, en el caso de los campos personalizados).
            $existingByKey = [];
            foreach ($cols as $c) {
                if (isset($c['key'])) {
                    $existingByKey[$c['key']] = $c;
                }
            }

            $new   = [];
            $order = 1;

            // 1) Columnas del catálogo en el ORDEN CANÓNICO del config.
            foreach ($catalog as $cat) {
                $prev = $existingByKey[$cat['key']] ?? null;
                $new[] = [
                    'key'     => $cat['key'],
                    'name'    => $cat['name'],
                    'type'    => $cat['type'] ?? 'text',
                    'visible' => $prev['visible'] ?? ($cat['visible_by_default'] ?? true),
                    'order'   => $order++,
                ];
            }

            // 2) Campos personalizados (keys que NO están en el catálogo), en su orden actual.
            $custom = array_values(array_filter(
                $cols,
                fn ($c) => isset($c['key']) && !in_array($c['key'], $catalogKeys, true)
            ));
            usort($custom, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
            foreach ($custom as $c) {
                $c['order'] = $order++;
                $new[] = $c;
            }

            $pref->columns = $new;
            $pref->save();
        });
    }

    public function down(): void
    {
        // No reversible de forma segura (el orden previo no se conserva). No-op.
    }
};
