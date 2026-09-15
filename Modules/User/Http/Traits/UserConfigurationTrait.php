<?php

namespace Modules\User\Http\Traits;

use Illuminate\Http\Request;
use Modules\Role\Models\Role;
use Modules\User\Models\User;

trait UserConfigurationTrait
{
    /**
     * Obtener configuración completa para el frontend (FiltroUltra)
     * 
     * IMPORTANTE: Hay 2 secciones independientes:
     * - field_options: Opciones del dropdown de busqueda PARTE SUPERIOR
     * - chip_filters: Filtros que aparecen como chips PARTE INFERIOR 
     * 
     * Tú decides qué va en cada lugar, no hay duplicación automática.
     */
    protected function getConfiguration(Request $request): array
    {
        return [
            'paginacion' => $this->getPaginacionConfiguration($request),
            'busqueda' => $this->getBusquedaConfiguration($request),

            // Opciones del dropdown de búsqueda (arriba)
            'field_options' => $this->getFieldOptions(),

            // Datos para los selectores dentro de búsqueda (cuando type=select)
            'search_options_data' => $this->getSearchOptionsData(),

            // Filtros que aparecen como chips (abajo) - SEPARADOS de búsqueda
            'chip_filters' => $this->getChipFilters(),
            'chip_filters_selected' => $this->getChipFiltersSelected($request),
            'chip_filters_labels' => $this->getChipFiltersLabels(),

            // Usuarios para búsqueda tipo 'user'
            'usuarios' => $this->getUsuariosParaFiltros(),
        ];
    }

    /**
     * Configuración de paginación
     */
    private function getPaginacionConfiguration(Request $request): array
    {
        return [
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('perPage', 20),
        ];
    }

    /**
     * Configuración de búsqueda actual
     */
    private function getBusquedaConfiguration(Request $request): array
    {
        return [
            'termino' => $request->input('search', ''),
            'campo' => $request->input('search_campo', ''),
        ];
    }

    /**
     * ============================================
     * SECCIÓN 1: DROPDOWN DE BÚSQUEDA (PARTE SUPERIOR)
     * ============================================
     * 
     * Estas son las opciones del selector "Búsqueda global / Nombre / Rol..."
     * 
     * Tipos disponibles:
     * - 'text': Input de texto libre
     * - 'select': Dropdown con opciones (usa optionsKey para saber de dónde tomar las opciones)
     * - 'user': Selector de usuario especial
     */
    protected function getFieldOptions(): array
    {
        return [
            [
                'value' => '',
                'label' => 'Búsqueda global',
                'description' => 'Buscar en todos los campos',
                'type' => 'text',
                'default' => true,
            ],
            [
                'value' => 'name',
                'label' => 'Nombre',
                'description' => 'Buscar por nombre',
                'type' => 'text',
            ],
            [
                'value' => 'email',
                'label' => 'Email',
                'description' => 'Buscar por email',
                'type' => 'text',
            ],
            [
                'value' => 'numero_documento',
                'label' => 'N° Documento',
                'description' => 'Buscar por documento',
                'type' => 'text',
            ],
            [
                'value' => 'phone',
                'label' => 'Teléfono',
                'description' => 'Buscar por teléfono',
                'type' => 'text',
            ],
            [
                'value' => 'role',
                'label' => 'Rol',
                'description' => 'Seleccionar un rol',
                'type' => 'select',
                'optionsKey' => 'roles', // Toma opciones de search_options_data.roles
                'paramName' => 'role_id',
            ],
            // Si quisieras agregar departamento aquí en vez de como chip:
            // [
            //     'value' => 'department',
            //     'label' => 'Departamento',
            //     'description' => 'Seleccionar departamento',
            //     'type' => 'select',
            //     'optionsKey' => 'departamentos',
            //     'paramName' => 'department',
            // ],
        ];
    }

    /**
     * Datos para los selectores dentro de búsqueda
     * Cuando un field_option tiene type='select', busca sus opciones aquí
     */
    protected function getSearchOptionsData(): array
    {
        return [
            'roles' => Role::select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'label' => $r->name,
                ])
                ->toArray(),
        ];
    }

    /**
     * ============================================
     * SECCIÓN 2: FILTROS CHIP (ABAJO - HUÉRFANOS)
     * ============================================
     * 
     * Estos filtros aparecen como botones/chips debajo de la búsqueda.
     * Son independientes del dropdown de búsqueda.
     */
    protected function getChipFilters(): array
    {
        return [
            // Departamentos como chips
            'departamentos' => User::whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department')
                ->map(fn($d) => [
                    'id' => $d,
                    'label' => $d,
                ])
                ->toArray(),

            // Posiciones como chips
            'posiciones' => User::whereNotNull('position')
                ->where('position', '!=', '')
                ->distinct()
                ->orderBy('position')
                ->pluck('position')
                ->map(fn($p) => [
                    'id' => $p,
                    'label' => $p,
                ])
                ->toArray(),

            // Estados como chips
            'estados' => [
                ['id' => 1, 'label' => 'Activo'],
                ['id' => 0, 'label' => 'Inactivo'],
            ],
        ];
    }

    /**
     * Labels para los filtros chip
     */
    protected function getChipFiltersLabels(): array
    {
        return [
            'departamentos' => 'Departamentos',
            'posiciones' => 'Cargos / Posiciones',
            'estados' => 'Estados',
        ];
    }

    /**
     * Mapeo de chip filter key -> parámetro del backend
     */
    protected function getChipFiltersParamMap(): array
    {
        return [
            'departamentos' => 'department',
            'posiciones' => 'position',
            'estados' => 'active',
        ];
    }

    /**
     * Obtener chips actualmente seleccionados
     */
    protected function getChipFiltersSelected(Request $request): array
    {
        $selected = [];
        $paramMap = $this->getChipFiltersParamMap();

        foreach ($paramMap as $chipKey => $paramName) {
            if ($request->filled($paramName)) {
                $values = is_array($request->$paramName)
                    ? $request->$paramName
                    : [$request->$paramName];

                $selected[$chipKey] = collect($values)->map(fn($v) => [
                    'id' => $v,
                    'label' => $this->getLabelForValue($chipKey, $v),
                ])->toArray();
            }
        }

        return $selected;
    }

    /**
     * Obtener label para un valor de chip
     */
    private function getLabelForValue(string $chipKey, $value): string
    {
        if ($chipKey === 'estados') {
            return $value ? 'Activo' : 'Inactivo';
        }
        return (string) $value;
    }

    /**
     * Usuarios para búsqueda tipo 'user'
     */
    protected function getUsuariosParaFiltros(): array
    {
        return User::select('id', 'name', 'email')
            ->where('active', true)
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])
            ->toArray();
    }

    /**
     * Aplicar filtros a la query de usuarios
     */
    protected function applyFilters($query, Request $request)
    {
        // === SEGURIDAD GLOBAL: solo un admin puede ver a los usuarios con rol "admin" ===
        if (! optional($request->user())->hasRole('admin')) {
            $query->whereDoesntHave('roles', fn($q) => $q->where('name', 'admin'));
        }

        // === BÚSQUEDA ===
        if ($request->filled('search')) {
            $search = $request->search;
            $campo = $request->input('search_campo', '');

            if ($campo && $campo !== '') {
                $this->applyFieldSearch($query, $campo, $search);
            } else {
                $query->where(function ($q) use ($search) {
                    $this->applyGlobalSearch($q, $search);
                });
            }
        }

        // === FILTROS DEL DROPDOWN (type=select) ===
        if ($request->filled('role_id')) {
            $roleIds = is_array($request->role_id) ? $request->role_id : [$request->role_id];
            $query->whereHas('roles', fn($q) => $q->whereIn('roles.id', $roleIds));
        }

        // === FILTROS CHIP ===
        if ($request->filled('department')) {
            $departments = is_array($request->department) ? $request->department : [$request->department];
            $query->whereIn('department', $departments);
        }

        if ($request->filled('position')) {
            $positions = is_array($request->position) ? $request->position : [$request->position];
            $query->whereIn('position', $positions);
        }

        if ($request->filled('active')) {
            $query->where('active', (bool) $request->active);
        }

        // Filtro "Creado por"
        if ($request->filled('created_by')) {
            $creators = is_array($request->created_by) ? $request->created_by : [$request->created_by];
            $query->whereIn('created_by', $creators);
        }

        // === FILTROS DE FECHA ===
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $dateColumn = $request->input('date_type') === 'updated_at' ? 'updated_at' : 'created_at';
            if ($request->filled('date_from')) {
                $query->whereDate($dateColumn, '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate($dateColumn, '<=', $request->date_to);
            }
        }

        return $query;
    }

    protected function getDepartamentos(): array
    {
        return User::whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->toArray();
    }

    /**
     * Búsqueda en campo específico
     */
    private function applyFieldSearch($query, string $campo, string $search): void
    {
        $query->where(function ($q) use ($campo, $search) {
            switch ($campo) {
                case 'name':
                    $q->where('name', 'like', "%{$search}%");
                    break;
                case 'email':
                    $q->where('email', 'like', "%{$search}%");
                    break;
                case 'numero_documento':
                    $q->where('numero_documento', 'like', "%{$search}%");
                    break;
                case 'phone':
                    $q->where('phone', 'like', "%{$search}%");
                    break;
                default:
                    $this->applyGlobalSearch($q, $search);
            }
        });
    }

    /**
     * Búsqueda global
     */
    private function applyGlobalSearch($query, string $search): void
    {
        $query->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('numero_documento', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->orWhere('department', 'like', "%{$search}%")
            ->orWhere('position', 'like', "%{$search}%")
            ->orWhereHas('roles', fn($q) => $q->where('name', 'like', "%{$search}%"));
    }
}
