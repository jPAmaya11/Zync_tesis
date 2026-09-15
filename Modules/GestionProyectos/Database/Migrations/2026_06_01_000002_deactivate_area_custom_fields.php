<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva los campos personalizados "Área Negocios" y "Área Responsable"
 * (heredados del import de Jira). Quedan inservibles: no tienen input en el modal
 * de creación. Al ponerlos active=false desaparecen de la tabla y de cualquier
 * vista/listado que filtra por ->active(), SIN borrar sus valores guardados
 * (gp_custom_field_values) y SIN tocar el seeder. Reversible (down → active=true).
 *
 * Nota: si alguna vez se re-corre el seeder de import, los recrearía activos y
 * habría que volver a aplicar esta desactivación.
 */
return new class extends Migration
{
    private array $names = ['Área Negocios', 'Área de Negocios', 'Área Responsable'];

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
