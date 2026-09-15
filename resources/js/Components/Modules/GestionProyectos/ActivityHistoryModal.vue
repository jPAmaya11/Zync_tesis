<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Historial de Actividades"
        size="2xl"
        :hide-footer="true"
        @close="close"
    >
        <template #subtitle>
            Tarea:
            <span class="font-mono font-semibold text-white/90">{{ issueKey }}</span>
            <span v-if="pendingValue" class="ml-2 inline-flex items-center gap-1 text-amber-200">
                · Estado pendiente: <strong>{{ pendingValue }}</strong>
            </span>
        </template>

        <!-- Body: lista + formulario integrados (se mantiene el padding superior del ModalView) -->
        <div class="flex flex-col -mx-8 -mb-6 pt-2">
            <!-- Lista de entradas (scroll) -->
            <div class="flex-1 overflow-y-auto custom-scrollbar px-6 py-4 space-y-4 max-h-[45vh]">
                <div v-if="loading" class="flex items-center justify-center py-8">
                    <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                    </svg>
                    <span class="ml-2 text-sm text-gray-500">Cargando historial...</span>
                </div>

                <!-- <div
                    v-else-if="!entries.length"
                    class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-zinc-500"
                >
                    <svg class="w-10 h-10 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-sm font-medium">Sin registros todavía</p>
                    <p v-if="canEdit" class="text-xs mt-1">Añade la primera entrada usando el formulario de abajo.</p>
                </div> -->

                <template v-else>
                    <div v-for="entry in entries" :key="entry.id" class="flex gap-3">
                        <UserAvatar
                            :src="entry.user_avatar_url"
                            :name="entry.user_name"
                            size="lg"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ entry.user_name || 'Usuario' }}</span>
                                <span class="text-xs text-gray-400 dark:text-zinc-500">{{ entry.created_at }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words">{{ entry.comment }}</p>
                            <div v-if="entry.attachments?.length" class="mt-1 flex flex-col gap-0.5">
                                <a
                                    v-for="(att, i) in entry.attachments"
                                    :key="i"
                                    :href="att.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    {{ att.name || 'Adjunto' }}
                                </a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Formulario nueva entrada (sticky bottom) -->
            <div v-if="canEdit" class="shrink-0 border-t border-gray-100 dark:border-zinc-800 px-6 py-4 bg-gray-50 dark:bg-zinc-800/50 space-y-3">
                <p
                    v-if="pendingValue"
                    class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2"
                >
                    <strong>Requerido:</strong> Para cambiar el estado a
                    <strong>{{ pendingValue }}</strong>, debe registrar un comentario en el historial.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Comentario <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="form.comment"
                        rows="3"
                        maxlength="5000"
                        placeholder="Describe la actividad realizada, motivo del cambio, etc."
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 resize-none"
                    ></textarea>
                </div>

                <!-- Reprogramado: pedir nueva fecha de vencimiento.
                     Se guarda en fecha_reprogramacion; fecha_limite original queda intacta. -->
                <div v-if="isReprogramando">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Nueva fecha de vencimiento <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.newDueDate"
                        type="date"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    />
                    <p class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                        La fecha límite original se conserva como referencia; esta es la nueva fecha objetivo.
                    </p>
                </div>

                <!-- Adjunto: drag & drop + click -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Adjuntar archivo
                        <span class="ml-1 text-gray-400 font-normal">(opcional, máx. 3 archivos)</span>
                    </label>
                    <FileUploader
                        v-model="form.attachments"
                        mode="multiple"
                        preset="mixed"
                        :max-size="20480"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button
                        type="button"
                        @click="close"
                        class="px-4 py-2 text-xs font-medium text-gray-600 dark:text-gray-300 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors"
                    >Cancelar</button>
                    <button
                        type="button"
                        :disabled="!canSubmit"
                        @click="submit"
                        :class="[
                            'px-4 py-2 text-xs font-semibold rounded-lg transition-colors',
                            canSubmit
                                ? 'bg-indigo-600 hover:bg-indigo-700 text-white'
                                : 'bg-gray-200 dark:bg-zinc-700 text-gray-400 cursor-not-allowed',
                        ]"
                    >
                        <span v-if="saving" class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                            </svg>
                            Guardando...
                        </span>
                        <span v-else>{{ pendingValue ? 'Guardar y aplicar estado' : 'Añadir al historial' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </ModalView>
</template>

<script setup>
import FileUploader from '@/Components/Common/FileUploader.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    issueKey: { type: String, default: null },
    pendingField: { type: String, default: null },
    pendingValue: { type: [String, Number, Array], default: null },
    canEdit: { type: Boolean, default: true },
});

const emit = defineEmits(['update:show', 'saved', 'apply-pending', 'error']);

const entries = ref([]);
const loading = ref(false);
const saving = ref(false);
const form = ref({ comment: '', attachments: [], newDueDate: '' });

// Límite de 3 archivos por evidencia (el FileUploader es compartido y no se toca: se acota aquí).
const MAX_EVID_FILES = 3;
watch(
    () => form.value.attachments,
    (files) => {
        if (Array.isArray(files) && files.length > MAX_EVID_FILES) {
            form.value.attachments = files.slice(0, MAX_EVID_FILES);
            window.showToast?.(`Máximo ${MAX_EVID_FILES} archivos por evidencia.`, 'warning', { timer: 2500 });
        }
    }
);

const isReprogramando = computed(
    () => props.pendingField === 'status' && props.pendingValue === 'Reprogramado'
);

const canSubmit = computed(() => {
    if (saving.value) return false;
    if (form.value.comment.trim().length === 0) return false;
    if (isReprogramando.value && !form.value.newDueDate) return false;
    return true;
});

watch(
    () => props.show,
    async (val) => {
        if (val && props.issueKey) {
            form.value = { comment: '', attachments: [], newDueDate: '' };
            await refresh();
        } else if (!val) {
            entries.value = [];
            form.value = { comment: '', attachments: [], newDueDate: '' };
        }
    }
);

async function refresh() {
    if (!props.issueKey) return;
    loading.value = true;
    try {
        const { data } = await window.axios.get(
            route('gestion-proyectos.activity.index', { key: props.issueKey })
        );
        entries.value = data;
    } catch {
        // silencioso
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!canSubmit.value) return;
    saving.value = true;
    try {
        const fd = new FormData();
        fd.append('comment', form.value.comment);
        (form.value.attachments || []).forEach((f) => fd.append('attachment[]', f));
        // Marca: este registro es la evidencia exigida para una transición crítica.
        // Con esto el backend suprime el correo de "nuevo comentario" para evitar
        // doble notificación (el de cambio de estado ya incluye el comentario).
        if (props.pendingField === 'status' && props.pendingValue) {
            fd.append('for_critical_transition', '1');
            // Etiqueta esta evidencia con el estado destino: el backend exige que la evidencia
            // sea específica de esta transición (un comentario viejo no habilita otra transición).
            fd.append('new_status', props.pendingValue);
        }

        // axios envía la cookie XSRF-TOKEN (siempre fresca) y arma el multipart con su
        // boundary; evita el 419 que daba el token estancado del <meta> con fetch.
        await window.axios.post(
            route('gestion-proyectos.activity.store', { key: props.issueKey }),
            fd
        );

        window.showToast('Registro añadido al historial.', 'success', { timer: 3000 });
        await refresh();

        if (props.pendingField && props.pendingValue) {
            emit('apply-pending', {
                key: props.issueKey,
                field: props.pendingField,
                value: props.pendingValue,
                // Solo viaja cuando la transición es a Reprogramado.
                extras: isReprogramando.value
                    ? { fecha_reprogramacion: form.value.newDueDate }
                    : null,
            });
            close();
        } else {
            emit('saved');
            form.value = { comment: '', attachments: [], newDueDate: '' };
        }
    } catch (err) {
        const data = err.response?.data ?? {};
        const msg = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : (data.error || data.message || (err.response ? 'Error al guardar.' : 'Error de conexión.'));
        emit('error', msg);
    } finally {
        saving.value = false;
    }
}

function close() {
    emit('update:show', false);
}

function initials(name) {
    return (name || '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
}
</script>
