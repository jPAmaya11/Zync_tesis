<script setup>
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import Swal from 'sweetalert2';
import SmartSelect from '@/Components/Common/SmartSelect.vue';
import Switchtoggle from '@/Components/Inputs/Switchtoggle.vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import AddColumnModal from '@/Components/Modules/GestionProyectos/AddColumnModal.vue';
import BulkActionsBar from '@/Components/Modules/GestionProyectos/BulkActionsBar.vue';
import BulkDeleteModal from '@/Components/Modules/GestionProyectos/BulkDeleteModal.vue';
import BulkEditModal from '@/Components/Modules/GestionProyectos/BulkEditModal.vue';
import ColumnConfigPanel from '@/Components/Modules/GestionProyectos/ColumnConfigPanel.vue';
import IssueFiltersDropdown from '@/Components/Modules/GestionProyectos/IssueFiltersDropdown.vue';
import ProjectEmptyState from '@/Components/Modules/GestionProyectos/ProjectEmptyState.vue';
import ProjectFormModal from '@/Components/Modules/GestionProyectos/ProjectFormModal.vue';
import ProjectSwitcher from '@/Components/Modules/GestionProyectos/ProjectSwitcher.vue';
import ProjectSidebar from '@/Components/Modules/GestionProyectos/ProjectSidebar.vue';
import ActivityHistoryModal from '@/Components/Modules/GestionProyectos/ActivityHistoryModal.vue';
import SummaryModal from '@/Components/Modules/GestionProyectos/SummaryModal.vue';
import SpaceMembersModal from '@/Components/Modules/GestionProyectos/SpaceMembersModal.vue';
import SubTaskModal from '@/Components/Modules/GestionProyectos/SubTaskModal.vue';
import TeamsManagementModal from '@/Components/Modules/GestionProyectos/TeamsManagementModal.vue';
import LabelsManagementModal from '@/Components/Modules/GestionProyectos/LabelsManagementModal.vue';
import TimelineModal from '@/Components/Modules/GestionProyectos/TimelineModal.vue';
import Dropdown from '@/Components/Navigation/Dropdown.vue';
import OperacionesButton from '@/Components/Buttons/OperacionesButton.vue';
import McpSessionsModal from '@/Components/Modules/GestionProyectos/McpSessionsModal.vue';
import { useColumnPreferences } from '@/Composables/GestionProyectos/useColumnPreferences';
import { useIssueFilters } from '@/Composables/GestionProyectos/useIssueFilters';
import { useBulkActions } from '@/Composables/GestionProyectos/useBulkActions';
import { useInlineEdit } from '@/Composables/GestionProyectos/useInlineEdit';
import { useProjectUsers } from '@/Composables/GestionProyectos/useProjectUsers';
import { useCreateIssue } from '@/Composables/GestionProyectos/useCreateIssue';
import { getLabelColor } from '@/Composables/GestionProyectos/useLabelColors';
import { mapStatusMeta } from '@/Composables/GestionProyectos/useStatusMeta';
import axios from 'axios';
import IssueHeader from './Partials/IssueHeader.vue';
import IssueTable from './Partials/IssueTable.vue';
import CreateIssueModal from './Partials/CreateIssueModal.vue';
import InlineEditDropdowns from './Partials/InlineEditDropdowns.vue';
import ReprogramarModal from '@/Components/Modules/GestionProyectos/ReprogramarModal.vue';
import MisPendientesDrawer from '@/Components/Modules/GestionProyectos/MisPendientesDrawer.vue';
import EspacioAnalyticsDrawer from '@/Components/Modules/GestionProyectos/EspacioAnalyticsDrawer.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';

// ─────────────────────────────────────────────────────────────────────────────
// Props
// ─────────────────────────────────────────────────────────────────────────────
const props = defineProps({
    issues: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    jira_error: { type: Object, default: null },
    customFields: { type: Array, default: () => [] },
    columnPreferences: { type: Array, default: () => [] },
    catalogColumns: { type: Array, default: () => [] },
    project: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    // Props SCRUM (Amadosixteen/Scrum) — espacios, roles y equipos
    spaceMemberRole: { type: String, default: null },
    spaceAdminIds: { type: Array, default: () => [] },
    spaceTeams: { type: Array, default: () => [] },
    spaceCategories: { type: Array, default: () => [] },
    allLabels: { type: Array, default: () => [] },
    spaceLabels: { type: Array, default: () => [] },
    // Catálogo de categorías del espacio activo (crece solo, igual que las etiquetas).
    spaceCategorias: { type: Array, default: () => [] },
});

const page = usePage();

// ─────────────────────────────────────────────────────────────────────────────
// LocalStorage helper
// ─────────────────────────────────────────────────────────────────────────────
const STORAGE_KEY = `gestion-proyectos.prefs.${page.props.auth?.user?.id ?? 'guest'}`;

function loadPrefs() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
}

function savePrefs(data) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch {}
}

const prefs = loadPrefs();

// ─────────────────────────────────────────────────────────────────────────────
// Filtros, paginación e issues — extraídos a useIssueFilters
// ─────────────────────────────────────────────────────────────────────────────
const {
    form,
    localFilter,
    filtersPrincipal,
    filtersSolicitado,
    allIssues,
    filteredIssues,
    groupedIssues,
    nextPageToken,
    isLast,
    totalCount,
    tableLoading,
    loadMoreLoading,
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
    navigateFilters,
    loadMore,
    clearFilters,
    toggleArrayValue,
} = useIssueFilters(props, prefs, savePrefs);

// ─────────────────────────────────────────────────────────────────────────────
// Subactividades desplegables inline (la flecha en cada actividad padre)
// ─────────────────────────────────────────────────────────────────────────────
const expandedKeys    = reactive({}); // { [actividadKey]: true } — filas abiertas
const childrenByKey   = reactive({}); // { [actividadKey]: [subactividadDTO...] }
const childrenLoading = reactive({}); // { [actividadKey]: true } — cargando hijos

// Una key de subactividad termina en "-S<n>"; su padre es la key sin ese sufijo.
const isChildKey  = (key) => /-S\d+$/.test(key || '');
const parentKeyOf = (key) => (key || '').replace(/-S\d+$/, '');

async function fetchChildren(key) {
    childrenLoading[key] = true;
    try {
        const { data } = await axios.get(route('gestion-proyectos.subactividades.index', { key }));
        childrenByKey[key] = data;
        // Mantener el contador de la flecha al día tras crear/borrar subactividades. El padre
        // puede ser una actividad raíz, una subactividad o una -Rn → buscar en todas las cachés.
        const padre = findIssueByKey(key);
        if (padre) padre.sub_actividades_count = data.length;
    } catch {
        childrenByKey[key] = childrenByKey[key] || [];
        window.showToast?.('No se pudieron cargar las subactividades.', 'error', { timer: 2500 });
    } finally {
        childrenLoading[key] = false;
    }
}

function toggleExpand(issue) {
    const key = issue.key;
    if (expandedKeys[key]) { delete expandedKeys[key]; return; }
    expandedKeys[key] = true;
    if (!childrenByKey[key]) fetchChildren(key);
}

// Re-carga los hijos de una actividad si está abierta (tras editar/crear/tareas).
function refreshChildrenOf(key) {
    if (key && expandedKeys[key]) fetchChildren(key);
}

// ─────────────────────────────────────────────────────────────────────────────
// Reprogramaciones desplegables inline
// ─────────────────────────────────────────────────────────────────────────────
const expandedReprogKeys  = reactive({});
const reprogByKey         = reactive({});
const reprogLoading       = reactive({});

async function fetchReprogramaciones(rootKey) {
    reprogLoading[rootKey] = true;
    try {
        const { status, data } = await axios.get(
            route('gestion-proyectos.reprogramaciones.index', { key: rootKey }),
            { validateStatus: () => true }
        );

        if (status >= 200 && status < 300) {
            reprogByKey[rootKey] = data.data || [];
            const root = allIssues.value.find(i => i.key === rootKey);
            if (root) root.reprogramaciones_count = reprogByKey[rootKey].length;

            if (reprogByKey[rootKey].length === 0) {
                window.showToast?.('Esta actividad no tiene reprogramaciones aún.', 'info', { timer: 2500 });
            }
        } else {
            reprogByKey[rootKey] = reprogByKey[rootKey] || [];
            window.showToast?.(
                data?.error || data?.message || 'Error al cargar las reprogramaciones.',
                'error',
                { timer: 3000 }
            );
        }
    } catch {
        reprogByKey[rootKey] = reprogByKey[rootKey] || [];
        window.showToast?.('Error de conexión al cargar reprogramaciones.', 'error', { timer: 3000 });
    } finally {
        reprogLoading[rootKey] = false;
    }
}

function toggleExpandReprog(issue) {
    const key = issue.key;
    if (expandedReprogKeys[key]) { delete expandedReprogKeys[key]; return; }
    expandedReprogKeys[key] = true;
    if (!reprogByKey[key]) fetchReprogramaciones(key);
}

// Resuelve un issue por key en cualquiera de las cachés: lista principal (actividades
// raíz), subactividades (childrenByKey) o versiones de reprogramación (reprogByKey).
// Las subactividades y -Rn NO viven en allIssues, así que find() directo falla en ellas.
function findIssueByKey(key) {
    if (!key) return null;
    const top = allIssues.value.find((i) => i.key === key);
    if (top) return top;
    for (const list of Object.values(childrenByKey)) {
        const hit = (list || []).find((i) => i.key === key);
        if (hit) return hit;
    }
    for (const list of Object.values(reprogByKey)) {
        const hit = (list || []).find((i) => i.key === key);
        if (hit) return hit;
    }
    return null;
}

// Panel lateral "Mis pendientes". Su estado abierto/cerrado persiste en sessionStorage:
// un F5 no debe cerrar el panel si el usuario lo tenía abierto.
const showMisPendientes = ref(sessionStorage.getItem('gp-mis-pendientes-open') === '1');
watch(showMisPendientes, (v) => {
    try { sessionStorage.setItem('gp-mis-pendientes-open', v ? '1' : '0'); } catch { /* storage no disponible */ }
});
// Ref al panel "Mis pendientes" para refrescarlo tras editar una actividad inline.
const misPendientesRef = ref(null);
function refrescarMisPendientes() { misPendientesRef.value?.recargar?.(); }

// Modal de reprogramación
const showReprogramarModal = ref(false);
const reprogramarIssue     = ref(null);

function openReprogramarModal(issue) {
    reprogramarIssue.value = issue;
    // Cargar usuarios asignables del espacio para el dropdown de Persona Asignada.
    if (createAssignees.value.length === 0) loadAssignableUsers(form.project);
    showReprogramarModal.value = true;
}

// Mínimo de fecha de INICIO para el modal de reprogramación:
// - Subactividad: el inicio de su actividad PADRE (no puede iniciar antes que el padre).
// - Actividad raíz: su propio inicio original.
const reprogramMinStart = computed(() => {
    const it = reprogramarIssue.value;
    if (!it) return null;
    if (isChildKey(it.key)) {
        const padre = allIssues.value.find((i) => i.key === parentKeyOf(it.key));
        return padre?.start_date || it.start_date || null;
    }
    return it.start_date || null;
});

function onReprogramado({ issueKey, sucesor }) {
    // Marcar el original como Reprogramado en la lista local. El original puede ser una
    // actividad raíz (allIssues) o una subactividad (childrenByKey) → resolver en ambas.
    const original = findIssueByKey(issueKey);
    if (original) {
        if (original.status) original.status.name = 'Reprogramado';
        original.reprogramaciones_count = (original.reprogramaciones_count || 0) + 1;
    }
    // Añadir el sucesor al caché de reprogramaciones y expandir el nodo
    if (!reprogByKey[issueKey]) reprogByKey[issueKey] = [];
    reprogByKey[issueKey].push({ ...sucesor, _isReprog: true, _reprogRootKey: issueKey });
    expandedReprogKeys[issueKey] = true;
}

// Aplana cada grupo: tras cada actividad abierta inserta su sub-árbol expandido
// (subactividades _isChild, fila de añadir, y versiones de reprogramación _isReprog).
//
// pushBloque es RECURSIVO: una actividad raíz O una reprogramación (-Rn) pueden tener
// subactividades; cada subactividad puede reprogramarse; y "todas las -Rn" pueden a su
// vez tener subactividades. La profundidad real la acota el estado de expansión del
// usuario (datos finitos, sin ciclos); igual ponemos un tope defensivo.
function pushBloque(block, parent, underChild = false, depth = 0) {
    if (depth > 8) return;   // tope defensivo contra anidado excesivo
    const pk = parent.key;

    // Subactividades de `parent` (solo si está expandido para subactividades).
    if (expandedKeys[pk]) {
        for (const kid of (childrenByKey[pk] || [])) {
            block.push({ ...kid, _isChild: true, _parentKey: pk });
            // Las reprogramaciones de la subactividad las resuelve la recursión sobre `kid`.
            pushBloque(block, kid, true, depth + 1);
        }
        // La fila "+ Agregar subactividad" solo para quien puede escribir.
        if (canUserWrite.value) {
            block.push({ _isAddRow: true, _parentKey: pk, key: `__add__${pk}`, id: `__add__${pk}` });
        }
    }

    // Reprogramaciones de `parent` (-Rn). _underChild = el padre es una subactividad
    // (sangría más profunda, sin badge R). Cada -Rn puede tener su propio sub-árbol.
    if (expandedReprogKeys[pk]) {
        for (const ver of (reprogByKey[pk] || [])) {
            block.push({ ...ver, _isReprog: true, _underChild: underChild, _reprogRootKey: pk });
            pushBloque(block, ver, false, depth + 1);
        }
    }
}

const displayGroups = computed(() =>
    groupedIssues.value.map((group) => {
        const items = [];
        for (const issue of group.items) {
            // El padre se inserta por referencia (no clonar: la edición inline muta el objeto).
            items.push(issue);
            // Bloque de filas hijas (subactividades + fila de añadir + reprogramaciones) que,
            // junto con el padre, quedan rodeadas por un borde de grupo en la tabla.
            const block = [];
            pushBloque(block, issue);
            // _grpMember marca toda fila hija del bloque; _grpLast la última (borde inferior).
            block.forEach((row, i) => {
                items.push({ ...row, _grpMember: true, _grpLast: i === block.length - 1 });
            });
        }
        return { ...group, items };
    })
);

// ─────────────────────────────────────────────────────────────────────────────
// Preferences de columnas
// ─────────────────────────────────────────────────────────────────────────────
const {
    localColumnPrefs,
    visibleColumns,
    allColumnsSorted,
    displayColName,
    toggleColumnVisibility,
    moveColumnUp,
    moveColumnDown,
    addColumn: addLocalColumn,
} = useColumnPreferences(props, () => form.project);

const showColumnPanel = ref(false);
const showAddColumnModal = ref(false);
const columnPanelWasOpen = ref(false);

// ─────────────────────────────────────────────────────────────────────────────
// Create Issue — lógica extraída a useCreateIssue
// ─────────────────────────────────────────────────────────────────────────────
const {
    createForm,
    createParentKey,
    createParentStartDate,
    isSubmitting: isCreateSubmitting,
    showCreateModal,
    createIssueTypes,
    createTypesLoading,
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
    selectAssignee,
    onReporterInput,
    onReporterFocus,
    selectReporter,
} = useCreateIssue(() => form.project, () => props.spaceLabels, {
    // Al crear una subactividad, dejo abierta su actividad padre y recargo sus hijos.
    onSubactividadCreated: (parentKey) => {
        expandedKeys[parentKey] = true;
        fetchChildren(parentKey);
    },
});

// Abre el modal de crear en modo "subactividad" de la actividad padre indicada.
function handleCreateSubactividad(parentKey) {
    // El padre puede ser una actividad raíz, una subactividad o una reprogramación (-Rn):
    // las dos últimas no viven en allIssues, por eso resolvemos en todas las cachés.
    const parentIssue = findIssueByKey(parentKey);
    openCreateModal({ parentKey, parentStartDate: parentIssue?.start_date ?? null });
}

// ─────────────────────────────────────────────────────────────────────────────
// Equipos del espacio (SCRUM) — toda la lógica vive en <TeamsManagementModal>.
// Mantenemos una copia local sincronizada con props.spaceTeams para el selector
// "Equipo" del modal de Crear Tarea.
// ─────────────────────────────────────────────────────────────────────────────
// Permiso SCRUM: ¿puede el usuario actual aprobar (mover a Finalizado/Reprogramado)?
const canApproveInSpace = computed(
    () =>
        ['propietario', 'administrador', 'aprobador', 'implementador'].includes(
            props.spaceMemberRole
        ) ||
        page.props.auth?.user?.roles?.some(
            (r) => r === 'admin' || r.name === 'admin' || r === 'super-admin' || r.name === 'super-admin' || r === 'super_admin' || r.name === 'super_admin'
        )
);
const canUserWrite = computed(() => {
    if (props.spaceMemberRole === 'lector') return false;
    return props.canEdit;
});
const canUserManage = computed(() => {
    return props.canManage;
});
// Solo propietario del espacio o admin global puede eliminar un espacio
const canEditSpace = computed(() => {
    if (page.props.auth?.user?.roles?.some(r => r === 'admin' || r.name === 'admin' || r === 'super-admin' || r.name === 'super-admin' || r === 'super_admin' || r.name === 'super_admin')) return true;
    if (page.props.can?.['gestion-proyectos.admin']) return true;
    return props.spaceMemberRole === 'propietario';
});

// Edición inline (PATCH) de DATOS: Propietario + Administrador + Implementador
// (o admin global). Ejecutor, aprobador y lector NO editan datos inline.
const canInlineEdit = computed(() => {
    if (page.props.auth?.user?.roles?.some(r => r === 'admin' || r.name === 'admin' || r === 'super-admin' || r.name === 'super-admin' || r === 'super_admin' || r.name === 'super_admin')) return true;
    if (page.props.can?.['gestion-proyectos.admin']) return true;
    return ['propietario', 'administrador', 'implementador'].includes(props.spaceMemberRole);
});

// Admin global puro (sin contar propietario de espacio). Sólo este perfil ve el panel de Mail.
const isGestionProyectosAdmin = computed(() => {
    if (page.props.auth?.user?.roles?.some(r => r === 'admin' || r.name === 'admin' || r === 'super-admin' || r.name === 'super-admin' || r === 'super_admin' || r.name === 'super_admin')) return true;
    return !!page.props.can?.['gestion-proyectos.admin'];
});

// Roles que escriben (ejecutor, aprobador, administrador, propietario) o admin global.
// Ya NO gatea "Sesiones MCP": las sesiones son propias de cada usuario y las ve cualquiera
// que llegue a esta página (la ruta exige 'gestion-proyectos.ver'). Se conserva por si otra
// opción del menú lo necesita.
const canGenerateToken = computed(() =>
    canUserWrite.value || canApproveInSpace.value || isGestionProyectosAdmin.value
);

const showTeamsModal = ref(false);
const showRolesModal = ref(false);
const showMcpSessionsModal = ref(false);
function openMcpSessionsModal() { showMcpSessionsModal.value = true; }

// Modal de descripción completa (actividad / subactividad / reprogramación -Rn).
const showDescriptionModal = ref(false);
const descriptionIssue = ref(null);
function openDescriptionModal(issue) {
    descriptionIssue.value = issue;
    showDescriptionModal.value = true;
}

// Modal de TÍTULO (summary): ver completo + editar + histórico de títulos.
// Aplica a actividad / subactividad / reprogramación (igual que la descripción).
const showSummaryModal = ref(false);
const summaryIssue = ref(null);
function openSummaryModal(issue) {
    summaryIssue.value = issue;
    showSummaryModal.value = true;
}
function onSummaryUpdated({ key, summary }) {
    // Actualiza la FUENTE (allIssues / subactividades / -Rn) para que el cambio persista
    // aunque la tabla recompute filas agrupadas (copias).
    const src = findIssueByKey(key);
    if (src) src.summary = summary;
    // Y el objeto abierto en el modal (puede ser una copia) para reflejo inmediato.
    if (summaryIssue.value && summaryIssue.value.key === key) {
        summaryIssue.value.summary = summary;
    }
}
const showLabelsModal = ref(false);

function openLabelsModal() {
    showLabelsModal.value = true;
}
function onLabelsChanged() {
    // Refresca las etiquetas del espacio (picker/filtros) sin recargar toda la página.
    router.reload({ only: ['allLabels', 'spaceLabels'] });
}
const allGlobalTeams = ref([]);
const teamsLocal = ref([...props.spaceTeams]);
// Equipos ofrecibles para ASIGNAR a una actividad: solo activos. El equipo ya
// asignado a una actividad se muestra desde sus propios datos (no de esta lista),
// así que un equipo desactivado no desaparece de las actividades que ya lo tienen.
const assignableTeams = computed(() => teamsLocal.value.filter((t) => t.is_active !== false));
const spaceMemberCandidates = ref([]);

async function loadSpaceMemberCandidates() {
    try {
        const resp = await fetch(route('gestion-proyectos.member-candidates'));
        if (resp.ok) spaceMemberCandidates.value = await resp.json();
    } catch (err) {
        console.error('[GestionProyectos] Error al cargar candidatos de espacio:', err);
    }
}

watch(
    () => props.spaceTeams,
    (fresh) => { teamsLocal.value = [...fresh]; },
    { deep: true }
);

function openTeamsModal() {
    // Aseguramos que la lista de usuarios esté cargada para el selector de miembros
    if (createAssignees.value.length === 0) loadAssignableUsers(form.project);
    loadSpaceMemberCandidates();
    showTeamsModal.value = true;
}
function onTeamsChanged(updatedTeams) { teamsLocal.value = updatedTeams; }

function openRolesModal() {
    if (teamsLocal.value.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Sin equipos vinculados',
            text: 'Aún no tienes miembros en este espacio. Primero debes asignar o crear un equipo',
            toast: true,
            position: 'top-end',
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false
        });
        return;
    }
    loadSpaceMemberCandidates();
    showRolesModal.value = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// Timeline (audit log) y Activity History (comentarios) — lógica en componentes
// ─────────────────────────────────────────────────────────────────────────────
const showTimelineModal = ref(false);
const timelineKey = ref(null);

function openTimelineModal(key) {
    timelineKey.value = key;
    showTimelineModal.value = true;
}

const showActivityHistoryModal = ref(false);
const activityHistoryKey = ref(null);
const activityHistoryPendingField = ref(null);
const activityHistoryPendingValue = ref(null);

// ─── Sub Tareas Modal ─────────────────────────────────────────────────────────
const showSubTaskModal   = ref(false);
const subTaskIssueKey    = ref(null);
const subTaskSummary     = ref('');

function openSubTaskModal(issue) {
    subTaskIssueKey.value = issue.key;
    subTaskSummary.value  = issue.summary ?? '';
    if (createAssignees.value.length === 0) loadAssignableUsers(form.project);
    showSubTaskModal.value = true;
}

function openActivityHistoryModal(key, pendingField = null, pendingValue = null) {
    activityHistoryKey.value = key;
    activityHistoryPendingField.value = pendingField;
    activityHistoryPendingValue.value = pendingValue;
    showActivityHistoryModal.value = true;
}

/**
 * Cuando el usuario añade una entrada con cambio pendiente (Finalizado, etc.),
 * el modal emite `apply-pending` con {key, field, value}. Acá reintentamos
 * el PATCH de inline edit con el valor pendiente.
 */
function handleActivityHistoryError(msg) {
    window.showToast?.(msg, 'error') ?? console.error('[ActivityHistory]', msg);
}

async function retryInlineEditAfterHistory({ key, field, value, extras }) {
    inlineLoading.value = true;
    const payload = { project_key: form.project };
    if (field === 'status') payload.status = value;
    else payload[field] = value;

    // Reprogramado pasa además la nueva fecha de vencimiento en fecha_reprogramacion.
    if (extras && typeof extras === 'object') {
        Object.assign(payload, extras);
    }

    try {
        const { status: httpStatus, data } = await axios.patch(
            route('gestion-proyectos.update', { key }),
            payload,
            { validateStatus: () => true }
        );
        if (httpStatus >= 200 && httpStatus < 300) {
            const issue = allIssues.value.find((i) => i.key === key);
            // Actualizar el objeto status completo (nombre + color) para reflejar el cambio al instante.
            if (issue && field === 'status') issue.status = mapStatusMeta(value);
            // Fechas de etapa auto-sincronizadas (Finalizado→Producción, En Revisión→Stage).
            if (issue && data?.fecha_entrega !== undefined)    issue.fecha_entrega    = data.fecha_entrega;
            if (issue && data?.fecha_aprobacion !== undefined) issue.fecha_aprobacion = data.fecha_aprobacion;
            // "Aprobado Por" se auto-rellena al pasar a Finalizado (hook del modelo): reflejarlo sin F5.
            if (issue && data?.aprobado_por !== undefined)     issue.aprobado_por     = data.aprobado_por;
            // Badge del botón "Seguimiento": el backend devuelve el nº de cambios recalculado → sin F5.
            if (issue && data?.audit_changes_count !== undefined) issue.audit_changes_count = data.audit_changes_count;
            // Subactividades/versiones reprogramadas no viven en allIssues: refrescar sus hijos.
            if (isChildKey(key)) refreshChildrenOf(parentKeyOf(key));
            else if (reprogByKey[parentKeyOf(key)] || /-R\d+$/.test(key)) {
                const rootKey = key.replace(/-R\d+$/, '');
                if (expandedReprogKeys[rootKey]) fetchReprogramaciones(rootKey);
            }
            // Reflejar el cambio de estado en el panel "Mis pendientes" sin F5.
            refrescarMisPendientes();
            window.showToast('Estado actualizado correctamente.', 'success', { timer: 3000 });
        } else {
            window.showToast(
                data?.error || data?.message || 'Error al actualizar.',
                'error'
            );
        }
    } catch {
        window.showToast('Error de conexión.', 'error');
    } finally {
        inlineLoading.value = false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Custom Fields Metadata — Reactividad local para campos nuevos
// ─────────────────────────────────────────────────────────────────────────────
const customFieldsLocal = ref([...props.customFields]);

watch(
    () => props.customFields,
    (fresh) => {
        customFieldsLocal.value = [...fresh];
    },
    { deep: true }
);

/** Columnas del catálogo aún no añadidas localmente (para el modal Añadir Campo).
 *  displayColName: 'fecha_entrega' se exhibe como "Staging" (solo presentación). */
const availableCatalogColumns = computed(() => {
    const existingKeys = new Set(localColumnPrefs.value.map((c) => c.key));
    return props.catalogColumns.filter((c) => !existingKeys.has(c.key)).map(displayColName);
});

/** Campos personalizados del proyecto aún no añadidos (para el modal). */
const availableCustomFields = computed(() => {
    const existingKeys = new Set(localColumnPrefs.value.map((c) => c.key));
    return customFieldsLocal.value.filter((f) => !existingKeys.has(f.column_key));
});

function openAddColumnModal() {
    // Cerramos el panel mientras está el modal — evita "modal sobre modal".
    columnPanelWasOpen.value = showColumnPanel.value;
    showColumnPanel.value = false;
    showAddColumnModal.value = true;
}

function onAddColumnModalClosed() {
    showAddColumnModal.value = false;
    if (columnPanelWasOpen.value) {
        showColumnPanel.value = true;
        columnPanelWasOpen.value = false;
    }
}

/** El modal emite `added` con `{ kind, column, field }`. Aquí persistimos y notificamos. */
function onColumnAdded({ kind, column, field }) {
    // Si es un campo nuevo o existente de custom fields, asegurar que esté en customFieldsLocal
    if ((kind === 'new-field' || kind === 'existing-field') && field) {
        if (!customFieldsLocal.value.find(f => f.id === field.id)) {
            customFieldsLocal.value.push(field);
        }
    }

    addLocalColumn(column);
    const msg =
        kind === 'new-field'
            ? `Campo "${column.name}" creado y añadido.`
            : kind === 'existing-field'
              ? `Campo "${column.name}" añadido.`
              : `Columna "${column.name}" añadida.`;
    window.showToast(msg, 'success', { timer: 3000 });
}

/** Guardar valor de un campo personalizado vía PATCH */
async function saveCustomFieldValue(issueKey, colKey, value) {
    const col = localColumnPrefs.value.find((c) => c.key === colKey);
    if (!col?.field_id) {
        console.warn('[GestionProyectos] saveCustomFieldValue: columna sin field_id — recarga la página para forzar re-sincronización.', { colKey, col });
        window.showToast?.('No se pudo guardar el campo: configuración incompleta. Recarga la página.', 'warning');
        cancelEdit();
        return;
    }
    inlineLoading.value = true;
    try {
        const { status: httpStatus, data } = await axios.patch(
            route('gestion-proyectos.update', { key: issueKey }),
            { custom_field_id: col.field_id, custom_field_value: value },
            { validateStatus: () => true }
        );
        if (httpStatus >= 200 && httpStatus < 300) {
            const issue = allIssues.value.find((i) => i.key === issueKey);
            if (issue) {
                if (!issue.custom_fields) issue.custom_fields = {};
                if (!issue.custom_fields[colKey]) {
                    issue.custom_fields[colKey] = {
                        value,
                        field_id: col.field_id,
                        name: col.name,
                        type: col.type ?? 'text',
                    };
                } else {
                    issue.custom_fields[colKey].value = value;
                }
            }
            cancelEdit();

            // Si se actualizó "Estimación (Días)", recalcular Fecha Programada
            if ((col.name || '').toLowerCase().trim() === 'estimación (días)') {
                inlineLoading.value = false;
                await autoCalcFechaProgramada(issueKey);
                return;
            }
        } else {
            window.showToast(data?.error ?? 'Error al guardar el campo.', 'error');
        }
    } catch (e) {
        window.showToast('Error de conexión.', 'error');
    } finally {
        inlineLoading.value = false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Auto-cálculo de Fecha Programada = start_date + Estimación (Días)
// Se dispara al editar inline start_date o el custom field "Estimación (Días)".
// ─────────────────────────────────────────────────────────────────────────────
function findCustomColByName(name) {
    const target = name.toLowerCase().trim();
    return localColumnPrefs.value.find(
        (c) => c.field_id && (c.name || '').toLowerCase().trim() === target
    );
}

function addDaysIsoDate(isoDate, days) {
    if (!isoDate || days === null || days === undefined || days === '')
        return null;
    const n = Number(days);
    if (!Number.isFinite(n)) return null;
    // Parsear partes (YYYY-MM-DD) en UTC para evitar desfases por zona horaria
    const parts = String(isoDate).split('-');
    if (parts.length !== 3) return null;
    const [year, month, day] = parts.map(Number);
    if (!year || !month || !day) return null;
    const ms = Date.UTC(year, month - 1, day) + Math.round(n) * 86400000;
    return new Date(ms).toISOString().slice(0, 10); // YYYY-MM-DD
}

async function autoCalcFechaProgramada(issueKey) {
    const estimacionCol = findCustomColByName('Estimación (Días)');
    const programadaCol = findCustomColByName('Fecha Programada');
    if (!estimacionCol || !programadaCol) return;

    const issue = allIssues.value.find((i) => i.key === issueKey);
    if (!issue) return;

    const estVal = issue.custom_fields?.[estimacionCol.key]?.value;
    const startVal = issue.start_date;
    const computed = addDaysIsoDate(startVal, estVal);
    if (!computed) return;

    const current = issue.custom_fields?.[programadaCol.key]?.value;
    if (current === computed) return;

    await saveCustomFieldValue(issueKey, programadaCol.key, computed);
}





// ─────────────────────────────────────────────────────────────────────────────
// UI state
// ─────────────────────────────────────────────────────────────────────────────
const showFilters = ref(false);
const isOpen      = ref(false);

// Estado de cada acordeón (abierto/cerrado)
const principalItems = computed(() => {
    const items = [
        {
            key: 'assignee',
            label: 'Persona asignada',
            count: localAssigneeOptions.value.length || null,
        },
        {
            key: 'type',
            label: 'Tipo de actividad',
            count: uniqueTypes.value.length || null,
        },
        { key: 'labels', label: 'Etiquetas', count: null },
        {
            key: 'status',
            label: 'Estado',
            count: localStatusOptions.value.length || null,
        },
        {
            key: 'priority',
            label: 'Prioridad',
            count: uniquePriorities.value.length || null,
        },
    ];
    if (hasReporters.value)
        items.push({
            key: 'reporter',
            label: 'Solicitado Por',
            count: uniqueReporters.value.length || null,
        });
    if (hasCreators.value)
        items.push({
            key: 'creator',
            label: 'Creador',
            count: uniqueCreators.value.length || null,
        });
    items.push({ key: 'date', label: 'Fecha', count: null });
    return items;
});

// Estilos semánticos para Issue Types
function issueTypeBadgeClass(type) {
    const name = type?.name?.toLowerCase() || '';
    if (name.includes('bug') || name.includes('error'))
        return 'bg-red-500/10 text-red-500 border-red-500/20';
    if (name.includes('task') || name.includes('tarea'))
        return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
    if (name.includes('improvement') || name.includes('mejora'))
        return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
    if (name.includes('new feature') || name.includes('funcionalidad'))
        return 'bg-purple-500/10 text-purple-500 border-purple-500/20';
    if (name.includes('epic') || name.includes('épica'))
        return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
    if (name.includes('sub-task') || name.includes('subtarea'))
        return 'bg-gray-500/10 text-gray-400 border-gray-500/20';
    return 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20';
}

function getColumnWidthClass(key) {
    const widths = {
        summary: 'min-w-[400px]',
        assignee: 'min-w-[200px]',
        reporter: 'min-w-[200px]',
        creator: 'min-w-[200px]',
        status: 'min-w-[140px]',
        issue_type: 'min-w-[120px]',
        priority: 'min-w-[120px]',
        created: 'min-w-[150px]',
        updated: 'min-w-[150px]',
        fecha_limite: 'min-w-[150px]',
        start_date: 'min-w-[150px]',
    };
    return widths[key] || 'min-w-[150px]';
}

// ─────────────────────────────────────────────────────────────────────────────
// Computed que cruzan composables (jiraPriorities + uniquePriorities/Types/Users)
// ─────────────────────────────────────────────────────────────────────────────
const selectedPriorityIcon = computed(() => {
    const name = createForm.priority;
    if (!name) return null;
    const official = jiraPriorities.value.find((p) => p.name === name);
    if (official?.iconUrl) return official.iconUrl;
    const opt = uniquePriorities.value.find((p) => p.name === name);
    return opt?.icon_url || null;
});

const selectedTypeIcon = computed(() => {
    const name = createForm.issue_type;
    if (!name) return null;
    const fromApi = createIssueTypes.value.find((t) => t.name === name);
    if (fromApi?.iconUrl) return fromApi.iconUrl;
    const fromTable = uniqueTypes.value.find((t) => t.name === name);
    return fromTable?.icon_url || null;
});

const selectedAssigneeObj = computed(() => {
    const id = createForm.assignee_account_id;
    if (!id) return null;
    return createAssignees.value.find((u) => u.account_id === id)
        || uniqueUsers.value.find((u) => u.account_id === id)
        || null;
});

const selectedReporterObj = computed(() => {
    const id = createForm.reporter_account_id;
    if (!id) return null;
    return createAssignees.value.find((u) => u.account_id === id)
        || uniqueUsers.value.find((u) => u.account_id === id)
        || null;
});



// Sidebar deslizable de espacios (empuja la tabla, no la tapa).
const showProjectSidebar = ref(false);

// Con AMBOS sidebars abiertos el espacio se comprime: el buscador se colapsa a una lupa
// y su input aparece flotante (posición absoluta) para no desordenar la toolbar.
const rawBothOpen = computed(() => showProjectSidebar.value && showMisPendientes.value);
// Estado DIFERIDO: colapsa al instante al abrir el 2º sidebar, pero al cerrar uno espera a
// que termine la animación de ancho (500ms) antes de re-expandir el input. Así el buscador
// no crece mientras el sidebar aún se está cerrando (evita el parpadeo de botones).
const bothSidebarsOpen = ref(false);
let bothCollapseTimer = null;
watch(rawBothOpen, (val) => {
    clearTimeout(bothCollapseTimer);
    if (val) {
        bothSidebarsOpen.value = true;                 // colapsar de inmediato
    } else {
        bothCollapseTimer = setTimeout(() => { bothSidebarsOpen.value = false; }, 520);
    }
});

const searchPopoverOpen = ref(false);
const searchPopoverInput = ref(null);

function toggleSearchPopover() {
    searchPopoverOpen.value = !searchPopoverOpen.value;
    if (searchPopoverOpen.value) nextTick(() => searchPopoverInput.value?.focus());
}

// Si deja de estar comprimido, ocultar el popover del buscador.
watch(bothSidebarsOpen, (val) => { if (!val) searchPopoverOpen.value = false; });

// ─── Enfoque de un issue desde "Mis pendientes" (scroll suave + flash índigo) ──
function flashRow(key) {
    const sel = window.CSS?.escape ? CSS.escape(key) : key;
    const row = document.querySelector(`tr[data-issue-key="${sel}"]`);
    if (!row) return false;
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    row.classList.remove('gp-flash');
    void row.offsetWidth;          // reflow → permite re-disparar la animación
    row.classList.add('gp-flash');
    setTimeout(() => row.classList.remove('gp-flash'), 2000);
    return true;
}

// Expande lo necesario (padre de subactividad / reprogs del root) y enfoca la fila.
async function focusIssueByKey(key) {
    if (!key) return;
    // Subactividad "-S<n>": abrir su actividad padre.
    if (isChildKey(key)) {
        const pk = parentKeyOf(key);
        if (!expandedKeys[pk]) { expandedKeys[pk] = true; await fetchChildren(pk); }
    }
    // Reprogramación "-R<n>": abrir las reprogs de su root (y el padre del root si es hija).
    if (/-R\d+$/.test(key)) {
        const rootKey = key.replace(/-R\d+$/, '');
        if (isChildKey(rootKey)) {
            const pk = parentKeyOf(rootKey);
            if (!expandedKeys[pk]) { expandedKeys[pk] = true; await fetchChildren(pk); }
        }
        if (!expandedReprogKeys[rootKey]) { expandedReprogKeys[rootKey] = true; await fetchReprogramaciones(rootKey); }
    }
    await nextTick();
    // Tras expandir, el render puede tardar un frame: reintenta una vez. Si tampoco
    // aparece (issue eliminado, filtrado o sin acceso), avisar en vez de fallar mudo.
    if (!flashRow(key)) {
        setTimeout(() => {
            if (!flashRow(key)) {
                window.showToast?.(`No se encontró ${key} en la tabla (puede estar filtrado o eliminado).`, 'info', { timer: 3000 });
            }
        }, 320);
    }
}

// Enfoque para DEEP-LINK (?focus= en la URL): a diferencia de "Mis pendientes" —donde
// la tabla ya está renderizada— acá la página recién carga (full reload o post-login vía
// Inertia) y las filas pueden tardar en aparecer. Hacemos POLL del "ancla" (la fila que
// debe existir en la tabla base: la propia key si es top-level, o el padre si es hija)
// hasta ~6s; cuando aparece, delegamos en focusIssueByKey (expande + flashea).
async function deepLinkFocus(key) {
    if (!key) return;
    // Ancla = fila que debe estar en la tabla base para poder enfocar/expandir.
    let anchor = key;
    if (isChildKey(key)) anchor = parentKeyOf(key);
    else if (/-R\d+$/.test(key)) {
        const rootKey = key.replace(/-R\d+$/, '');
        anchor = isChildKey(rootKey) ? parentKeyOf(rootKey) : rootKey;
    }
    const sel = window.CSS?.escape ? CSS.escape(anchor) : anchor;
    const maxTries = 24;            // 24 × 250ms ≈ 6s de margen para cargas lentas
    for (let i = 0; i < maxTries; i++) {
        if (document.querySelector(`tr[data-issue-key="${sel}"]`)) {
            await focusIssueByKey(key);
            return;
        }
        await new Promise((r) => setTimeout(r, 250));
    }
    // Nunca apareció el ancla: el ítem no está en este espacio/página o no hay acceso.
    window.showToast?.(`No se encontró ${key} (puede estar en otra página, filtrado o sin acceso).`, 'info', { timer: 3500 });
}

// Click en una tarjeta de "Mis pendientes" (cross-espacio).
function onPendienteFocus(it) {
    // Las tareas (GpSubTarea) no son filas de la tabla → enfocar su issue padre.
    const targetKey = it.type === 'tarea' ? (it.parent_key || it.key) : it.key;

    // Otro espacio: el destino del enfoque viaja en sessionStorage (one-shot), NO en
    // la URL: un ?focus= en la URL se re-ejecutaba en cada F5 (Inertia restauraba el
    // param en sus partial reloads aunque se limpiara con replaceState).
    if (it.project && it.project !== form.project) {
        sessionStorage.setItem('gp-focus-key', targetKey);
        window.location.href = route('gestion-proyectos.index', { project: it.project });
        return;
    }
    // Mismo espacio: enfocar SIN cerrar el panel de pendientes (queda abierto).
    focusIssueByKey(targetKey);
}

// Copiar un enlace compartible que enfoca el ítem (deep-link). El destinatario —ya
// autenticado y con acceso al espacio— abre el link y la tabla expande/resalta la
// actividad/subactividad automáticamente. No usa tokens: la autorización del espacio
// (visibleProjectKeys) sigue aplicando.
async function copyShareLink(issue) {
    const space = issue?.project || form.project;
    // El enlace DEBE apuntar al origin donde el usuario está parado (ej. http://127.0.0.1:8000).
    // route()/Ziggy arma la URL con APP_URL (http://localhost, SIN puerto) → al pegarla caería
    // en otro server/sesión y rebotaría a /dashboard. Por eso reescribimos el origin con el real.
    let url;
    try {
        const generated = route('gestion-proyectos.index', { project: space, focus: issue.key });
        const u = new URL(generated, window.location.origin);   // tolera URL absoluta o relativa
        url = window.location.origin + u.pathname + u.search;   // fuerza host:puerto actual
    } catch {
        url = `${window.location.origin}/gestion-proyectos?project=${encodeURIComponent(space)}&focus=${encodeURIComponent(issue.key)}`;
    }

    // Copiar con fallback para contextos sin Clipboard API.
    let copied = false;
    try {
        await navigator.clipboard.writeText(url);
        copied = true;
    } catch {
        try {
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            copied = document.execCommand('copy');
            document.body.removeChild(ta);
        } catch { copied = false; }
    }

    if (copied) {
        window.showToast?.(`Enlace de ${issue.key} copiado ✓`, 'success', { timer: 2500 });
    } else {
        window.prompt('Copiá el enlace para compartir:', url);
    }
}

function switchProject(key) {
    showProjectSidebar.value = false;
    form.project    = key;
    form.statuses   = [];
    form.priorities = [];
    form.search     = '';
    form.group_by   = '';
    localFilter.status   = '';
    localFilter.assignee = '';
    localFilter.priority = '';
    navigateFilters(false);
}

function applyFilters() {
    localFilter.status   = '';
    localFilter.assignee = '';
    localFilter.priority = '';
    navigateFilters(false);
}

// ─────────────────────────────────────────────────────────────────────────────
// Agrupamiento dinámico (sobre filteredIssues)
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// Atajo de teclado: C → abrir modal crear
// ─────────────────────────────────────────────────────────────────────────────
function handleKeydown(e) {
    const tag = document.activeElement?.tagName?.toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if (e.key === 'Escape' && showCreateModal.value) closeCreateModal();
}

const handleGlobalClick = (e) => {
    if (!e.target.closest('.group-by-dropdown')) isOpen.value = false;
    if (!e.target.closest('.filter-dropdown')) showFilters.value = false;
};

// ─────────────────────────────────────────────────────────────────────────────
// Toasts via window.showToast (SweetAlert global registrado en <SweetAlert>).
// No mantenemos UI propia — el componente global ya se monta en el layout.
// ─────────────────────────────────────────────────────────────────────────────

// Watcher: flash del servidor (success / error)
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) {
            window.showToast(flash.success, 'success', { timer: 4000 });
        }
        if (flash.error) {
            window.showToast(flash.error, 'error');
        }
    },
    { deep: true }
);

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('click', handleGlobalClick);
    // Pre-cargar lista de usuarios para que los modales de proyecto
    // tengan los datos disponibles inmediatamente al abrirse.
    loadProjectUsers();

    // Llegada desde "Mis pendientes" de OTRO espacio: el destino viene en sessionStorage
    // (one-shot: se consume aquí mismo, así un F5 posterior NO repite la coreografía).
    // De forma visual, se abre el sidebar de espacios (marca el espacio recién
    // seleccionado), luego se cierra y se hace scroll suave + flash sobre el issue.
    const focusKey = sessionStorage.getItem('gp-focus-key');
    if (focusKey) {
        sessionStorage.removeItem('gp-focus-key');

        showMisPendientes.value = true;                                   // el panel de pendientes sigue abierto
        showProjectSidebar.value = true;                                  // el sidebar de espacios se desliza abierto
        setTimeout(() => { showProjectSidebar.value = false; }, 1100);    // y se cierra
        setTimeout(() => focusIssueByKey(focusKey), 1450);                // luego enfoca
    }

    // Deep-link COMPARTIBLE: ?focus=<KEY> en la URL (enlace enviado a otra persona).
    // Se lee UNA sola vez y se limpia el param (replaceState) para que un F5 no repita
    // el enfoque. sessionStorage tiene prioridad (no pisar la coreografía cross-espacio).
    if (!focusKey) {
        try {
            const _u = new URL(window.location.href);
            const _focus = _u.searchParams.get('focus');
            if (_focus) {
                _u.searchParams.delete('focus');
                window.history.replaceState({}, '', _u.pathname + _u.search + _u.hash);
                deepLinkFocus(_focus);   // poll robusto (la tabla puede tardar en render)
            }
        } catch { /* URL no disponible */ }
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('click', handleGlobalClick);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers UI
// ─────────────────────────────────────────────────────────────────────────────
const initials = (name) =>
    (name || '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();


const currentProject = computed(
    () => props.projects.find((p) => p.key === form.project) ?? null
);

// Key para el modal de equipos: solo si el espacio existe de verdad en la lista.
// Si no hay espacios (o form.project quedó residual), '' → el modal opera en modo global.
const teamsModalProjectKey = computed(
    () => (currentProject.value ? form.project : '')
);

// Switch por-espacio "Bloquear fechas anteriores": controla el piso de la Fecha de Inicio en
// los modales de crear y reprogramar. Default true (espacio sin flag → bloquea, comportamiento actual).
const bloquearFechasEspacio = computed(
    () => currentProject.value?.validar_fechas_inicio ?? true
);

const activeLocalFilters = computed(
    () =>
        (localFilter.status ? 1 : 0) +
        (localFilter.assignee ? 1 : 0) +
        (localFilter.priority ? 1 : 0) +
        (localFilter.type ? 1 : 0) +
        (localFilter.impacto ? 1 : 0) +
        (localFilter.reporter ? 1 : 0) +
        (localFilter.creator ? 1 : 0) +
        (localFilter.dateFrom || localFilter.dateTo ? 1 : 0)
);


// ─────────────────────────────────────────────────────────────────────────────
// Selección múltiple + acciones masivas — extraídos a useBulkActions
// ─────────────────────────────────────────────────────────────────────────────
const {
    selectedIssues,
    allVisibleSelected,
    someSelected,
    toggleSelectAll,
    clearSelection,
    bulkLoading,
    showEditModal,
    showDeleteModal,
    openBulkEditModal,
    openDeleteModal,
    onBulkEditCompleted,
    onBulkDeleteCompleted,
} = useBulkActions(filteredIssues, () => props.issues, applyFilters);


// ─────────────────────────────────────────────────────────────────────────────
// Inline Editing — extraído a useInlineEdit
// ─────────────────────────────────────────────────────────────────────────────
const {
    editing,
    dropdownAnchor,
    dropdownStyle,
    inlineLoading,
    assigneeSuggestions: inlineAssigneeSuggestions,
    reporterSuggestions: inlineReporterSuggestions,
    assigneeLoading: inlineAssigneeLoading,
    reporterLoading: inlineReporterLoading,
    startEdit,
    cancelEdit,
    saveInlineEdit,
    selectAssigneeInline,
    selectTeamInline,
    onAssigneeInputInline,
    selectReporterInline,
    onReporterInputInline,
} = useInlineEdit(allIssues, localColumnPrefs, canInlineEdit, form, {
    createAssignees,
    uniqueUsers,
    uniqueReporters,
    uniquePriorities,
    teamsLocal,
    jiraPriorities,
    loadAssignableUsers,
    loadJiraPriorities,
    openActivityHistoryModal,
    autoCalcFechaProgramada,
    saveCustomFieldValue,
    openReprogramarModal,
    resolveIssue: findIssueByKey,
    // El campo Estado lo pueden cambiar escritores (Ejecutor+) y aprobadores, aunque el resto
    // de la edición inline esté restringida al propietario.
    canUserWrite,
    canApprove: canApproveInSpace,
    // "Producción editable tras finalizar": flag del espacio activo. Habilita editar inline
    // fecha_aprobacion ("Producción") aun cuando la actividad está Finalizada.
    produccionEditableFinalizado: computed(() => currentProject.value?.produccion_editable_finalizado ?? false),
    // Tras guardar inline, refrescar las filas que no viven en allIssues (subactividades / -Rn)
    // para reflejar el cambio (estado, asignado, etc.) sin recargar la página.
    onAfterSave: (key) => {
        if (isChildKey(key)) {
            refreshChildrenOf(parentKeyOf(key));
        } else if (/-R\d+$/.test(key)) {
            const rootKey = key.replace(/-R\d+$/, '');
            if (expandedReprogKeys[rootKey]) fetchReprogramaciones(rootKey);
        }
        // Reflejar el cambio en el panel "Mis pendientes" sin recargar la página.
        refrescarMisPendientes();
    },
});

// Edición inline: si edité una subactividad (fila hija), recargo los hijos de su
// actividad padre para reflejar el cambio (las hijas no viven en allIssues).
async function handleSaveEdit() {
    // El refresco de hijos (subactividades / -Rn) lo hace onAfterSave dentro de saveInlineEdit.
    await saveInlineEdit();
}

// Tras tocar tareas en el modal, refresco el cumplimiento del ítem.
//  - Si es subactividad (-Sn): re-cargo los hijos del padre (allí se ve su estado).
//  - Si es actividad raíz: re-consulto sus tareas y actualizo el badge sub_task_count
//    para que el contador se refleje sin recargar la página (F5).
async function onTareasUpdated(issueKey) {
    if (isChildKey(issueKey)) {
        refreshChildrenOf(parentKeyOf(issueKey));
        return;
    }
    const issue = allIssues.value.find((i) => i.key === issueKey);
    if (!issue) return;
    try {
        const { data } = await axios.get(
            route('gestion-proyectos.subtareas.index', { key: issueKey })
        );
        issue.sub_task_count = Array.isArray(data) ? data.length : (data?.length ?? 0);
    } catch {
        // Silencioso: el contador se sincronizará en la próxima carga.
    }
}

// Eliminar una subactividad (arrastra sus tareas) desde la fila inline.
async function handleDeleteSubactividad(sub) {
    const ok = await Swal.fire({
        title: `¿Eliminar la subactividad ${sub.key}?`,
        text: 'Se eliminará junto con todas sus tareas. Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
    }).then((r) => r.isConfirmed);
    if (!ok) return;
    try {
        // bulk.delete responde 200 con { succeeded, failed } incluso si el borrado fue rechazado
        // (p.ej. sin permiso). Hay que revisar el resultado para no mostrar un falso "eliminado".
        const { data } = await axios.post(route('gestion-proyectos.bulk.delete'), { keys: [sub.key] });
        refreshChildrenOf(sub._parentKey || parentKeyOf(sub.key));
        if (data?.succeeded?.includes(sub.key)) {
            window.showToast?.('Subactividad eliminada.', 'success', { timer: 2000 });
        } else {
            const err = data?.failed?.[0]?.error || 'No tienes permiso para eliminar esta subactividad.';
            window.showToast?.(err, 'error', { timer: 3000 });
        }
    } catch {
        window.showToast?.('No se pudo eliminar la subactividad.', 'error', { timer: 2500 });
    }
}

// Helpers for InlineEditDropdowns: populate suggestions on focus
function onAssigneeFocusInline() {
    inlineAssigneeSuggestions.value = createAssignees.value;
}
function onReporterFocusInline() {
    inlineReporterSuggestions.value = createAssignees.value;
}

// ---- Cambiar estado (solo toast informativo por ahora) ----
function openChangeStatusAlert() {
    window.showToast('Cambio de estado masivo disponible próximamente.', 'info');
}
// ─────────────────────────────────────────────────────────────────────────────
// Crear / Editar / Eliminar Espacio
//   Toda la lógica vive en <ProjectFormModal>. Aquí solo orquestamos qué modal
//   está abierto y compartimos la lista de usuarios via useProjectUsers().
// ─────────────────────────────────────────────────────────────────────────────
const { userOptions: projectUserOptions, findUser: findProjectUser, loadUsers: loadProjectUsers } =
    useProjectUsers();

const showCreateProjectModal = ref(false);
const showEditProjectModal = ref(false);
const editingProject = ref(null);

function openCreateProjectModal() {
    loadProjectUsers();
    loadAllGlobalTeams();
    showCreateProjectModal.value = true;
}

function openEditProjectModal(project) {
    editingProject.value = project;
    loadProjectUsers();
    loadAllGlobalTeams();
    showEditProjectModal.value = true;
}

async function loadAllGlobalTeams() {
    try {
        const resp = await fetch(route('gestion-proyectos.teams.all'));
        if (resp.ok) allGlobalTeams.value = await resp.json();
    } catch (err) {
        console.error("Error al cargar equipos globales:", err);
    }
}

function onProjectModalSaved(key) {
    window.showToast('Espacio guardado correctamente.', 'success');
    // Al crear, el redirect ya cargó los datos del nuevo espacio (?project=key);
    // seleccionarlo aquí hace que el switcher muestre su título al instante.
    if (key) {
        form.project = key;
    } else {
        // Editar: refresca la lista de espacios por si cambió el nombre/ícono.
        router.reload({ only: ['projects'] });
    }
}

function onProjectModalDeleted() {
    editingProject.value = null;
}

function onProjectModalError(msg) {
    window.showToast(msg, 'error');
}

// Detecta si un valor de icon es URL o dataURL (imagen) vs emoji/texto.
// Se usa todavía en el toolbar/dropdown del proyecto seleccionado.
function isIconUrl(icon) {
    if (!icon) return false;
    return icon.startsWith('http') || icon.startsWith('data:image');
}

// Gestión Equipos y Correos se movieron al botón "Configuración" del header.
const scrumOperations = computed(() => [
    {
        id: 'editar',
        label: 'Editar espacio',
        icon: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10',
        variant: 'primary',
        // MISMO gate que el antiguo botón lápiz: canEditSpace (admin global o propietario).
        show: canEditSpace.value,
        disabled: false,
        action: () => openEditProjectModal(currentProject.value),
    },
    {
        id: 'roles',
        label: 'Miembros y Roles',
        icon: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
        variant: 'purple',
        show: canUserManage.value,
        disabled: false,
        action: openRolesModal,
    },
    {
        id: 'etiquetas',
        label: 'Etiquetas',
        icon: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z M6 6h.008v.008H6V6z',
        variant: 'primary',
        show: canUserManage.value,
        disabled: false,
        action: openLabelsModal,
    },
    {
        id: 'analitica',
        label: 'Analítica del espacio',
        icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        variant: 'primary',
        show: canUserManage.value,
        disabled: false,
        action: openAnalyticsDrawer,
    },
]);

// ── Drawer de Analítica del espacio (KPIs) ──────────────────────────────────
const showAnalyticsDrawer = ref(false);
function openAnalyticsDrawer() {
    showAnalyticsDrawer.value = true;
}
</script>

<template>
    <Head title="Gestión de Proyectos" />
    <!-- `gp-scope`: ancla de la capa de corrección de modo oscuro del módulo
         (resources/css/gestion-proyectos.css). Sin estilos propios en claro. -->
    <AuthenticatedLayout class="gp-scope">
        <template #header>
            <div
                class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"
            >
                <!-- Título y contador -->
                <div class="flex items-center gap-4">
                    <h2 class="text-2xl font-bold tituloPag">
                        Gestión de Proyectos
                    </h2>
          <div
                        v-if="projects.length"
                        class="hidden sm:flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700"
                    >
                        <svg
                            class="w-5 h-5 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                            />
                        </svg>
                        <span
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                            >{{ filteredIssues.length }}</span
                        >
                        <span
                            class="text-sm text-gray-500 dark:text-gray-400"
                            >{{
                                filteredIssues.length === 1
                                    ? 'issue'
                                    : 'issues'
                            }}</span
                        >
                    </div>
                </div>

                <!-- ═══ Controles del módulo (extremo derecho del header) ═══ -->
                <div class="flex items-center gap-2">
                    <!-- Mis pendientes: panel PERSONAL (cross-espacio). Gateado por el permiso global
                         'gestion-proyectos.miembro' → lo ve cualquiera que pueda ser asignado, sin
                         importar su rol en el espacio activo (lector aquí pero ejecutor en otro espacio
                         sí lo ve). Los 'ver'-sin-'miembro' no lo ven. NO depende del rol del espacio. -->
                    <button
                        v-if="$page.props.can?.['gestion-proyectos.miembro']"
                        type="button"
                        @click="showMisPendientes = !showMisPendientes"
                        title="Mis pendientes — todo lo asignado a mí en todos los espacios"
                        class="inline-flex items-center gap-2 bgPrincipal text-white px-5 py-2.5 rounded-md shadow-sm hover:bg-indigo-700 transition-colors duration-200 h-[44px] text-sm font-medium"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                        </svg>
                        <span>Mis pendientes</span>
                    </button>

                    <!-- Siempre visible: contiene "Sesiones MCP", que es de cada usuario sobre sus
                         propias conexiones, no una operación privilegiada. Quien llega a esta página
                         ya tiene 'gestion-proyectos.ver' (lo exige la ruta). El resto de opciones del
                         menú siguen gateadas una a una. -->
                    <Dropdown align="right" minWidth="220px">
                    <template #trigger>
                        <button
                            title="Gestionar (espacios, equipos y correos)"
                            class="inline-flex items-center gap-2 bgPrincipal text-white px-5 py-2.5 rounded-md shadow-sm hover:bg-indigo-700 transition-colors duration-200 h-[44px] text-sm font-medium"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.397-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Gestion General</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </template>
                    <template #content="{ close }">
                        <div class="py-1">

                            <!-- Sesiones MCP: cada quien ve y corta solo sus propias sesiones -->
                            <button
                                type="button"
                                @click="openMcpSessionsModal(); close();"
                                title="Gestiona los dispositivos conectados a tu MCP"
                                class="w-full flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-left"
                            >
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                                </svg>
                                <span class="flex-1">Sesiones MCP</span>
                            </button>

                            <!-- Gestión Equipos: admin de espacio + admin global -->
                            <button
                                v-if="canUserManage"
                                type="button"
                                @click="openTeamsModal(); close();"
                                :class="[
                                    'w-full flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-left',
                                    'border-t border-gray-100 dark:border-gray-800',
                                ]"
                            >
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                                <span class="flex-1">Gestión Equipos</span>
                            </button>

                            <!-- Correos: SOLO admin global del módulo -->
                            <button
                                v-if="isGestionProyectosAdmin"
                                type="button"
                                @click="router.visit(route('gestion-proyectos.mail.providers.index')); close();"
                                class="w-full flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-left"
                            >
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                <span class="flex-1">Gestion de Correos</span>
                            </button>

                            <!-- Crear nuevo espacio: SOLO admin global del módulo (acción destacada) -->
                            <button
                                v-if="$page.props.can?.['gestion-proyectos.admin']"
                                type="button"
                                @click="openCreateProjectModal(); close();"
                                class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-50/70 dark:bg-indigo-500/10 hover:bg-indigo-100/80 dark:hover:bg-indigo-500/20 transition-colors text-left border-t border-gray-100 dark:border-gray-800 mt-1"
                            >
                                <svg class="w-4 h-4 text-indigo-500 dark:text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                                </svg>
                                <span class="flex-1">Crear nuevo espacio</span>
                            </button>
                        </div>
                    </template>
                </Dropdown>
                </div>
            </div>
        </template>

        <!-- Contenedor flex: el panel "Mis pendientes" empuja esta caja hacia la izquierda. -->
        <div class="flex items-start mt-4">
            <div class="flex-1 min-w-0">
        <!-- Caja del contenido (título espacio + acciones + tabla). Mismo fondo/padding/radius
             que el box global anterior, pero scoped aquí para NO envolver el header de arriba. -->
        <div style="background: var(--bg-primary);" class="pt-3">
            <!-- Empty state: sin proyectos creados -->
            <ProjectEmptyState
                v-if="!projects.length"
                :can-create="!!$page.props.can?.['gestion-proyectos.admin']"
                @create="openCreateProjectModal"
                @manage-teams="openTeamsModal"
            />

            <!-- Vista normal: hay al menos un proyecto -->
            <div v-else class="flex items-start">
                <!-- Sidebar deslizable de espacios: empuja el contenido al abrirse -->
                <ProjectSidebar
                    :show="showProjectSidebar"
                    :current-key="form.project"
                    :projects="projects"
                    @switch="switchProject"
                    @close="showProjectSidebar = false"
                />

                <div class="flex-1 min-w-0 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <!-- Subtítulo con proyecto seleccionado + Selector de proyectos -->
                    <ProjectSwitcher
                        :current-project="currentProject"
                        :projects="projects"
                        :open="showProjectSidebar"
                        @toggle="showProjectSidebar = !showProjectSidebar"
                    />
                    <div class="flex items-center gap-2 flex-wrap md:ml-auto md:justify-end">
                        <!-- Search: input normal; con AMBOS sidebars abiertos colapsa a lupa
                             y su input aparece flotante (absoluto) para no desordenar la toolbar. -->
                        <div class="relative">
                            <!-- Modo normal: input completo -->
                            <template v-if="!bothSidebarsOpen">
                                <svg
                                    class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"
                                    />
                                </svg>
                                <input
                                    v-model="form.search"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    placeholder="Buscar actividad…"
                                    class="h-9 pl-8 pr-3 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-colors"
                                />
                            </template>

                            <!-- Modo colapsado: solo lupa; click abre el input flotante -->
                            <template v-else>
                                <button
                                    type="button"
                                    @click="toggleSearchPopover"
                                    title="Buscar actividad"
                                    :class="[
                                        'relative inline-flex items-center justify-center w-9 h-9 rounded-lg border transition-colors',
                                        (searchPopoverOpen || form.search)
                                            ? 'border-indigo-300 dark:border-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300'
                                            : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/60',
                                    ]"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                                    </svg>
                                    <!-- Punto: hay una búsqueda activa pero el input está cerrado -->
                                    <span v-if="form.search && !searchPopoverOpen" class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-indigo-500 ring-2 ring-white dark:ring-gray-900"></span>
                                </button>

                                <!-- Input flotante: posición absoluta, NO empuja la toolbar -->
                                <div v-if="searchPopoverOpen" class="absolute right-0 top-full mt-1.5 z-30 w-64">
                                    <div class="relative">
                                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                                        </svg>
                                        <input
                                            ref="searchPopoverInput"
                                            v-model="form.search"
                                            @keyup.enter="applyFilters(); searchPopoverOpen = false"
                                            @keyup.escape="searchPopoverOpen = false"
                                            type="text"
                                            placeholder="Buscar actividad…"
                                            class="w-full h-9 pl-8 pr-3 text-sm rounded-lg border border-indigo-300 dark:border-indigo-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                                        />
                                    </div>
                                </div>
                            </template>
                        </div>

<!-- "Sesiones MCP" vive en el dropdown "Gestion General", visible para todo usuario del modulo. -->

                        <!-- ═══ Botones GEMELOS: Filtrar · Nueva actividad · Operaciones ═══ -->
                        <IssueFiltersDropdown
                            :form="form"
                            :local-filter="localFilter"
                            :options="options"
                            :principal-items="principalItems"
                            :unique-users="uniqueUsers"
                            :unique-priorities="uniquePriorities"
                            :unique-types="uniqueTypes"
                            :unique-reporters="uniqueReporters"
                            :unique-creators="uniqueCreators"
                            :filtered-count="filteredIssues.length"
                            :total-count="totalCount"
                            :active-local-filters="activeLocalFilters"
                            :table-loading="tableLoading"
                            @apply="applyFilters"
                            @clear="clearFilters"
                        />

                        <!-- ═══ Acción PRIMARIA: Crear Actividad (gemelo de Operaciones) ═══ -->
                        <button
                            v-if="canUserWrite"
                            @click="openCreateModal"
                            title="Nueva actividad"
                            class="inline-flex items-center gap-2 bgPrincipal text-white px-5 py-2.5 rounded-lg shadow-sm hover:bg-indigo-700 transition-colors duration-200 h-[44px] text-sm font-medium"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Nueva actividad
                        </button>

                        <!-- ═══ Acciones del espacio seleccionado: Roles + Etiquetas + Analítica ═══ -->
                        <OperacionesButton
                            v-if="canUserManage || canEditSpace"
                            title="Este espacio"
                            :operations="scrumOperations"
                        />

                        <!-- ═══ Menú "Más acciones" (kebab) — acciones poco frecuentes ═══ -->

                    </div>
                </div>

                <!-- ── ERROR JIRA ────────────────────────────────────────────── -->
                <div
                    v-if="jira_error"
                    class="p-3 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm"
                >
                    <strong>Error Jira ({{ jira_error.code }}):</strong>
                    {{ jira_error.message }}
                </div>

                <IssueTable
                    :grouped-issues="displayGroups"
                    :expanded-keys="expandedKeys"
                    :children-loading="childrenLoading"
                    :expanded-reprog-keys="expandedReprogKeys"
                    :reprog-loading="reprogLoading"
                    :visible-columns="visibleColumns"
                    :selected-issues="selectedIssues"
                    :table-loading="tableLoading"
                    :can-user-write="canUserWrite"
                    :can-approve="canApproveInSpace"
                    :can-manage="canUserManage"
                    :space-admin-ids="spaceAdminIds"
                    :current-user-account-id="String($page.props.auth?.user?.id ?? '')"
                    :editing="editing"
                    :inline-loading="inlineLoading"
                    :total-count="totalCount"
                    :all-issues-count="allIssues.length"
                    :filtered-count="filteredIssues.length"
                    :is-last="isLast"
                    :load-more-loading="loadMoreLoading"
                    :custom-fields="customFieldsLocal"
                    :jira-priorities="jiraPriorities"
                    :teams-local="teamsLocal"
                    :assignable-users="createAssignees"
                    :terminal-statuses="options.terminal_statuses || ['Finalizado']"
                    @update:selected-issues="selectedIssues = $event"
                    @toggle-select-all="toggleSelectAll"
                    @copy-link="copyShareLink"
                    @start-edit="(issue, field, e) => startEdit(issue, field, e)"
                    @cancel-edit="cancelEdit"
                    @save-edit="handleSaveEdit"
                    @update:editing-value="editing.value = $event"
                    @load-more="loadMore"
                    @open-subtask="openSubTaskModal"
                    @open-timeline="openTimelineModal"
                    @open-history="openActivityHistoryModal"
                    @toggle-expand="toggleExpand"
                    @create-subactividad="handleCreateSubactividad"
                    @delete-subactividad="handleDeleteSubactividad"
                    @toggle-expand-reprog="toggleExpandReprog"
                    @open-description="openDescriptionModal"
                    @open-summary="openSummaryModal"
                />
                </div>
            </div>
        </div>
            </div>
            <!-- ── Mis pendientes: panel lateral que EMPUJA el contenido (cross-espacio) ── -->
            <MisPendientesDrawer
                ref="misPendientesRef"
                :show="showMisPendientes"
                @close="showMisPendientes = false"
                @focus-issue="onPendienteFocus"
            />
        </div>

        <!-- ── MODAL: Reprogramar actividad ────────────────────────────────── -->
        <ReprogramarModal
            :show="showReprogramarModal"
            :issue-key="reprogramarIssue?.key"
            :fecha-limite-original="reprogramarIssue?.fecha_limite"
            :start-date-original="reprogramMinStart"
            :reprogramaciones-count="reprogramarIssue?.reprogramaciones_count || 0"
            :available-users="createAssignees"
            :project-key="form.project"
            :priority-options="options.priorities || []"
            :bloquear-fechas="bloquearFechasEspacio"
            @close="showReprogramarModal = false; reprogramarIssue = null"
            @reprogramado="onReprogramado"
        />

        <!-- ── DRAWER: Analítica del espacio (KPIs) ──────────────────────── -->
        <EspacioAnalyticsDrawer
            :show="showAnalyticsDrawer"
            :project-key="form.project"
            :space-name="currentProject?.name"
            @close="showAnalyticsDrawer = false"
        />

        <!-- ── BARRA DE ACCIONES MASIVAS ─────────────────────────────────── -->
        <BulkActionsBar
            :count="selectedIssues.length"
            :can-edit="canInlineEdit"
            :can-delete="canUserManage"
            :loading="bulkLoading"
            @edit="openBulkEditModal"
            @delete="openDeleteModal"
            @clear="clearSelection"
        />

        <!-- ── MODAL: Edición masiva ──────────────────────────────── -->
        <BulkEditModal
            v-model:show="showEditModal"
            :selected-keys="selectedIssues"
            :priorities="jiraPriorities"
            :fallback-priorities="options.priorities || []"
            :unique-priorities="uniquePriorities"
            @completed="onBulkEditCompleted"
            @error="showError"
        />

        <!-- ── MODAL: ELIMINAR EN MASA ────────────────────────────────────── -->
        <BulkDeleteModal
            v-model:show="showDeleteModal"
            :selected-keys="selectedIssues"
            @completed="onBulkDeleteCompleted"
            @error="showError"
        />

        <CreateIssueModal
            :show="showCreateModal"
            :create-form="createForm"
            :projects="projects"
            :create-issue-types="createIssueTypes"
            :create-types-loading="createTypesLoading"
            :jira-priorities="jiraPriorities"
            :unique-priorities="uniquePriorities"
            :create-statuses="createStatuses"
            :create-statuses-loading="createStatusesLoading"
            :create-labels="createLabels"
            :create-labels-loading="createLabelsLoading"
            :space-categorias="spaceCategorias"
            :assignee-suggestions="assigneeSuggestions"
            :assignee-loading="assigneeLoading"
            :create-assignees-loading="createAssigneesLoading"
            :reporter-suggestions="reporterSuggestions"
            :reporter-loading="reporterLoading"
            :create-error="createError"
            :preview-ready="previewReady"
            :space-teams="assignableTeams"
            :priority-options="options.priorities || []"
            :initials="initials"
            :is-icon-url="isIconUrl"
            :parent-key="createParentKey"
            :parent-start-date="createParentStartDate"
            :bloquear-fechas="bloquearFechasEspacio"
            :submitting="isCreateSubmitting"
            :impacto-options="options.impacto_values || ['Crítico', 'Alto', 'Medio', 'Bajo', 'Sin impacto']"
            @close="closeCreateModal"
            @submit="submitCreate"
            @assignee-input="onAssigneeInput"
            @assignee-focus="onAssigneeFocus"
            @select-assignee="selectAssignee"
            @reporter-input="onReporterInput"
            @reporter-focus="onReporterFocus"
            @select-reporter="selectReporter"
            @clear-assignee-suggestions="assigneeSuggestions = []"
            @clear-reporter-suggestions="reporterSuggestions = []"
        />
        <InlineEditDropdowns
            :editing="editing"
            :dropdown-anchor="dropdownAnchor"
            :dropdown-style="dropdownStyle"
            :status-options="options.statuses || []"
            :critical-statuses="options.critical_statuses || ['Finalizado', 'Reprogramado', 'Cancelado']"
            :can-approve-only="canApproveInSpace && !canUserWrite"
            :can-approve="canApproveInSpace"
            :all-issues="allIssues"
            :current-status-name="editing.field === 'status' ? editing.value : null"
            :jira-priorities="jiraPriorities"
            :priority-options="options.priorities || []"
            :unique-priorities="uniquePriorities"
            :assignee-suggestions="inlineAssigneeSuggestions"
            :reporter-suggestions="inlineReporterSuggestions"
            :assignee-loading="inlineAssigneeLoading"
            :reporter-loading="inlineReporterLoading"
            :team-options="assignableTeams"
            :space-categorias="spaceCategorias"
            :initials="initials"
            @cancel="cancelEdit"
            @status-select="(s) => { editing.value = s; saveInlineEdit(); }"
            @priority-select="(p) => { editing.value = p; saveInlineEdit(); }"
            @assignee-input-inline="onAssigneeInputInline"
            @assignee-focus-inline="onAssigneeFocusInline"
            @select-assignee-inline="selectAssigneeInline"
            @select-team-inline="selectTeamInline"
            @reporter-input-inline="onReporterInputInline"
            @reporter-focus-inline="onReporterFocusInline"
            @select-reporter-inline="selectReporterInline"
            @categoria-select="(c) => { editing.value = c; saveInlineEdit(); }"
        />

        <!-- ── MODALES DE PROYECTO (Crear + Editar/Eliminar) ─────────────── -->
        <!-- Ambos comparten el mismo componente, cambia el modo y el project. -->
        <!-- ── MODAL: Gestión de Equipos (SCRUM) ─────────────────────────── -->
        <TeamsManagementModal
            v-model:show="showTeamsModal"
            :teams="teamsLocal"
            :project-key="teamsModalProjectKey"
            :can-edit="canEdit"
            :available-users="spaceMemberCandidates"
            @teams-changed="onTeamsChanged"
            @error="showError"
        />

        <!-- ── MODAL: Gestión de Etiquetas (SCRUM, por espacio) ──────────── -->
        <LabelsManagementModal
            v-model:show="showLabelsModal"
            :project-key="teamsModalProjectKey"
            :can-edit="canEdit"
            :spaces="projects"
            @labels-changed="onLabelsChanged"
            @error="showError"
        />

        <!-- ── MODAL: Timeline (audit log de cambios) ──────────────────── -->
        <TimelineModal
            v-model:show="showTimelineModal"
            :issue-key="timelineKey"
        />

        <!-- ── MODAL: Historial de Actividades (comentarios + adjuntos) ── -->
        <ActivityHistoryModal
            v-model:show="showActivityHistoryModal"
            :issue-key="activityHistoryKey"
            :pending-field="activityHistoryPendingField"
            :pending-value="activityHistoryPendingValue"
            :can-edit="canUserWrite || canApproveInSpace"
            @apply-pending="retryInlineEditAfterHistory"
            @error="handleActivityHistoryError"
        />

        <!-- ── MODAL: Sub Tareas ──────────────────────────────────────── -->
        <SubTaskModal
            v-model:show="showSubTaskModal"
            :issue-key="subTaskIssueKey"
            :issue-summary="subTaskSummary"
            :project-key="form.project"
            :available-users="createAssignees"
            :can-edit="canUserWrite"
            :can-approve="canApproveInSpace"
            @tareas-updated="onTareasUpdated"
        />

        <SpaceMembersModal
            v-model:show="showRolesModal"
            :project-key="form.project"
            :space-name="currentProject?.name"
            :can-edit="canUserWrite"
            :available-users="spaceMemberCandidates"
            @members-changed="() => {}"
        />

        <McpSessionsModal v-model:show="showMcpSessionsModal" />

        <!-- ── MODAL: Descripción completa (actividad / subactividad / -Rn) ──────── -->
        <ScrumModalOverlay :show="showDescriptionModal" />
        <ModalView
            :show="showDescriptionModal"
            :title="descriptionIssue?.summary || 'Descripción'"
            size="lg"
            :hide-footer="true"
            @close="showDescriptionModal = false"
        >
            <template #subtitle>
                <span class="font-mono bg-white/20 px-1.5 py-0.5 rounded text-[10px]">{{ descriptionIssue?.key }}</span>
                <span v-if="descriptionIssue?._isReprog" class="ml-1.5 inline-flex items-center h-4 px-1.5 rounded text-[10px] font-bold bg-amber-500 text-white">Reprogramación R{{ descriptionIssue?.reprogramacion_n }}</span>
            </template>
            <div class="max-h-[60vh] overflow-y-auto custom-scrollbar">
                <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed whitespace-pre-line">{{ descriptionIssue?.description }}</p>
            </div>
        </ModalView>

        <!-- ── MODAL: Título completo + editar + histórico (actividad / sub / -Rn) ── -->
        <SummaryModal
            v-model:show="showSummaryModal"
            :issue="summaryIssue"
            :project-key="form.project"
            :can-edit="canInlineEdit"
            @updated="onSummaryUpdated"
        />

        <ProjectFormModal
            v-model:show="showCreateProjectModal"
            mode="create"
            :user-options="projectUserOptions"
            :find-user="findProjectUser"
            :space-categories="spaceCategories"
            :all-teams="allGlobalTeams"
            @search-users="loadProjectUsers"
            @saved="onProjectModalSaved"
            @error="onProjectModalError"
        />
        <ProjectFormModal
            v-model:show="showEditProjectModal"
            mode="edit"
            :project="editingProject"
            :can-manage="canUserManage"
            :can-delete="canEditSpace"
            :user-options="projectUserOptions"
            :find-user="findProjectUser"
            :space-categories="spaceCategories"
            :all-teams="allGlobalTeams"
            @search-users="loadProjectUsers"
            @saved="onProjectModalSaved"
            @deleted="onProjectModalDeleted"
            @error="onProjectModalError"
        />

    </AuthenticatedLayout>

    <!-- ══ PANEL LATERAL: CONFIGURAR COLUMNAS ══════════════════════════════════ -->
    <ColumnConfigPanel
        v-model:show="showColumnPanel"
        :columns="allColumnsSorted"
        :visible-count="visibleColumns.length"
        @toggle="toggleColumnVisibility"
        @move-up="moveColumnUp"
        @move-down="moveColumnDown"
        @add-column="openAddColumnModal"
    />

    <!-- ══ MODAL: AÑADIR COLUMNA ════════════════════════════════════════════════ -->
    <AddColumnModal
        v-model:show="showAddColumnModal"
        :project-key="form.project"
        :available-catalog="availableCatalogColumns"
        :available-custom-fields="availableCustomFields"
        @added="onColumnAdded"
        @error="showError"
        @update:show="(val) => !val && onAddColumnModalClosed()"
    />

</template>
