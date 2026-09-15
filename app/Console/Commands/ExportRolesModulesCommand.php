<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Role\Models\Module;
use Modules\Role\Models\Role;

/**
 * Exporta, para cada rol, los módulos a los que tiene acceso.
 *
 * La relación rol→módulo es INDIRECTA: rol → permisos → permissions.module_id → módulo.
 * El comando es 100% read-only (no modifica nada) e idempotente: seguro de correr en Prod.
 *
 * Uso:
 *   php artisan roles:export-modules            # tabla en consola + .xls (Excel)
 *   php artisan roles:export-modules --no-xls   # solo consola
 */
class ExportRolesModulesCommand extends Command
{
    protected $signature = 'roles:export-modules
        {--no-xls : No generar el archivo .xls, solo mostrar la tabla en consola}
        {--path= : Ruta del .xls (por defecto storage/app/roles-modulos.xls)}';

    protected $description = 'Lista cada rol con los módulos a los que tiene acceso (vía sus permisos). Read-only.';

    public function handle(): int
    {
        // Mapa id→nombre de módulo (una sola query).
        $modulesById = Module::pluck('name', 'id');

        $roles = Role::with('permissions:id,name,module_id')->orderBy('name')->get();

        if ($roles->isEmpty()) {
            $this->warn('No hay roles en la base de datos.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($roles as $role) {
            // Módulos distintos a partir de los module_id de sus permisos.
            $moduleNames = $role->permissions
                ->pluck('module_id')
                ->filter()                       // descarta permisos sin módulo (null)
                ->unique()
                ->map(fn ($id) => $modulesById[$id] ?? null)
                ->filter()
                ->sort()
                ->values();

            $rows[] = [
                'rol'        => $role->name,
                'modulos'    => $moduleNames->isNotEmpty() ? $moduleNames->implode(', ') : '—',
                'n_modulos'  => $moduleNames->count(),
                'n_permisos' => $role->permissions->count(),
            ];
        }

        // ── Tabla en consola ──────────────────────────────────────────────────
        $this->table(
            ['Rol', 'Módulos', 'Núm. Módulos', 'Núm. Permisos'],
            array_map(fn ($r) => [
                $r['rol'], $r['modulos'], $r['n_modulos'], $r['n_permisos'],
            ], $rows)
        );

        $this->info(sprintf('Total: %d roles · %d módulos en el sistema.', count($rows), $modulesById->count()));

        // ── XLS (HTML que Excel abre nativo, sin librerías) con columnas anchas ──
        if (! $this->option('no-xls')) {
            $path = $this->option('path') ?: storage_path('app/roles-modulos.xls');
            $dir  = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $this->writeHtmlXls($path, $rows);

            $this->newLine();
            $this->info("Excel generado en: {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * Genera un .xls usando una tabla HTML (Excel lo abre nativo, sin librerías),
     * con la columna "Módulos" ancha y cabeceras en negrita.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeHtmlXls(string $path, array $rows): void
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $html  = "<html xmlns:x=\"urn:schemas-microsoft-com:office:excel\"><head><meta charset=\"UTF-8\">";
        $html .= "<style>
            table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
            th { background:#4338ca; color:#ffffff; font-weight:bold; padding:6px 10px; border:1px solid #3730a3; text-align:left; }
            td { padding:4px 10px; border:1px solid #d0d0d0; vertical-align:top; mso-number-format:'\\@'; }
            .num { text-align:center; }
        </style></head><body>";
        // Anchos de columna (en px aprox → Excel los respeta vía <col width>).
        $html .= "<table><colgroup>"
            . "<col width=\"190\"><col width=\"720\"><col width=\"95\"><col width=\"95\">"
            . "</colgroup>";
        $html .= "<tr><th>Rol</th><th>Módulos</th><th>Núm. Módulos</th><th>Núm. Permisos</th></tr>";
        foreach ($rows as $r) {
            $html .= "<tr>"
                . "<td>{$esc($r['rol'])}</td>"
                . "<td>{$esc($r['modulos'])}</td>"
                . "<td class=\"num\">{$esc($r['n_modulos'])}</td>"
                . "<td class=\"num\">{$esc($r['n_permisos'])}</td>"
                . "</tr>";
        }
        $html .= "</table></body></html>";

        file_put_contents($path, $html);
    }
}
