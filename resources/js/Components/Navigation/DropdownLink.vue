<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="flex items-center w-full px-4 py-2.5 text-sm text-gray-600 dark:text-gray-300 border-l-2 border-transparent hover:border-[var(--colorPrincipal)] hover:bg-gray-50 dark:hover:bg-white/[0.04] hover:text-gray-900 dark:hover:text-white transition-all duration-150 focus:outline-none"
    >
        <slot />
    </Link>
</template>
