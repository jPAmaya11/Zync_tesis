import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * useIssueFilters — Gestiona todos los filtros del módulo de Gestión de Proyectos.
 * Abstrae la lógica de filtrado local (client-side) y el disparo de requests
 * al servidor (via Inertia router.get).
 *
 * @param {object} props     — Las props reactivas del componente padre
 * @param {object} prefs     — Preferencias cargadas desde localStorage
 * @param {function} savePrefs — Función para persistir prefs en localStorage
 */
export function useIssueFilters(props, prefs, savePrefs) {
    // ─── Formulario de filtros API (dispara requests al servidor) ─────────────
    const form = reactive({
        project:
            props.filters?.project ||
            prefs.project ||
            props.projects?.[0]?.key ||
            '',
        statuses:   [...(props.filters?.statuses  || prefs.statuses  || [])],
        priorities: [...(props.filters?.priorities || prefs.priorities || [])],
        labels:     [...(props.filters?.labels     || prefs.labels     || [])],
        search:     props.filters?.search || '',
        group_by:   props.filters?.group_by  || prefs.group_by  || '',
        order_by:   props.filters?.order_by  || prefs.order_by  || 'created DESC',
    });

    // Persiste cambios de configuración (no search) en localStorage
    watch(
        () => [form.project, form.group_by, form.order_by, form.statuses, form.priorities, form.labels],
        () => {
            savePrefs({
                project:    form.project,
                group_by:   form.group_by,
                order_by:   form.order_by,
                statuses:   form.statuses,
                priorities: form.priorities,
                labels:     form.labels,
            });
        },
        { deep: true }
    );

    // ─── Filtros locales (sin llamar API) ──────────────────────────────────────
    const localFilter = reactive({
        status:   '',
        assignee: '',
        priority: '',
        type:     '',
        impacto:  '', // nivel de impacto (el issue.impacto es un array; coincide si lo contiene)
        reporter: '',
        creator:  '',
        dateFrom: '', // rango de fecha: desde (compara con created_at_raw)
        dateTo:   '', // rango de fecha: hasta (compara con fecha_limite)
    });

    // Flags de dimensiones activas (estilo Jira)
    const filtersPrincipal = reactive({
        assignee: false,
        type:     false,
        labels:   false,
        status:   false,
        priority: false,
        reporter: false,
        creator:  false,
        date:     false,
    });

    const filtersSolicitado = reactive({
        tecnico: false,
        credito: false,
    });

    // ─── Estado de paginación ──────────────────────────────────────────────────
    const allIssues       = ref([...(props.issues || [])]);
    const nextPageToken   = ref(props.meta?.next_page_token ?? null);
    const isLast          = ref(props.meta?.is_last ?? true);
    const totalCount      = ref(props.meta?.total ?? 0);
    const isAppending     = ref(false);
    const tableLoading    = ref(false);
    const loadMoreLoading = ref(false);

    // Cuando el backend devuelve nuevos datos, acumular o resetear
    watch(
        () => props.issues,
        (fresh) => {
            if (isAppending.value) {
                allIssues.value = [...allIssues.value, ...fresh];
            } else {
                allIssues.value = [...fresh];
            }
            nextPageToken.value = props.meta?.next_page_token ?? null;
            isLast.value        = props.meta?.is_last ?? true;
            totalCount.value    = props.meta?.total ?? 0;
            isAppending.value   = false;
        }
    );

    // ─── Computed: filtrado local ──────────────────────────────────────────────
    const filteredIssues = computed(() => {
        let list = allIssues.value;

        if (form.search) {
            const q = form.search.toLowerCase();
            list = list.filter(
                (i) =>
                    i.summary?.toLowerCase().includes(q) ||
                    i.key?.toLowerCase().includes(q)
            );
        }

        if (localFilter.status)
            list = list.filter((i) => i.status?.name === localFilter.status);
        if (localFilter.assignee)
            list = list.filter((i) => i.assignee?.display_name === localFilter.assignee);
        if (localFilter.priority)
            list = list.filter((i) => i.priority?.name === localFilter.priority);
        if (localFilter.type)
            list = list.filter((i) => i.issue_type?.name === localFilter.type);
        if (localFilter.impacto)
            list = list.filter((i) => Array.isArray(i.impacto) && i.impacto.includes(localFilter.impacto));
        if (localFilter.reporter)
            list = list.filter((i) => i.reporter?.display_name === localFilter.reporter);
        if (localFilter.creator)
            list = list.filter((i) => i.creator?.display_name === localFilter.creator);
        // Rango de fecha: "Desde" compara con la fecha de creación; "Hasta" con la fecha límite.
        // Las fechas vienen en ISO (YYYY-MM-DD), comparables como string.
        if (localFilter.dateFrom)
            list = list.filter((i) => i.created_at_raw && i.created_at_raw >= localFilter.dateFrom);
        if (localFilter.dateTo)
            list = list.filter((i) => i.fecha_limite && i.fecha_limite <= localFilter.dateTo);

        return list;
    });

    // ─── Opciones únicas derivadas de los datos ────────────────────────────────
    const localStatusOptions = computed(() =>
        [...new Set(allIssues.value.map((i) => i.status?.name).filter(Boolean))].sort()
    );

    const localAssigneeOptions = computed(() =>
        [...new Set(allIssues.value.map((i) => i.assignee?.display_name).filter(Boolean))].sort()
    );

    const localCreatorOptions = computed(() =>
        [...new Set(allIssues.value.map((i) => i.creator?.display_name).filter(Boolean))].sort()
    );

    const uniqueUsers = computed(() => {
        const users = new Map();
        allIssues.value.forEach((i) => {
            if (i.assignee?.account_id) users.set(i.assignee.account_id, i.assignee);
        });
        return Array.from(users.values());
    });

    const uniqueReporters = computed(() => {
        const reporters = new Map();
        allIssues.value.forEach((i) => {
            if (i.reporter?.account_id) reporters.set(i.reporter.account_id, i.reporter);
        });
        return Array.from(reporters.values());
    });

    const uniqueCreators = computed(() => {
        const creators = new Map();
        allIssues.value.forEach((i) => {
            if (i.creator?.account_id) creators.set(i.creator.account_id, i.creator);
        });
        return Array.from(creators.values());
    });

    const uniqueTypes = computed(() => {
        const types = new Map();
        allIssues.value.forEach((i) => {
            if (i.issue_type?.name) types.set(i.issue_type.name, i.issue_type);
        });
        return Array.from(types.values());
    });

    const uniquePriorities = computed(() => {
        const priorities = new Map();
        allIssues.value.forEach((i) => {
            if (i.priority?.name) priorities.set(i.priority.name, i.priority);
        });
        const order = ['Highest', 'High', 'Medium', 'Low', 'Lowest'];
        return Array.from(priorities.values()).sort((a, b) => {
            let idxA = order.indexOf(a.name); if (idxA === -1) idxA = 99;
            let idxB = order.indexOf(b.name); if (idxB === -1) idxB = 99;
            return idxA - idxB;
        });
    });

    const hasReporters = computed(() => uniqueReporters.value.length > 0);
    const hasCreators  = computed(() => uniqueCreators.value.length > 0);

    // ─── Navegación / requests al servidor ────────────────────────────────────
    function navigateFilters(append = false) {
        isAppending.value  = append;
        tableLoading.value = true;
        router.get(
            route('gestion-proyectos.index'),
            {
                project:    form.project,
                statuses:   form.statuses,
                priorities: form.priorities,
                labels:     form.labels,
                search:     form.search,
                group_by:   form.group_by,
                order_by:   form.order_by,
            },
            {
                preserveState:  true,
                preserveScroll: true,
                onFinish: () => { tableLoading.value = false; },
            }
        );
    }

    function loadMore() {
        if (!nextPageToken.value || loadMoreLoading.value) return;
        isAppending.value     = true;
        loadMoreLoading.value = true;
        router.get(
            route('gestion-proyectos.index'),
            {
                project:          form.project,
                statuses:         form.statuses,
                priorities:       form.priorities,
                labels:           form.labels,
                search:           form.search,
                group_by:         form.group_by,
                order_by:         form.order_by,
                next_page_token:  nextPageToken.value,
            },
            {
                preserveState:  true,
                preserveScroll: true,
                onFinish: () => { loadMoreLoading.value = false; },
            }
        );
    }

    function clearFilters() {
        form.statuses   = [];
        form.priorities = [];
        form.labels     = [];
        form.search     = '';
        form.group_by   = '';
        localFilter.status   = '';
        localFilter.assignee = '';
        localFilter.priority = '';
        localFilter.type     = '';
        localFilter.impacto  = '';
        localFilter.reporter = '';
        localFilter.creator  = '';
        localFilter.dateFrom = '';
        localFilter.dateTo   = '';
        Object.keys(filtersPrincipal).forEach((k)  => (filtersPrincipal[k]  = false));
        Object.keys(filtersSolicitado).forEach((k) => (filtersSolicitado[k] = false));
        navigateFilters(false);
    }

    // ─── Grouping ─────────────────────────────────────────────────────────────
    const groupedIssues = computed(() => {
        if (!form.group_by)
            return [{ key: '__all__', label: null, items: filteredIssues.value }];

        const buckets = new Map();
        for (const issue of filteredIssues.value) {
            const raw = resolveGroupValue(issue, form.group_by);
            const values = Array.isArray(raw) && raw.length ? raw : [raw];
            for (const v of values) {
                const label = formatGroupLabel(v);
                if (!buckets.has(label)) buckets.set(label, []);
                buckets.get(label).push(issue);
            }
        }
        return Array.from(buckets.entries())
            .map(([label, items]) => ({ key: label, label, items }))
            .sort((a, b) => a.label.localeCompare(b.label));
    });

    function resolveGroupValue(issue, key) {
        switch (key) {
            case 'status':     return issue.status?.name          ?? 'Sin estado';
            case 'assignee':   return issue.assignee?.display_name ?? 'Sin asignar';
            case 'priority':   return issue.priority?.name        ?? 'Sin prioridad';
            case 'issue_type': return issue.issue_type?.name      ?? 'Sin tipo';
            case 'labels':     return issue.labels?.length ? issue.labels : 'Sin etiquetas';
            case 'entorno':    return issue.entorno || 'Sin entorno';
            case 'impacto':    return issue.impacto?.length ? issue.impacto : 'Sin impacto';
            default:           return issue[key]                  ?? 'Sin valor';
        }
    }

    function formatGroupLabel(value) {
        if (value === null || value === undefined || value === '') return 'Sin valor';
        if (typeof value === 'object') return JSON.stringify(value);
        return String(value);
    }

    function toggleArrayValue(arr, value) {
        const idx = arr.indexOf(value);
        if (idx === -1) arr.push(value);
        else arr.splice(idx, 1);
    }

    return {
        // Form / filters state
        form,
        localFilter,
        filtersPrincipal,
        filtersSolicitado,
        // Issues & pagination
        allIssues,
        filteredIssues,
        groupedIssues,
        nextPageToken,
        isLast,
        totalCount,
        tableLoading,
        loadMoreLoading,
        isAppending,
        // Options
        localStatusOptions,
        localAssigneeOptions,
        localCreatorOptions,
        uniqueUsers,
        uniqueReporters,
        uniqueCreators,
        uniqueTypes,
        uniquePriorities,
        hasReporters,
        hasCreators,
        // Actions
        navigateFilters,
        loadMore,
        clearFilters,
        toggleArrayValue,
    };
}
