<template>
    <span
        :class="[
            'inline-flex items-center justify-center rounded-full shrink-0 overflow-hidden',
            sizeClasses,
            !shouldShowImage && initialsBg,
            ringClasses,
        ]"
        :title="name || undefined"
    >
        <img
            v-if="shouldShowImage"
            :src="src"
            :alt="name || 'Avatar'"
            class="w-full h-full object-cover"
            loading="lazy"
            @error="onImageError"
            @load="onImageLoad"
        />
        <span
            v-else
            :class="['font-bold text-white uppercase', textSizeClasses]"
        >{{ initials }}</span>
    </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    /** URL del avatar — si está rota, fallback automático a iniciales. */
    src: { type: String, default: null },
    /** Nombre del usuario para iniciales + alt + title. */
    name: { type: String, default: '' },
    /** Tamaño: xs (16), sm (20), md (28), lg (36), xl (48). */
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl', '2xl'].includes(v),
    },
    /** Anillo/borde alrededor del avatar (útil cuando se apila sobre fondos). */
    ring: { type: Boolean, default: false },
});

// Track de URLs que fallaron para no reintentar
const hasError = ref(false);

// Si la URL cambia, resetear el error
watch(() => props.src, () => { hasError.value = false; });

const shouldShowImage = computed(() => !!props.src && !hasError.value);

const initials = computed(() => {
    if (!props.name) return '?';
    return props.name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
});

const sizeClasses = computed(() => {
    return {
        xs: 'w-4 h-4',
        sm: 'w-5 h-5',
        md: 'w-7 h-7',
        lg: 'w-9 h-9',
        xl: 'w-12 h-12',
        '2xl': 'w-16 h-16',
    }[props.size] ?? 'w-7 h-7';
});

const textSizeClasses = computed(() => {
    return {
        xs: 'text-[8px]',
        sm: 'text-[9px]',
        md: 'text-[10px]',
        lg: 'text-xs',
        xl: 'text-sm',
        '2xl': 'text-base',
    }[props.size] ?? 'text-[10px]';
});

// Color de fondo determinístico por nombre — mismo usuario → mismo color
const initialsBg = computed(() => {
    if (!props.name) return 'bg-gray-400';
    const palette = [
        'bg-indigo-500',
        'bg-purple-500',
        'bg-blue-500',
        'bg-teal-500',
        'bg-emerald-500',
        'bg-amber-500',
        'bg-rose-500',
        'bg-pink-500',
    ];
    let hash = 0;
    for (let i = 0; i < props.name.length; i++) {
        hash = (hash << 5) - hash + props.name.charCodeAt(i);
        hash |= 0;
    }
    return palette[Math.abs(hash) % palette.length];
});

const ringClasses = computed(() =>
    props.ring ? 'border-2 border-white dark:border-gray-800' : ''
);

function onImageError() {
    // La URL es válida pero la imagen no carga (404, CORS, etc.)
    // → ocultamos el <img> y mostramos iniciales como fallback.
    hasError.value = true;
}

function onImageLoad() {
    // La imagen cargó OK — asegurarnos de que hasError sea false
    if (hasError.value) hasError.value = false;
}
</script>
