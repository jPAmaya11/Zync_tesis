<template>
    <div style="display: inline-block">
        <img
            :src="logoSrc"
            alt="Zync"
            style="
                object-fit: contain;
                display: block;
                width: 100%;
                height: inherit;
            "
        />
    </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    variant: {
        type: String,
        default: "default", // puede ser "default" o "white"
    },
});

const logoSrc = computed(() => {
    return props.variant === "white"
        ? "/img/logo-white.png"
        : "/img/logo.png";
});
</script>

<style scoped>
/* Estilos básicos sin animaciones */
</style>
