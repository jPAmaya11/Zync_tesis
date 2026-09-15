<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva el campo personalizado "Tenancy" (heredado del import de Jira).
 *
 * Aparece SOLO en el espacio ERP, porque es el único creado desde el snapshot de Jira
 * (Database/Seeders/fixtures/erp_jira_snapshot.php lo declara en 'custom_fields'), y
 * gp_custom_fields está scopeado por project_key. En la tabla sale una columna
 * "TENANCY" siempre vacía: la columna de datos `tenancy` de gp_proyectos se eliminó en
 * 2026_05_13_000002_refactor_gp_proyectos_scrum (la sustituyeron Software y Entorno),
 * pero el CAMPO PERSONALIZADO del import se quedó activo y nadie lo rellena.
 *
 * Con active=false desaparece de la tabla sin borrar nada: CustomFieldService filtra
 * las columnas por los custom fields activos (`$allowedKeys = catálogo + activos`), así
 * que basta con esto — NO hay que tocar gp_user_column_preferences aunque la clave siga
 * guardada en su JSON. Los valores en gp_custom_field_values se conservan por si acaso.
 *
 * Mismo patrón y motivo que las migraciones hermanas que ya desactivaron "area"
 * (2026_06_01_000002) y "Periodo" (2026_06_01_000003), del mismo import. Reversible.
 *
 * Nota: si se re-corre el seeder de import, lo recrearía activo y habría que reaplicar.
 */
return new class extends Migration
{
    private array $names = ['Tenancy', 'Tenency', 'Tenencia'];

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
