<script setup>
import ApplicationLogo from "@/Components/Layout/ApplicationLogo.vue";
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div class="appLogo">
            <Link href="/">
                <ApplicationLogo class="fill-current text-gray-500" />
            </Link>
        </div>

        <div
            class="mt-6 overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg w-full s"
        >
            <slot />
        </div>
    </div>
</template>
