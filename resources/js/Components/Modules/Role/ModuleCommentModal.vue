<template>
    <ModalView
        :show="show"
        title="Comentario"
        :subtitle="module ? module.nombre : ''"
        size="lg"
        @close="$emit('close')"
    >
        <!-- ══ Modo escritura (solo admin) ══ -->
        <div v-if="canWrite" class="space-y-3">
            <!-- <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Descripción
            </label> -->
            <textarea
                v-model="body"
                rows="6"
                maxlength="2000"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors px-3 py-2 resize-y"
            ></textarea>
            <div class="flex items-center justify-between text-xs text-gray-400">
                <span v-if="meta">Última edición por <strong>{{ meta.editor || '—' }}</strong> · {{ formatFecha(meta.updated_at) }}</span>
                <span v-else>Aún sin descripción</span>
                <span class="tabular-nums">{{ body.length }}/2000</span>
            </div>
        </div>

        <!-- ══ Modo lectura (no-admin) ══ -->
        <div v-else class="space-y-3">
            <template v-if="meta && meta.body">
                <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">{{ meta.body }}</p>
                <p class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700">
                    Última edición por <strong>{{ meta.editor || '—' }}</strong> · {{ formatFecha(meta.updated_at) }}
                </p>
            </template>
            <div v-else class="flex flex-col items-center justify-center py-10 gap-2 text-center">
                <svg class="w-10 h-10 text-gray-200 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z" />
                </svg>
                <p class="text-sm text-gray-400">Este grupo de permisos aún no tiene una descripción.</p>
            </div>
        </div>

        <!-- ══ Footer con acciones ══ -->
        <template #footer>
            <PrimaryButton
                variant="soft"
                color="gray"
                size="md"
                rounded="xl"
                @click="$emit('close')"
            >
                Cerrar
            </PrimaryButton>

            <PrimaryButton
                v-if="canWrite && meta"
                variant="outline"
                color="rose"
                size="md"
                rounded="xl"
                :loading="deleting"
                @click="onDelete"
            >
                {{ deleting ? 'Borrando…' : 'Borrar' }}
            </PrimaryButton>

            <PrimaryButton
                v-if="canWrite"
                variant="solid"
                color="indigo"
                size="md"
                rounded="xl"
                :loading="saving"
                @click="onSave"
            >
                {{ saving ? 'Guardando…' : 'Guardar' }}
            </PrimaryButton>
        </template>
    </ModalView>
</template>

<script setup>
import ModalView from '@/Components/Modals/ModalView.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    module: { type: Object, default: null }, // { id, nombre }
    comment: { type: Object, default: null }, // { module_id, body, editor, updated_at } | null
    canWrite: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'saved', 'deleted']);

const body = ref('');
const saving = ref(false);
const deleting = ref(false);

// El comentario existente (para meta de edición y estado del botón Borrar).
const meta = computed(() => props.comment || null);

// Guardar habilitado: hay texto, no está guardando y cambió respecto a lo persistido.
const canSave = computed(() => {
    const trimmed = body.value.trim();
    if (!trimmed || saving.value) return false;
    return trimmed !== (props.comment?.body ?? '').trim();
});

// Sincronizar el textarea al abrir o al cambiar de módulo.
watch(
    () => [props.show, props.module?.id],
    ([visible]) => {
        if (visible) {
            body.value = props.comment?.body ?? '';
        }
    },
    { immediate: true }
);

function formatFecha(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('es-PE', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    } catch (e) {
        return iso;
    }
}

async function onSave() {
    if (!props.module || !canSave.value) return;
    saving.value = true;
    try {
        const { data } = await window.axios.post(
            route('roles.modules.comment.store', { module: props.module.id }),
            { body: body.value.trim() }
        );
        window.showToast?.('Descripción guardada.', 'success', { timer: 3000 });
        emit('saved', data.comment);
        emit('close');
    } catch (e) {
        const msg = e?.response?.status === 403
            ? 'Solo un administrador puede editar los comentarios de permisos.'
            : (e?.response?.data?.errors?.body?.[0] || 'No se pudo guardar la descripción.');
        window.showError?.('Error', msg);
    } finally {
        saving.value = false;
    }
}

async function onDelete() {
    if (!props.module || !meta.value) return;
    const result = await window.showConfirm?.(
        '¿Borrar descripción?',
        `Se eliminará la descripción del grupo «${props.module.nombre}».`,
        { confirmButtonText: 'Sí, borrar', cancelButtonText: 'Cancelar', type: 'warning' }
    );
    if (result && !result.isConfirmed) return;

    deleting.value = true;
    try {
        await window.axios.delete(route('roles.modules.comment.destroy', { module: props.module.id }));
        window.showToast?.('Descripción eliminada.', 'success', { timer: 3000 });
        emit('deleted', props.module.id);
        emit('close');
    } catch (e) {
        const msg = e?.response?.status === 403
            ? 'Solo un administrador puede editar los comentarios de permisos.'
            : 'No se pudo eliminar la descripción.';
        window.showError?.('Error', msg);
    } finally {
        deleting.value = false;
    }
}
</script>
