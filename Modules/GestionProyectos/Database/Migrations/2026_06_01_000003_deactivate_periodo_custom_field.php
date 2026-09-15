<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva el campo personalizado "Periodo" (heredado del import de Jira).
 * Inservible: no tiene input en el modal de creación. Con active=false desaparece
 * de la tabla y de cualquier vista que filtra por ->active(), SIN borrar sus valores
 * guardados (gp_custom_field_values) y SIN tocar el seeder. Reversible (down → active=true).
 *
 * Nota: si se re-corre el seeder de import, lo recrearía activo y habría que reaplicar.
 */
return new class extends Migration
{
    private array $names = ['Periodo', 'Período'];

    public function up(): void
    {
        DB::table('gp_custom_fields')
            ->whereIn('name', $this->names)
            ->update(['active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('gp_custom_fields')
            ->whereIn('name', $this->names)
            ->update(['active' => true, 'updated_at' => now()]);
    }
};
