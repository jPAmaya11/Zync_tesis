<?php

return [
    'default_max_results' => 50,

    // Flujo SCRUM PROJECT: Pendiente → En Curso → En Revisión → Finalizado
    // Desvíos desde cualquier estado: En Pausa, Reprogramado, Cancelado
    'statuses' => [
        'Pendiente',
        'En Curso',
        'En Revisión',
        'Finalizado',
        'En Pausa',
        'Reprogramado',
        'Cancelado',
    ],

    // Estados "críticos" en cuanto a AUTORIZACIÓN: solo Aprobador o rol superior
    // (canApprove) puede mover una tarea a estos estados.
    'critical_statuses' => [
        'Finalizado',
        'Reprogramado',
        'Cancelado',
    ],

    // Subconjunto de los críticos que además exige EVIDENCIA (comentario en el Historial
    // de Actividades; el adjunto es opcional). Solo Aprobador/Administrador/Admin global
    // pueden aplicar estos estados, y deben registrar evidencia antes de la transición.
    'evidence_required_statuses' => [
        'Finalizado',
        'Reprogramado',
        'Cancelado',
    ],

    // Estados TERMINALES: una tarea en estos estados ya no admite cambios de estado
    // ni edición de campos (la celda no ofrece opciones y el backend rechaza la transición
    // de salida). Aplica por igual a actividades, subactividades y versiones reprogramadas.
    'terminal_statuses' => [
        'Finalizado',
        'Cancelado',
    ],

    // Estados a los que un Aprobador (solo canApprove, sin canWrite) puede
    // mover una tarea para RECHAZARLA (rollback / rebote al equipo). Los críticos
    // son siempre permitidos para el Aprobador; estos se suman para rechazo.
    'approver_rollback_statuses' => [
        'Pendiente',
        'En Curso',
        'En Pausa',
    ],

    'priorities' => [
        'Alta',
        'Media',
        'Baja',
    ],

    // Niveles de impacto válidos (única fuente de verdad: backend, MCP y los
    // modales de crear actividad/subactividad usan exactamente esta lista).
    'impacto_values' => [
        'Crítico',
        'Alto',
        'Medio',
        'Bajo',
        'Sin impacto',
    ],

    'issue_types' => [
        'Tarea',
        'Error',
        // 'Épica' retirada del selector. Las actividades existentes con issue_type='Épica'
        // se conservan en BD y se siguen mostrando; solo no se pueden crear nuevas.
        // 'Historia' (mostrada en UI como "Configuración") retirada: sin actividades/subactividades
        // con este tipo en BD al momento de quitarla, no requirió migración de datos.
        'Subtarea',
        'Mejora',
    ],

    'projects' => [
        'ERP',
        'CRM',
        'Móvil',
        'Web',
    ],

    // Prefijo para autogenerar el key: PROY-0001, PROY-0002, etc.
    'key_prefix' => env('GP_KEY_PREFIX', 'PROY'),

    // Reintentos no aplican (sin API externa), pero se mantiene por consistencia de config.
    'retry' => [
        'max_attempts'  => 1,
        'base_delay_ms' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Columnas del catálogo del sistema (campos fijos de la tabla gp_proyectos)
    |--------------------------------------------------------------------------
    | visible_by_default: true  → la columna se muestra por defecto
    | visible_by_default: false → la columna está oculta hasta que el usuario la active
    */
    /*
    |--------------------------------------------------------------------------
    | Columnas catálogo — nomenclatura blueprint SCRUM PROJECT v1.0
    |--------------------------------------------------------------------------
    */
    'catalog_columns' => [
        ['key' => 'issue_type',         'name' => 'Tipo',                  'type' => 'select', 'visible_by_default' => true],
        ['key' => 'summary',            'name' => 'Actividad',             'type' => 'text',   'visible_by_default' => true],
        // Fechas agrupadas justo después de Actividad (orden de negocio, default global SCRUM):
        // Creada → Fecha Inicio → Fecha Límite → Stage → Producción.
        ['key' => 'created_at',         'name' => 'Fecha Creación',        'type' => 'date',   'visible_by_default' => true],
        ['key' => 'start_date',         'name' => 'Fecha Inicio',          'type' => 'date',   'visible_by_default' => true],
        ['key' => 'fecha_limite',       'name' => 'Fecha Límite',          'type' => 'date',   'visible_by_default' => true],
        ['key' => 'fecha_entrega',      'name' => 'Fecha Subida Stage',    'type' => 'date',   'visible_by_default' => true],
        ['key' => 'fecha_aprobacion',   'name' => 'Fecha Subida Producción','type' => 'date',  'visible_by_default' => true],
        // Resto de columnas
        ['key' => 'status',             'name' => 'Estado',                'type' => 'select', 'visible_by_default' => true],
        ['key' => 'assignee',           'name' => 'Persona Asignada',      'type' => 'user',   'visible_by_default' => true],
        ['key' => 'creator',            'name' => 'Creador',               'type' => 'user',   'visible_by_default' => true],
        ['key' => 'solicitado_por',     'name' => 'Solicitado Por',        'type' => 'user',   'visible_by_default' => true],
        // Catálogo por espacio que crece solo, igual que las etiquetas (ver GpCategoria).
        ['key' => 'categoria',          'name' => 'Categoría',             'type' => 'text',   'visible_by_default' => true],
        ['key' => 'equipo',             'name' => 'Equipo',                'type' => 'select', 'visible_by_default' => true],
        ['key' => 'priority',           'name' => 'Prioridad',             'type' => 'select', 'visible_by_default' => true],
        ['key' => 'labels',             'name' => 'Etiquetas',             'type' => 'tags',   'visible_by_default' => true],
        ['key' => 'impacto',            'name' => 'Impacto',               'type' => 'tags',   'visible_by_default' => true],
        ['key' => 'dias_estimados',     'name' => 'Días Estimados',        'type' => 'number', 'visible_by_default' => true],
        ['key' => 'aprobado_por',       'name' => 'Aprobado Por',          'type' => 'user',   'visible_by_default' => true],
        ['key' => 'validado_por',       'name' => 'Validado Por',          'type' => 'user',   'visible_by_default' => true],
        ['key' => 'updated_at',         'name' => 'Fecha Actualización',   'type' => 'date',   'visible_by_default' => true],
        ['key' => 'description',        'name' => 'Descripción',           'type' => 'text',   'visible_by_default' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.1 del servidor MCP
    |--------------------------------------------------------------------------
    | Authorization server propio (sin Passport ni dependencias nuevas). El issuer
    | es APP_URL. Ver docs/PLAN-OAUTH-MCP.md.
    */
    'oauth' => [
        // URL canónica del MCP. NO deriva del Host de la petición (evitaría
        // confusión de audiencia por IP/otro host). Se toma de GP_OAUTH_ISSUER y,
        // si no está definida, de APP_URL. En producción conviene fijarla
        // explícitamente en el .env.
        'issuer' => env('GP_OAUTH_ISSUER', env('APP_URL', 'http://localhost')),

        // Path del MCP bajo el dominio. El "resource" canónico se arma con url() y
        // debe coincidir CARÁCTER POR CARÁCTER con la URL que el usuario pega en
        // Claude — sin barra final.
        'resource_path' => 'mcp/gestion-proyectos',

        // Scope único: se traduce a abilities ['*'], que es lo que ya aceptan los
        // middlewares de la API y del MCP. offline_access se declara para
        // que el cliente pida refresh token.
        'scope'            => 'mcp',
        'scopes_supported' => ['mcp', 'offline_access'],

        // Abilities que recibe un token emitido por OAuth. Los permisos reales los
        // sigue resolviendo GpSpaceMember por espacio; esto solo abre el ámbito.
        'abilities' => ['*'],
    ],
];
