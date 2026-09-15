<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Añadir Campo"
        size="lg"
        :hide-footer="true"
        @close="close"
    >
        <template #subtitle>Elige un campo existente o crea uno nuevo</template>

        <div class="space-y-5">
            <!-- Columnas del catálogo -->
            <div v-if="availableCatalog.length">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">
                    Columnas del sistema
                </p>
                <div class="space-y-1">
                    <div
                        v-for="col in availableCatalog"
                        :key="col.key"
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-md bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ col.name }}</p>
                                <p class="text-[11px] text-gray-400">{{ col.type }}</p>
                            </div>
                        </div>
                        <button
                            @click="onAddCatalog(col)"
                            class="px-3 py-1 text-xs font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition"
                        >Añadir</button>
                    </div>
                </div>
            </div>

            <!-- Campos personalizados existentes -->
            <div v-if="availableCustomFields.length">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">
                    Campos personalizados del proyecto
                </p>
                <div class="space-y-1">
                    <div
                        v-for="field in availableCustomFields"
                        :key="field.column_key"
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-md bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-violet-600 dark:text-violet-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l5.654-4.654m5.292-4.616a3 3 0 014.243 4.243L15.75 13.5m-5.33-4.33a3 3 0 014.243 4.243" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ field.name }}</p>
                                <p class="text-[11px] text-gray-400">{{ field.type }}</p>
                            </div>
                        </div>
                        <button
                            @click="onAddCustomField(field)"
                            class="px-3 py-1 text-xs font-semibold rounded-md bg-violet-600 text-white hover:bg-violet-700 transition"
                        >Añadir</button>
                    </div>
                </div>
            </div>

            <!-- Separador -->
            <div
                v-if="availableCatalog.length || availableCustomFields.length"
                class="border-t border-gray-100 dark:border-gray-800"
            ></div>

            <!-- Formulario nuevo campo -->
            <div class="space-y-4">
                <p
                    v-if="availableCatalog.length || availableCustomFields.length"
                    class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                >
                    O crea un campo nuevo
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">
                        Nombre <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        maxlength="100"
                        placeholder="Ej: Cliente, Región, Número de ticket..."
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">
                        Tipo <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="ft in FIELD_TYPES"
                            :key="ft.value"
                            type="button"
                            @click="form.type = ft.value"
                            :title="ft.description"
                            :class="[
                                'group relative flex flex-col items-center justify-center gap-1.5 px-2 py-3 rounded-lg border text-center transition-colors duration-150 select-none',
                                form.type === ft.value
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-500/30'
                                    : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-indigo-300 dark:hover:border-indigo-600 hover:bg-gray-50 dark:hover:bg-gray-800/60',
                            ]"
                        >
                            <svg
                                class="w-5 h-5"
                                :class="
                                    form.type === ft.value
                                        ? 'text-indigo-600 dark:text-indigo-400'
                                        : 'text-gray-500 dark:text-gray-400 group-hover:text-indigo-500 dark:group-hover:text-indigo-400'
                                "
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.5"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" :d="ft.path" />
                            </svg>
                            <span class="text-[11px] font-semibold leading-tight">{{ ft.label }}</span>
                            <span
                                v-if="ft.beta"
                                class="absolute top-1 right-1 text-[8px] font-bold bg-amber-400 text-amber-900 px-1 py-0.5 rounded leading-none"
                            >β</span>
                        </button>
                    </div>
                    <p class="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                        {{ FIELD_TYPES.find((f) => f.value === form.type)?.description }}
                    </p>
                </div>

                <div v-if="form.type === 'dropdown'">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                        Opciones (separadas por coma)
                    </label>
                    <input
                        v-model="optionsText"
                        type="text"
                        placeholder="Ej: Opción A, Opción B, Opción C"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                    />
                </div>

                <PrimaryButton
                    variant="solid"
                    color="indigo"
                    size="md"
                    rounded="lg"
                    block
                    :loading="loading"
                    :disabled="loading || !form.name.trim()"
                    @click="onSaveNewField"
                >
                    {{ loading ? 'Creando...' : 'Crear y añadir Campos' }}
                </PrimaryButton>
            </div>
        </div>
    </ModalView>
</template>

<script setup>
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectKey: { type: String, default: null },
    availableCatalog: { type: Array, default: () => [] },
    availableCustomFields: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:show', 'added', 'error']);

const FIELD_TYPES = [
    { value: 'text',      label: 'Texto corto',    description: 'Texto corto de una línea',          beta: false, path: 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12' },
    { value: 'paragraph', label: 'Párrafo',        description: 'Bloque de texto multilínea',        beta: false, path: 'M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h12' },
    { value: 'timestamp', label: 'Fecha y hora',   description: 'Fecha y hora',                       beta: false, path: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' },
    { value: 'dropdown',  label: 'Desplegable',    description: 'Lista de opciones selección única', beta: false, path: 'M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9' },
    { value: 'date',      label: 'Fecha',          description: 'Selector de calendario',             beta: false, path: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5' },
    { value: 'number',    label: 'Número',         description: 'Valores numéricos',                  beta: false, path: 'M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5' },
    { value: 'labels',    label: 'Etiquetas',      description: 'Etiquetas organizativas',            beta: false, path: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z M6 6h.008v.008H6V6z' },
    { value: 'checkbox',  label: 'Checkbox',       description: 'Valor binario (Sí/No)',              beta: false, path: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { value: 'people',    label: 'Personas',       description: 'Asignación de usuarios',             beta: false, path: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
    { value: 'url',       label: 'Enlace',         description: 'Enlace web',                          beta: false, path: 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244' },
];

const form = ref({ name: '', type: 'text' });
const optionsText = ref('');
const loading = ref(false);

watch(() => props.show, (val) => {
    if (val) {
        form.value = { name: '', type: 'text' };
        optionsText.value = '';
    }
});

function close() {
    emit('update:show', false);
}

function onAddCatalog(col) {
    emit('added', { kind: 'catalog', column: { key: col.key, name: col.name } });
    close();
}

function onAddCustomField(field) {
    emit('added', {
        kind: 'existing-field',
        column: {
            key: field.column_key,
            name: field.name,
            field_id: field.id,
            type: field.type,
        },
        field,
    });
    close();
}

async function onSaveNewField() {
    if (!props.projectKey || !form.value.name.trim()) return;
    loading.value = true;
    try {
        const payload = {
            name: form.value.name.trim(),
            type: form.value.type,
            options: optionsText.value
                ? optionsText.value
                      .split(',')
                      .map((s) => s.trim())
                      .filter(Boolean)
                : null,
        };
        const resp = await axios.post(
            route('gestion-proyectos.custom-fields.store', props.projectKey),
            payload,
            { validateStatus: () => true }
        );

        if (resp.status !== 200 && resp.status !== 201) {
            emit('error', resp.data?.message ?? 'Error al crear el campo.');
            return;
        }

        const field = resp.data;
        emit('added', {
            kind: 'new-field',
            column: {
                key: field.column_key,
                name: field.name,
                field_id: field.id,
                type: field.type,
            },
            field,
        });
        close();
    } catch {
        emit('error', 'Error de conexión al crear el campo.');
    } finally {
        loading.value = false;
    }
}
</script>
