<template>
    <Link
        v-if="href"
        :href="href"
        class="flex items-center justify-center w-10 h-10 rounded-lg border border-[#5f5ffffc] text-[#5f5ffffc] hover:bg-[#5f5ffffc] hover:text-white transition-all duration-300 group"
    >
        <svg
            class="w-5 h-5 transform group-hover:-translate-x-1 transition-transform duration-300"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M18 12H6m0 0l4-4m-4 4l4 4"
            />
            <circle cx="6" cy="12" r="1" fill="currentColor" opacity="0.8" />
            <circle cx="18" cy="12" r="1" fill="currentColor" opacity="0.6" />
        </svg>
    </Link>
    <button
        v-else
        @click="goBack"
        type="button"
        class="flex items-center justify-center w-10 h-10 rounded-lg border border-[#5f5ffffc] text-[#5f5ffffc] hover:bg-[#5f5ffffc] hover:text-white transition-all duration-300 group"
    >
        <svg
            class="w-5 h-5 transform group-hover:-translate-x-1 transition-transform duration-300"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M18 12H6m0 0l4-4m-4 4l4 4"
            />
            <circle cx="6" cy="12" r="1" fill="currentColor" opacity="0.8" />
            <circle cx="18" cy="12" r="1" fill="currentColor" opacity="0.6" />
        </svg>
    </button>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    href: {
        type: String,
        required: false,
        default: null,
    },
});

function goBack() {
    window.history.back();
}
</script>
