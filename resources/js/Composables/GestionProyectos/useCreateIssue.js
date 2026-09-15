import { ref, computed, watch, nextTick } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

/**
 * @param {() => string}        getProjectKey   — Getter para la clave del proyecto activo
 * @param {() => Array|null}    getSpaceLabels  — Getter opcional para labels del espacio (desde props)
 */
export function useCreateIssue(getProjectKey, getSpaceLabels = null, callbacks = {}) {
    const page = usePage();

    // Si está seteado, el modal de crear opera en modo "subactividad" de esta key.
    const createParentKey = ref(null);
    // Fecha de inicio del padre (para el constraint min en el modal de subactividad).
    const createParentStartDate = ref(null);

    function currentKey() {
        return typeof getProjectKey === 'function' ? getProjectKey() : getProjectKey;
    }

    const createForm = useForm({
        project_key: currentKey(),
        summary: '',
        issue_type: '',
        issue_type_id: '',
        priority: 'Media',
        description: '',
        assignee_account_id: '',
        reporter_account_id: '',
        fecha_limite: '',
        start_date: '',
        solicitado_por: '',
        // Categoría de la actividad (opcional). Debe estar declarada aquí: useForm solo
        // envía las claves declaradas en el form.
        categoria: '',
        team_id: '',
        software: '',
        entorno: '',
        impacto: [],
        dias_estimados: '',
        // fecha_entrega / fecha_aprobacion ya no se cargan en creación: se auto-asignan
        // con el estado (En Revisión → Stage, Finalizado → Producción).
        labels: [],
        status: '',
        _assignee_display: '',
        _reporter_display: '',
        _reporter_avatar: '',
    });

    const showCreateModal = ref(false);
    const showPreview = ref(false);
    const createIssueTypes = ref([]);
    const createTypesLoading = ref(false);
    const showTypeDropdown = ref(false);
    const showProjectDropdown = ref(false);
    const showPriorityDropdown = ref(false);
    const showStatusDropdown = ref(false);
    const jiraPriorities = ref([]);
    const createStatuses = ref([]);
    const createStatusesLoading = ref(false);
    const createLabels = ref([]);
    const createLabelsLoading = ref(false);
    const createAssignees = ref([]);
    const createAssigneesLoading = ref(false);
    const assigneeSuggestions = ref([]);
    const assigneeLoading = ref(false);
    // Guard anti doble-envío (cubre tanto actividad como subactividad).
    const isSubmitting = ref(false);
    const reporterSuggestions = ref([]);
    const reporterLoading = ref(false);

    let assigneeTimer = null;
    let reporterTimer = null;

    const createError = computed(() => page.props.errors?.jira_create ?? null);
    const previewReady = computed(() => createForm.summary.trim().length > 0);

    async function loadIssueTypes(projectKey = '') {
        createTypesLoading.value = true;
        try {
            const url = route('gestion-proyectos.issue-types') + (projectKey ? '?project=' + encodeURIComponent(projectKey) : '');
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (resp.ok) {
                const data = await resp.json();
                createIssueTypes.value = data;
                if (data.length && !createForm.issue_type) {
                    createForm.issue_type = data[0].name;
                    createForm.issue_type_id = data[0].id ?? '';
                }
            }
        } catch {}
        createTypesLoading.value = false;
    }

    async function loadAssignableUsers(projectKey, query = '') {
        createAssigneesLoading.value = true;
        try {
            const url = route('gestion-proyectos.assignable-users') + '?project=' + encodeURIComponent(projectKey) + (query ? '&q=' + encodeURIComponent(query) : '');
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (resp.ok) createAssignees.value = await resp.json();
        } catch {}
        createAssigneesLoading.value = false;
    }

    async function loadJiraPriorities() {
        try {
            const resp = await fetch(route('gestion-proyectos.priorities'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (resp.ok) jiraPriorities.value = await resp.json();
        } catch {}
    }

    async function loadStatuses(projectKey = '') {
        if (!projectKey) return;
        createStatusesLoading.value = true;
        try {
            const url = route('gestion-proyectos.statuses') + '?project=' + encodeURIComponent(projectKey);
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (resp.ok) createStatuses.value = await resp.json();
        } catch {}
        createStatusesLoading.value = false;
    }

    async function loadLabels(projectKey = '') {
        createLabelsLoading.value = true;
        try {
            // Debe ir SIEMPRE con ?project= para traer las etiquetas del espacio (gp_labels).
            // Sin el parámetro, el endpoint cae al listado global viejo (labels embebidas en
            // los issues) y sobrescribe las etiquetas correctas del espacio.
            const url = route('gestion-proyectos.labels') + (projectKey ? '?project=' + encodeURIComponent(projectKey) : '');
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (resp.ok) createLabels.value = await resp.json();
        } catch {}
        createLabelsLoading.value = false;
    }

    // Inicializar labels desde el espacio si se provee getter
    if (getSpaceLabels) {
        const initial = getSpaceLabels();
        if (Array.isArray(initial) && initial.length) {
            createLabels.value = initial.map((l) => (typeof l === 'string' ? l : l.name));
        }
        // Mantener sincronizado cuando cambien las labels del espacio
        watch(
            () => (typeof getSpaceLabels === 'function' ? getSpaceLabels() : []),
            (fresh) => {
                if (Array.isArray(fresh)) {
                    createLabels.value = fresh.map((l) => (typeof l === 'string' ? l : l.name));
                }
            },
            { deep: true }
        );
    }

    function openCreateModal(opts = {}) {
        const projectKey = currentKey();
        createForm.reset();
        createParentKey.value = opts.parentKey ?? null;
        // Guardar la start_date del padre para usarla como min en el modal.
        createParentStartDate.value = opts.parentStartDate ?? null;
        createForm.project_key = projectKey;
        createForm.reporter_account_id = page.props.auth?.user?.jira_account_id ?? '';
        createForm._reporter_display = page.props.auth?.user?.name ?? '';
        createForm._reporter_avatar = page.props.auth?.user?.avatar_url ?? '';
        // Fecha Inicio: si es subactividad, nunca puede ser anterior al padre; usamos la del padre como default.
        createForm.start_date = opts.parentStartDate ?? todayYmd();
        showPreview.value = false;
        showCreateModal.value = true;
        loadIssueTypes(projectKey);
        loadStatuses(projectKey);
        loadLabels(projectKey);
        loadAssignableUsers(projectKey);
        loadJiraPriorities();
        nextTick(() => document.getElementById('cf-summary')?.focus());
    }

    function closeCreateModal() {
        showCreateModal.value = false;
        showPreview.value = false;
        createForm.reset();
        createParentKey.value = null;
        createParentStartDate.value = null;
        assigneeSuggestions.value = [];
        createAssignees.value = [];
        createIssueTypes.value = [];
    }

    function submitCreate() {
        // Guard anti doble-envío: bloquea clicks/Enter repetidos mientras hay un POST en vuelo.
        if (isSubmitting.value || createForm.processing) return;

        // Modo subactividad: endpoint dedicado (responde JSON), el padre define el espacio.
        if (createParentKey.value) {
            const parentKey = createParentKey.value;
            isSubmitting.value = true;
            window.axios.post(route('gestion-proyectos.subactividades.store', { key: parentKey }), createForm.data())
                .then((res) => {
                    closeCreateModal();
                    callbacks.onSubactividadCreated?.(parentKey, res.data);
                    window.showToast?.('Subactividad creada.', 'success', { timer: 2000 });
                })
                .catch((e) => {
                    const errs = e.response?.data?.errors;
                    const msg = errs
                        ? Object.values(errs).flat().join(' ')
                        : (e.response?.data?.error || e.response?.data?.message || 'Error al crear la subactividad. Revisa los campos.');
                    window.showToast(msg, 'error');
                })
                .finally(() => { isSubmitting.value = false; });
            return;
        }

        isSubmitting.value = true;
        createForm.post(route('gestion-proyectos.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateModal();
                window.showToast?.('Actividad creada exitosamente.', 'success', { timer: 2500 });
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] ?? 'Error al crear la actividad. Revisa los campos.';
                window.showToast(msg, 'error');
            },
            onFinish: () => { isSubmitting.value = false; },
        });
    }

    function onAssigneeInput(e) {
        const q = e.target.value;
        createForm._assignee_display = q;
        createForm.assignee_account_id = '';
        clearTimeout(assigneeTimer);
        if (q.length < 1) {
            assigneeSuggestions.value = createAssignees.value;
            return;
        }
        assigneeTimer = setTimeout(async () => {
            assigneeLoading.value = true;
            await loadAssignableUsers(createForm.project_key, q);
            assigneeSuggestions.value = createAssignees.value;
            assigneeLoading.value = false;
        }, 300);
    }

    function onReporterInput(e) {
        const q = e.target.value;
        createForm._reporter_display = q;
        createForm.reporter_account_id = '';
        createForm._reporter_avatar = '';
        clearTimeout(reporterTimer);
        if (q.length < 1) {
            reporterSuggestions.value = createAssignees.value;
            return;
        }
        reporterTimer = setTimeout(async () => {
            reporterLoading.value = true;
            await loadAssignableUsers(createForm.project_key, q);
            reporterSuggestions.value = createAssignees.value;
            reporterLoading.value = false;
        }, 300);
    }

    function selectAssignee(user) {
        createForm.assignee_account_id = user.account_id;
        createForm._assignee_display = user.display_name;
        assigneeSuggestions.value = [];
        // XOR: asignar usuario descarta el equipo.
        createForm.team_id = '';
    }

    // XOR inverso: elegir equipo descarta el usuario asignado.
    watch(() => createForm.team_id, (val) => {
        if (val) {
            createForm.assignee_account_id = '';
            createForm._assignee_display = '';
            assigneeSuggestions.value = [];
        }
    });

    // ─── Coherencia Días Estimados ↔ Fecha Límite (días calendario) ────────────
    // Helpers sin dependencia de TZ: parsean/serializan 'YYYY-MM-DD' como fecha local.
    function parseYmd(s) {
        if (!s || typeof s !== 'string') return null;
        const [y, m, d] = s.split('-').map(Number);
        if (!y || !m || !d) return null;
        return new Date(y, m - 1, d);
    }
    function toYmd(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    function addDays(ymd, n) {
        const base = parseYmd(ymd);
        if (!base) return '';
        base.setDate(base.getDate() + Number(n));
        return toYmd(base);
    }
    function diffDays(startYmd, endYmd) {
        const a = parseYmd(startYmd);
        const b = parseYmd(endYmd);
        if (!a || !b) return null;
        return Math.round((b - a) / 86400000);
    }
    function todayYmd() {
        return toYmd(new Date());
    }

    // Watchers reactivos: cualquier cambio (escribir días, elegir fecha, cambiar inicio)
    // re-deriva el otro campo en vivo. El flag evita el bucle entre watchers.
    // Si falta Fecha Inicio, se ancla a HOY para que la sincronización siempre sea visible.
    let syncingDates = false;

    watch(() => createForm.dias_estimados, (val) => {
        if (syncingDates) return;
        const n = parseInt(val);
        if (!Number.isInteger(n) || n < 0) return;
        syncingDates = true;
        if (!createForm.start_date) createForm.start_date = todayYmd();
        createForm.fecha_limite = addDays(createForm.start_date, n);
        nextTick(() => { syncingDates = false; });
    });

    watch(() => createForm.fecha_limite, (val) => {
        if (syncingDates || !val) return;
        syncingDates = true;
        if (!createForm.start_date) createForm.start_date = todayYmd();
        const d = diffDays(createForm.start_date, val);
        if (d !== null && d >= 0) createForm.dias_estimados = d;
        // La Fecha Límite es solo un margen estimado: no se borran Stage/Producción si la superan.
        nextTick(() => { syncingDates = false; });
    });

    watch(() => createForm.start_date, (val) => {
        if (syncingDates || !val) return;
        const n = parseInt(createForm.dias_estimados);
        if (Number.isInteger(n) && n >= 0) {
            syncingDates = true;
            createForm.fecha_limite = addDays(val, n);
            nextTick(() => { syncingDates = false; });
        } else if (createForm.fecha_limite) {
            const d = diffDays(val, createForm.fecha_limite);
            if (d !== null && d >= 0) {
                syncingDates = true;
                createForm.dias_estimados = d;
                nextTick(() => { syncingDates = false; });
            }
        }
    });

    function selectReporter(user) {
        createForm.reporter_account_id = user.account_id;
        createForm._reporter_display = user.display_name;
        createForm._reporter_avatar = user.avatar_url ?? '';
        reporterSuggestions.value = [];
    }

    function onAssigneeFocus() {
        if (createAssignees.value.length) {
            assigneeSuggestions.value = createAssignees.value;
        }
    }

    function onReporterFocus() {
        if (createAssignees.value.length) {
            reporterSuggestions.value = createAssignees.value;
        }
    }

    watch(() => createForm.project_key, (newKey) => {
        if (!newKey) return;
        createForm.assignee_account_id = '';
        createForm._assignee_display = '';
        createForm.labels = [];
        createForm.status = '';
        loadIssueTypes(newKey);
        loadStatuses(newKey);
        loadAssignableUsers(newKey);
        loadLabels(newKey);
    });

    watch(showCreateModal, (val) => {
        document.body.style.overflow = val ? 'hidden' : '';
    });

    return {
        createForm,
        createParentKey,
        createParentStartDate,
        isSubmitting,
        showCreateModal,
        showPreview,
        createIssueTypes,
        createTypesLoading,
        showTypeDropdown,
        showProjectDropdown,
        showPriorityDropdown,
        showStatusDropdown,
        jiraPriorities,
        createStatuses,
        createStatusesLoading,
        createLabels,
        createLabelsLoading,
        createAssignees,
        createAssigneesLoading,
        assigneeSuggestions,
        assigneeLoading,
        reporterSuggestions,
        reporterLoading,
        createError,
        previewReady,
        loadAssignableUsers,
        loadJiraPriorities,
        openCreateModal,
        closeCreateModal,
        submitCreate,
        onAssigneeInput,
        onAssigneeFocus,
        onReporterInput,
        onReporterFocus,
        selectAssignee,
        selectReporter,
    };
}
