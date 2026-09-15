import axios from 'axios';
import { computed, nextTick, reactive, ref } from 'vue';
import { mapStatusMeta } from '@/Composables/GestionProyectos/useStatusMeta';

/**
 * useInlineEdit — Gestiona toda la edición inline de celdas en la tabla de issues.
 *
 * @param {import('vue').Ref}          allIssues     — Lista reactiva de todos los issues
 * @param {import('vue').Ref}          localColumnPrefs — Preferencias de columnas
 * @param {import('vue').ComputedRef}  canInlineEdit  — Permiso de edición inline (solo propietario del espacio / admin global)
 * @param {import('vue').Ref}          form          — Formulario de filtros (para project key)
 * @param {object}                     deps          — Dependencias adicionales:
 *   - createAssignees: Ref<array>
 *   - uniqueUsers: ComputedRef<array>
 *   - uniqueReporters: ComputedRef<array>
 *   - uniquePriorities: ComputedRef<array>
 *   - teamsLocal: Ref<array>
 *   - jiraPriorities: Ref<array>
 *   - loadAssignableUsers: function
 *   - loadJiraPriorities: function
 *   - openActivityHistoryModal: function
 *   - autoCalcFechaProgramada: function
 *   - saveCustomFieldValue: function
 */
export function useInlineEdit(allIssues, localColumnPrefs, canInlineEdit, form, deps) {
    const {
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
        openReprogramarModal = null,
        // Resuelve un issue por key buscando en TODAS las cachés (principal, subactividades,
        // reprogramaciones). Necesario porque las subactividades/-Rn no viven en allIssues.
        resolveIssue = null,
        // Permisos para el campo Estado (transición de flujo): escritores y aprobadores.
        canUserWrite = { value: false },
        canApprove = { value: false },
        // Flag del espacio: si true, "Producción" (fecha_aprobacion) se puede editar aun Finalizado.
        produccionEditableFinalizado = { value: false },
        // Callback tras guardar (para refrescar hijos: subactividades / -Rn).
        onAfterSave = null,
    } = deps;

    // ─── Estado de edición ─────────────────────────────────────────────────────
    const editing = reactive({
        key: null,
        field: null,
        value: null,
        _fieldId: null,
        _fieldType: null,
    });

    const dropdownAnchor = ref(null);
    const inlineLoading = ref(false);

    // Refs para sugerencias de búsqueda
    const assigneeSuggestions = ref([]);
    const reporterSuggestions = ref([]);
    const assigneeLoading = ref(false);
    const reporterLoading = ref(false);
    let assigneeTimer = null;
    let reporterTimer = null;
    let scrollCloseHandler = null;

    function _attachScrollClose() {
        if (scrollCloseHandler) return;
        scrollCloseHandler = (e) => {
            if (e && e.target && (e.target.classList?.contains('custom-scrollbar') || e.target.closest?.('.custom-scrollbar'))) {
                return;
            }
            if (editing.key) cancelEdit();
        };
        window.addEventListener('scroll', scrollCloseHandler, { capture: true, passive: true });
        window.addEventListener('resize', scrollCloseHandler, { passive: true });
    }

    function _detachScrollClose() {
        if (!scrollCloseHandler) return;
        window.removeEventListener('scroll', scrollCloseHandler, { capture: true });
        window.removeEventListener('resize', scrollCloseHandler);
        scrollCloseHandler = null;
    }

    // ─── Estilo del dropdown teleportado ──────────────────────────────────────
    const dropdownStyle = computed(() => {
        if (!dropdownAnchor.value) return {};
        const a = dropdownAnchor.value;
        if (a.openUp) {
            return { position: 'fixed', bottom: `${window.innerHeight - a.rectTop + 4}px`, left: `${a.rectLeft}px`, zIndex: 9999 };
        }
        return { position: 'fixed', top: `${a.rectBottom + 4}px`, left: `${a.rectLeft}px`, zIndex: 9999 };
    });

    // ─── Iniciar edición ───────────────────────────────────────────────────────
    function startEdit(issue, field, event = null) {
        // El campo ESTADO es una transición de flujo: la pueden hacer escritores (Ejecutor/Admin/
        // Propietario) y aprobadores. El resto de campos es edición de datos: solo propietario/admin global.
        if (field === 'status') {
            if (!canUserWrite.value && !canApprove.value) return;
        } else if (!canInlineEdit.value) {
            return;
        }
        if (['key', 'dias_estimados'].includes(field)) return;

        // "Producción" (fecha_aprobacion): en una tarea Finalizada solo se puede editar si el
        // espacio tiene "Producción editable tras finalizar" ON. En otro terminal (Cancelado) o
        // con el switch OFF, queda bloqueada (espejo del candado del backend) → ni se abre.
        if (field === 'fecha_aprobacion') {
            const st = issue.status?.name;
            const esTerminal = st === 'Finalizado' || st === 'Cancelado';
            if (esTerminal && !(st === 'Finalizado' && produccionEditableFinalizado.value)) return;
        }

        if (editing.key === issue.key && editing.field === field) return;

        if (field.startsWith('custom_')) {
            const col = localColumnPrefs.value.find((c) => c.key === field);
            if (col && col.type === 'checkbox') {
                const currentValue = issue.custom_fields?.[field]?.value ?? 'false';
                const newValue = currentValue === 'true' ? 'false' : 'true';
                saveCustomFieldValue(issue.key, field, newValue);
                return;
            }
            editing.key = issue.key;
            editing.field = field;
            editing._fieldId = col?.field_id ?? null;
            editing._fieldType = col?.type ?? 'text';
            editing.value = issue.custom_fields?.[field]?.value ?? '';

            if (col && col.type === 'people') {
                loadAssignableUsers(form.project).then(() => {
                    assigneeSuggestions.value = createAssignees.value;
                });
                if (event) {
                    const rect = event.currentTarget.getBoundingClientRect();
                    dropdownAnchor.value = {
                        rectTop: rect.top,
                        rectBottom: rect.bottom,
                        rectLeft: rect.left,
                        rectWidth: rect.width,
                        openUp: window.innerHeight - rect.bottom < 280,
                    };
                    _attachScrollClose();
                }
                return;
            }

            // Enfocar el input del campo personalizado (era omitido por el return temprano)
            nextTick(() => {
                const el = document.querySelector(`[data-editing="${field}-${issue.key}"]`);
                if (el) el.focus();
            });
            return;
        }

        if (event) {
            const rect = event.currentTarget.getBoundingClientRect();
            dropdownAnchor.value = {
                rectTop: rect.top,
                rectBottom: rect.bottom,
                rectLeft: rect.left,
                rectWidth: rect.width,
                openUp: window.innerHeight - rect.bottom < 280,
            };
            _attachScrollClose();
        } else {
            dropdownAnchor.value = null;
        }

        editing.key = issue.key;
        editing.field = field;
        editing._fieldId = null;
        editing._fieldType = null;

        if (field === 'assignee') {
            editing.value = issue.assignee?.account_id || null;
            loadAssignableUsers(form.project).then(() => {
                assigneeSuggestions.value = createAssignees.value;
            });
        } else if (field === 'reporter' || field === 'solicitado_por') {
            const userObj = field === 'solicitado_por' ? issue.solicitado_por : issue.reporter;
            editing.value = userObj?.account_id || null;
            loadAssignableUsers(form.project).then(() => {
                reporterSuggestions.value = createAssignees.value;
            });
        } else if (field === 'validado_por') {
            // "Validado Por": mismo dropdown de miembros del espacio que "Persona Asignada".
            editing.value = issue.validado_por?.account_id || null;
            loadAssignableUsers(form.project).then(() => {
                assigneeSuggestions.value = createAssignees.value;
            });
        } else if (field === 'status') {
            editing.value = issue.status?.name;
        } else if (field === 'priority') {
            editing.value = issue.priority?.name;
            loadJiraPriorities();
        } else {
            editing.value = issue[field];
        }

        nextTick(() => {
            const el =
                document.querySelector(`[data-editing-teleport="${field}"]`) ||
                document.querySelector(`[data-editing="${field}-${issue.key}"]`);
            if (el) el.focus();
        });
    }

    // ─── Cancelar edición ─────────────────────────────────────────────────────
    function cancelEdit() {
        editing.key = null;
        editing.field = null;
        editing.value = null;
        dropdownAnchor.value = null;
        assigneeSuggestions.value = [];
        reporterSuggestions.value = [];
        _detachScrollClose();
    }

    // ─── Guardar edición inline ────────────────────────────────────────────────
    async function saveInlineEdit() {
        if (!editing.key || inlineLoading.value) return;

        const key = editing.key;
        const field = editing.field;
        const value = editing.value;

        if (field && field.startsWith('custom_')) {
            await saveCustomFieldValue(key, field, value);
            return;
        }

        const issue = allIssues.value.find((i) => i.key === key);

        // Las subactividades (filas hijas) no viven en allIssues. Si no está en caché,
        // omitimos el chequeo de "sin cambios" y vamos directo al PATCH (válido por key).
        if (issue) {
            let originalValue = null;
            if (field === 'assignee') originalValue = issue.assignee?.account_id || null;
            else if (field === 'reporter') originalValue = issue.reporter?.account_id || null;
            else if (field === 'solicitado_por') originalValue = issue.solicitado_por?.account_id || null;
            else if (field === 'validado_por') originalValue = issue.validado_por?.account_id || null;
            else if (field === 'status') originalValue = issue.status?.name;
            else if (field === 'priority') originalValue = issue.priority?.name;
            else if (field === 'labels') originalValue = JSON.stringify([...(issue.label_names || issue.labels || [])].sort());
            else originalValue = issue[field];

            if (field === 'labels') {
                const sortedNew = JSON.stringify([...(value || [])].sort());
                if (sortedNew === originalValue) { cancelEdit(); return; }
            } else if (value === originalValue) { cancelEdit(); return; }
        }

        // "Reprogramado" abre el modal dedicado en lugar del flujo PATCH normal.
        // El issue puede ser una subactividad o una -Rn (no viven en allIssues): se
        // resuelve con resolveIssue para no abrir el modal con key nula (causaba que
        // route() reventara y el front mostrara "Error de conexión").
        if (field === 'status' && value === 'Reprogramado') {
            const target = resolveIssue ? resolveIssue(key) : allIssues.value.find((i) => i.key === key);
            cancelEdit();
            if (!target) {
                window.showToast?.('No se pudo abrir la reprogramación de esta fila.', 'error', { timer: 3000 });
                return;
            }
            openReprogramarModal?.(target);
            return;
        }

        // Cerrar dropdown de inmediato para prevenir envíos duplicados
        dropdownAnchor.value = null;
        inlineLoading.value = true;

        const payload = { project_key: form.project };
        if (field === 'assignee') {
            payload.assignee_account_id = value || 'null';
            // "Sin asignar" desde la columna unificada limpia también el equipo.
            if (!value || value === 'null') payload.team_id = null;
        }
        else if (field === 'reporter' || field === 'solicitado_por') payload.reporter_account_id = value || 'null';
        else if (field === 'validado_por') payload.validado_por_account_id = value || 'null';
        else if (field === 'status') payload.status = value;
        else if (field === 'priority') payload.priority = value;
        else if (field === 'summary') payload.summary = value;
        else payload[field] = value;

        try {
            const { status: httpStatus, data } = await axios.patch(
                route('gestion-proyectos.update', { key }),
                payload,
                { validateStatus: () => true }
            );

            if (httpStatus >= 200 && httpStatus < 300) {
                if (issue) {
                    // Sincronizar dias_estimados si el backend lo recalculó (p.ej. al editar fecha_limite).
                    if (data?.dias_estimados != null) issue.dias_estimados = data.dias_estimados;
                    // Fechas de etapa auto-sincronizadas con el estado (En Revisión→Stage, Finalizado→Producción).
                    if (data?.fecha_entrega !== undefined)    issue.fecha_entrega    = data.fecha_entrega;
                    if (data?.fecha_aprobacion !== undefined) issue.fecha_aprobacion = data.fecha_aprobacion;
                    // "Aprobado Por" se llena al finalizar; el backend lo devuelve → reflejarlo sin F5.
                    if (data?.aprobado_por !== undefined)     issue.aprobado_por     = data.aprobado_por;
                    // Badge del botón "Seguimiento" (nº de cambios auditados): el backend lo devuelve
                    // recalculado tras escribir la entrada → actualizarlo sin F5.
                    if (data?.audit_changes_count !== undefined) issue.audit_changes_count = data.audit_changes_count;
                    if (field === 'assignee') {
                        if (value === 'null' || !value) {
                            issue.assignee = null;
                        } else {
                            const user = createAssignees.value.find((u) => u.account_id === value) || uniqueUsers.value.find((u) => u.account_id === value);
                            if (user) issue.assignee = { ...user };
                        }
                        // XOR: asignar usuario limpia el equipo (espejo del backend).
                        issue.team = null;
                        issue.team_id = null;
                    } else if (field === 'reporter' || field === 'solicitado_por') {
                        if (value === 'null' || !value) {
                            issue.reporter = null;
                            issue.solicitado_por = null;
                        } else {
                            const user = createAssignees.value.find((u) => u.account_id === value) || uniqueReporters.value.find((u) => u.account_id === value);
                            if (user) {
                                issue.reporter = { ...user };
                                issue.solicitado_por = { ...user };
                            }
                        }
                    } else if (field === 'validado_por') {
                        // El backend devuelve el usuario validador resuelto (o null) → lo reflejamos.
                        issue.validado_por = data?.validado_por ?? null;
                    } else if (field === 'status') {
                        // Actualizar nombre + color_class + category_key para que el chip cambie de color al instante.
                        issue.status = mapStatusMeta(value);
                    } else if (field === 'priority') {
                        const prio = jiraPriorities.value.find((p) => p.name === value) || uniquePriorities.value.find((p) => p.name === value);
                        issue.priority = { name: value, icon_url: prio?.iconUrl || prio?.icon_url };
                    } else if (field === 'labels') {
                        issue.labels = [...value];
                        issue.label_names = [...value];
                    } else if (field === 'team_id') {
                        issue.team_id = value ? Number(value) : null;
                        issue.team = value ? (teamsLocal.value.find((t) => String(t.id) === String(value)) ?? null) : null;
                        // XOR: asignar equipo limpia el usuario (espejo del backend).
                        if (value) issue.assignee = null;
                    } else {
                        issue[field] = value;
                    }
                }
                cancelEdit();

                // Refresco de filas que no viven en allIssues (subactividades / versiones -Rn):
                // el padre re-fetchea sus hijos para reflejar el cambio (estado, asignado, etc.).
                onAfterSave?.(key, field);

                if (field === 'start_date') {
                    inlineLoading.value = false;
                    await autoCalcFechaProgramada(key);
                    return;
                }
            } else if (httpStatus === 422) {
                // Tanto la evidencia del historial como la nueva fecha de reprogramación
                // se capturan en la misma modal — abrirla en ambos casos.
                if (data?.requires === 'activity_history' || data?.requires === 'fecha_reprogramacion') {
                    cancelEdit();
                    openActivityHistoryModal(key, field, value);
                } else {
                    const errorMsg = data?.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data?.error || data?.message || 'Error al actualizar el campo.');
                    window.showToast(errorMsg, 'error');
                }
            } else {
                window.showToast(data?.error || data?.message || 'Error al actualizar el campo.', 'error');
            }
        } catch {
            window.showToast('Error de conexión al actualizar. Intenta de nuevo.', 'error');
        } finally {
            inlineLoading.value = false;
        }
    }

    // ─── Helpers de asignación inline ─────────────────────────────────────────
    function selectAssigneeInline(user) {
        editing.value = user ? user.account_id : null;
        saveInlineEdit();
    }

    // Selección de equipo desde el dropdown unificado de "Persona Asignada".
    // Cambia el campo en edición a team_id; el backend (XOR) limpia el usuario.
    function selectTeamInline(team) {
        editing.field = 'team_id';
        editing.value = team ? team.id : null;
        saveInlineEdit();
    }

    function onAssigneeInputInline(e) {
        const q = e.target.value;
        clearTimeout(assigneeTimer);
        assigneeTimer = setTimeout(async () => {
            assigneeLoading.value = true;
            await loadAssignableUsers(form.project, q);
            assigneeSuggestions.value = createAssignees.value;
            assigneeLoading.value = false;
        }, 300);
    }

    function selectReporterInline(user) {
        editing.value = user ? user.account_id : null;
        saveInlineEdit();
    }

    function onReporterInputInline(e) {
        const q = e.target.value;
        clearTimeout(reporterTimer);
        reporterTimer = setTimeout(async () => {
            reporterLoading.value = true;
            await loadAssignableUsers(form.project, q);
            reporterSuggestions.value = createAssignees.value;
            reporterLoading.value = false;
        }, 300);
    }

    return {
        editing,
        dropdownAnchor,
        dropdownStyle,
        inlineLoading,
        assigneeSuggestions,
        reporterSuggestions,
        assigneeLoading,
        reporterLoading,
        startEdit,
        cancelEdit,
        saveInlineEdit,
        selectAssigneeInline,
        selectTeamInline,
        onAssigneeInputInline,
        selectReporterInline,
        onReporterInputInline,
    };
}
