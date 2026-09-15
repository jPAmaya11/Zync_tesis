<script setup>
/**
 * TabNav — Componente reutilizable de pestañas con pill deslizante animada.
 *
 * Props:
 * - tabs: Array de objetos { key, label, count? }
 * - modelValue: key del tab activo (v-model)
 *
 * Emits:
 * - update:modelValue: al cambiar de tab
 *
 * Slots:
 * - icon-{key}: slot nombrado para inyectar SVG personalizado por tab
 */
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    tabs: {
        type: Array,
        required: true,
        validator: (tabs) => tabs.every((t) => t.key && t.label),
    },
    modelValue: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

// Refs para medir posiciones de los botones y animar la pill
const tabRefs = ref([]);
const pillStyle = ref({});

function setTabRef(el, index) {
    if (el) tabRefs.value[index] = el;
}

function updatePill() {
    const activeIndex = props.tabs.findIndex((t) => t.key === props.modelValue);
    const el = tabRefs.value[activeIndex];
    if (!el) return;
    pillStyle.value = {
        width: `${el.offsetWidth}px`,
        height: `${el.offsetHeight}px`,
        transform: `translateX(${el.offsetLeft}px)`,
    };
}

watch(
    () => props.modelValue,
    () => nextTick(updatePill)
);
onMounted(() => nextTick(updatePill));

// Observar cambios de tamaño (responsive)
onMounted(() => {
    const observer = new ResizeObserver(() => updatePill());
    const container = tabRefs.value[0]?.parentElement;
    if (container) observer.observe(container);
});
</script>

<template>
    <div
        class="relative inline-flex items-center gap-0.5 p-1 bg-gray-100/80 backdrop-blur-sm rounded-2xl"
    >
        <!-- Pill animada (blob) -->
        <div
            class="absolute top-1 left-0 rounded-xl bg-white shadow-md ring-1 ring-black/[0.04] transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] pointer-events-none"
            :style="pillStyle"
        />

        <!-- Botones -->
        <button
            v-for="(tab, index) in tabs"
            :key="tab.key"
            :ref="(el) => setTabRef(el, index)"
            type="button"
            @click="emit('update:modelValue', tab.key)"
            :class="[
                'group relative z-10 flex items-center gap-2 px-4 py-2 text-[13px] font-semibold rounded-xl transition-colors duration-200 outline-none select-none',
                'focus-visible:ring-2 focus-visible:ring-indigo-500/40 focus-visible:ring-offset-1',
                modelValue === tab.key
                    ? 'text-gray-900'
                    : 'text-gray-500 hover:text-gray-700',
            ]"
        >
            <!-- Icono -->
            <span
                :class="[
                    'flex-shrink-0 transition-colors duration-200',
                    modelValue === tab.key
                        ? 'text-indigo-600'
                        : 'text-gray-400 group-hover:text-gray-500',
                ]"
            >
                <slot :name="'icon-' + tab.key" />
            </span>

            <!-- Label -->
            <span class="whitespace-nowrap">{{ tab.label }}</span>

            <!-- Badge -->
            <span
                v-if="tab.count !== undefined && tab.count !== null"
                :class="[
                    'min-w-[20px] text-center px-1.5 py-0.5 rounded-lg text-[11px] font-bold tabular-nums leading-none transition-all duration-200',
                    modelValue === tab.key
                        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-200'
                        : 'bg-gray-200/80 text-gray-500 group-hover:bg-gray-300/70',
                ]"
            >
                {{ tab.count }}
            </span>
        </button>
    </div>
</template>
