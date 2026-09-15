<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza el campo `impacto` de gp_proyectos: deja únicamente los niveles
 * oficiales (los mismos del modal de crear actividad/subactividad). Limpia los
 * valores heredados del import de Jira (IDs, URLs, "Cartera", "Global", etc.) y
 * cualquier área suelta que se haya colado por la API/MCP ("Operaciones"…).
 */
return new class extends Migration
{
    public function up(): void
    {
        $validos = ['Crítico', 'Alto', 'Medio', 'Bajo', 'Sin impacto'];

        DB::table('gp_proyectos')
            ->whereNotNull('impacto')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($validos) {
                foreach ($rows as $row) {
                    $actual = json_decode($row->impacto, true);
                    if (!is_array($actual)) {
                        continue;
                    }

                    $limpio = array_values(array_filter(
                        $actual,
                        fn ($v) => in_array($v, $validos, true)
                    ));

                    // Solo tocamos las filas que realmente tenían valores inválidos.
                    if (count($limpio) !== count($actual)) {
                        DB::table('gp_proyectos')
                            ->where('id', $row->id)
                            ->update(['impacto' => empty($limpio) ? null : json_encode($limpio)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Irreversible: los valores inválidos eliminados no se pueden recuperar.
    }
};
