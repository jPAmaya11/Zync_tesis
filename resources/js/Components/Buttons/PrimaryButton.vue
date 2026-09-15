<template>
    <button
        :type="type || undefined"
        :disabled="disabled || loading || undefined"
        :class="computedClasses"
    >
        <svg
            v-if="loading"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            :class="['animate-spin', spinnerSize]"
            aria-hidden="true"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
        </svg>
        <slot />
    </button>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    /** 'solid' | 'outline' | 'soft' | 'ghost' */
    variant: { type: String, default: "" },
    /** 'gray' | 'red' | 'rose' | 'pink' | 'orange' | 'amber' | 'emerald' | 'teal' | 'green' | 'sky' | 'blue' | 'indigo' | 'violet' | 'purple' */
    color: { type: String, default: "" },
    /** 'xs' | 'sm' | 'md' | 'lg' | 'xl' */
    size: { type: String, default: "" },
    iconOnly: { type: Boolean, default: false },
    /** width 100% */
    block: { type: Boolean, default: false },
    /** 'none' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full' */
    rounded: { type: String, default: "" },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: "" },
    /** disables built-in styling, leaves only structural <button> behavior */
    unstyled: { type: Boolean, default: false },
});

const LEGACY_CLASS =
    "inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900";

const isLegacy = computed(
    () =>
        !props.unstyled &&
        !props.variant &&
        !props.color &&
        !props.size &&
        !props.iconOnly &&
        !props.block &&
        !props.rounded &&
        !props.loading,
);

const SIZE_MAP = {
    xs: "px-2 py-1 text-[10px] gap-1",
    sm: "px-3 py-1.5 text-xs gap-1.5",
    md: "px-4 py-2 text-xs gap-2",
    lg: "px-5 py-2.5 text-sm gap-2",
    xl: "px-10 py-3.5 text-sm gap-2",
};

const ICON_ONLY_SIZE_MAP = {
    xs: "w-7 h-7 text-xs",
    sm: "w-8 h-8 text-xs",
    md: "w-9 h-9 text-sm",
    lg: "w-10 h-10 text-sm",
    xl: "w-12 h-12 text-base",
};

const SPINNER_SIZE_MAP = {
    xs: "w-3 h-3",
    sm: "w-3.5 h-3.5",
    md: "w-4 h-4",
    lg: "w-4 h-4",
    xl: "w-5 h-5",
};

const ROUNDED_MAP = {
    none: "rounded-none",
    sm: "rounded-sm",
    md: "rounded-md",
    lg: "rounded-lg",
    xl: "rounded-xl",
    "2xl": "rounded-2xl",
    full: "rounded-full",
};

const SOLID_COLOR_MAP = {
    gray: "bg-gray-800 text-white hover:bg-gray-700 focus:ring-gray-500",
    red: "bg-red-500 text-white hover:bg-red-600 focus:ring-red-400",
    rose: "bg-rose-500 text-white hover:bg-rose-600 focus:ring-rose-400",
    pink: "bg-pink-500 text-white hover:bg-pink-600 focus:ring-pink-400",
    orange: "bg-orange-500 text-white hover:bg-orange-600 focus:ring-orange-400",
    amber: "bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-400",
    emerald:
        "bg-emerald-500 text-white hover:bg-emerald-600 focus:ring-emerald-400",
    teal: "bg-teal-500 text-white hover:bg-teal-600 focus:ring-teal-400",
    green: "bg-green-500 text-white hover:bg-green-600 focus:ring-green-400",
    sky: "bg-sky-500 text-white hover:bg-sky-600 focus:ring-sky-400",
    blue: "bg-blue-500 text-white hover:bg-blue-600 focus:ring-blue-400",
    indigo: "bg-indigo-600 text-white hover:bg-indigo-600 focus:ring-indigo-400",
    violet: "bg-violet-500 text-white hover:bg-violet-600 focus:ring-violet-400",
    purple: "bg-purple-500 text-white hover:bg-purple-600 focus:ring-purple-400",
};

const OUTLINE_COLOR_MAP = {
    gray: "border border-gray-300 text-gray-600 hover:bg-gray-50",
    red: "border border-red-200 text-red-500 hover:bg-red-500 hover:text-white",
    rose: "border border-rose-200 text-rose-500 hover:bg-rose-50",
    pink: "border border-pink-200 text-pink-500 hover:bg-pink-50",
    orange: "border border-orange-200 text-orange-600 hover:bg-orange-50",
    amber: "border border-amber-200 text-amber-600 hover:bg-amber-50",
    emerald: "border border-emerald-200 text-emerald-600 hover:bg-emerald-50",
    teal: "border border-teal-200 text-teal-600 hover:bg-teal-50",
    green: "border border-green-200 text-green-600 hover:bg-green-50",
    sky: "border border-sky-200 text-sky-600 hover:bg-sky-50",
    blue: "border border-blue-200 text-blue-600 hover:bg-blue-50",
    indigo: "border border-indigo-200 text-indigo-600 hover:bg-indigo-50",
    violet: "border border-violet-200 text-violet-600 hover:bg-violet-50",
    purple: "border border-purple-200 text-purple-600 hover:bg-purple-50",
};

const SOFT_COLOR_MAP = {
    gray: "bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100",
    red: "bg-red-50 text-red-500 border border-red-200 hover:bg-red-500 hover:text-white",
    rose: "bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100",
    pink: "bg-pink-50 text-pink-600 border border-pink-200 hover:bg-pink-100",
    orange:
        "bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100",
    amber: "bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100",
    emerald:
        "bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100",
    teal: "bg-teal-50 text-teal-700 border border-teal-200 hover:bg-teal-100",
    green: "bg-green-50 text-green-700 border border-green-200 hover:bg-green-100",
    sky: "bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100",
    blue: "bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100",
    indigo:
        "bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100",
    violet:
        "bg-violet-50 text-violet-700 border border-violet-200 hover:bg-violet-100",
    purple:
        "bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100",
};

const GHOST_COLOR_MAP = {
    gray: "text-gray-600 hover:bg-gray-100 hover:text-gray-800",
    red: "text-red-500 hover:bg-red-50",
    rose: "text-rose-500 hover:bg-rose-50",
    pink: "text-pink-500 hover:bg-pink-50",
    orange: "text-orange-600 hover:bg-orange-50",
    amber: "text-amber-600 hover:bg-amber-50",
    emerald: "text-emerald-600 hover:bg-emerald-50",
    teal: "text-teal-600 hover:bg-teal-50",
    green: "text-green-600 hover:bg-green-50",
    sky: "text-sky-600 hover:bg-sky-50",
    blue: "text-blue-600 hover:bg-blue-50",
    indigo: "text-indigo-600 hover:bg-indigo-50",
    violet: "text-violet-600 hover:bg-violet-50",
    purple: "text-purple-600 hover:bg-purple-50",
};

const VARIANT_MAP = {
    solid: SOLID_COLOR_MAP,
    outline: OUTLINE_COLOR_MAP,
    soft: SOFT_COLOR_MAP,
    ghost: GHOST_COLOR_MAP,
};

const computedClasses = computed(() => {
    if (isLegacy.value) return LEGACY_CLASS;
    if (props.unstyled) return "";

    const variant = props.variant || "solid";
    const color = props.color || "gray";
    const size = props.size || "md";
    const rounded = props.rounded || "lg";

    const colorMap = VARIANT_MAP[variant] || SOLID_COLOR_MAP;
    const colorClasses = colorMap[color] || colorMap.gray;

    const sizeClasses = props.iconOnly
        ? ICON_ONLY_SIZE_MAP[size] || ICON_ONLY_SIZE_MAP.md
        : SIZE_MAP[size] || SIZE_MAP.md;

    const roundedClass = ROUNDED_MAP[rounded] || ROUNDED_MAP.lg;

    return [
        "inline-flex items-center justify-center font-semibold transition-colors",
        "focus:outline-none focus:ring-2 focus:ring-offset-1",
        sizeClasses,
        colorClasses,
        roundedClass,
        props.block ? "w-full" : "",
        props.disabled || props.loading
            ? "opacity-50 cursor-not-allowed"
            : "",
    ]
        .filter(Boolean)
        .join(" ");
});

const spinnerSize = computed(() => {
    const size = props.size || "md";
    return SPINNER_SIZE_MAP[size] || SPINNER_SIZE_MAP.md;
});
</script>
