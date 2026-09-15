<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    align: { type: String, default: 'right' },
    minWidth: { type: String, default: '50px' },
    maxWidth: { type: String, default: '220px' },
    contentClasses: { type: String, default: '' },
    search: { type: Boolean, default: false },
    searchPlaceholder: { type: String, default: 'Buscar...' },
});

const searchTerm = ref('');
const open = ref(false);

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') open.value = false;
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const alignmentClasses = computed(() => {
    if (props.align === 'left') return 'ltr:origin-top-left rtl:origin-top-right start-0';
    if (props.align === 'right') return 'ltr:origin-top-right rtl:origin-top-left end-0';
    return 'origin-top';
});
</script>

<template>
    <div class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <div v-show="open" class="fixed inset-0 z-40" @click="open = false" />

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1 scale-[0.98]"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-[0.98]"
        >
            <div
                v-show="open"
                class="absolute z-50 mt-2 rounded-xl overflow-hidden shadow-xl ring-1 ring-black/5 dark:ring-white/10 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/[0.08]"
                :class="[alignmentClasses, contentClasses]"
                :style="{ minWidth: props.minWidth, maxWidth: props.maxWidth }"
                @click="open = false"
            >
                <template v-if="props.search">
                    <div class="px-3 pt-3 pb-2 sticky top-0 z-10 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-white/[0.06]">
                        <input
                            v-model="searchTerm"
                            type="text"
                            :placeholder="props.searchPlaceholder"
                            class="w-full px-3 py-2 text-sm rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[var(--colorPrincipal)]/30 focus:border-[var(--colorPrincipal)] transition-colors"
                            @click.stop
                        />
                    </div>
                </template>

                <div class="py-1 overflow-y-auto max-h-[240px]">
                    <slot name="content" :search="searchTerm" />
                </div>
            </div>
        </Transition>
    </div>
</template>
