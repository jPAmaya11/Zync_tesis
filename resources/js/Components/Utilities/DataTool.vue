<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    show: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        v-if="show"
        class="toolTip invisible absolute opacity-0 transition-opacity duration-300 group-hover:visible group-hover:opacity-100 left-full top-1/2 z-50"
        style="
            pointer-events: none;
            transform: translateY(-50%) translateX(12px);
        "
    >
        <div class="relative flex items-center justify-center">
            <!-- Flecha detrás del texto -->
            <span
                class="absolute left-0 -translate-x-full top-1/2 -mt-2 w-0 h-0 tooltip-arrow"
                style="
                    border-top: 8px solid transparent;
                    border-bottom: 8px solid transparent;
                    filter: drop-shadow(0 2px 6px rgba(80, 80, 180, 0.12));
                    left: 6px;
                    z-index: 1;
                "
            ></span>
            <!-- Texto del tooltip -->
            <div
                class="bgPrincipal whitespace-nowrap rounded-xl px-5 py-2 text-sm text-white shadow-xl border border-white/30 dark:border-white/20 relative"
                style="
                    min-width: max-content;
                    box-shadow: 0 4px 24px 0 rgba(80, 80, 180, 0.18);
                    border-radius: 14px;
                    z-index: 2;
                "
            >
                {{ label }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.tooltip-arrow {
    border-right: 12px solid var(--colorPrincipal);
}
</style>
