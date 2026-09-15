<script setup>
import { ref } from "vue";

const props = defineProps({
    modelValue: String,
    label: String,
    error: String,
    placeholder: String,
    required: Boolean,
    name: String,
});

const emit = defineEmits(["update:modelValue"]);
const visible = ref(false);
</script>

<template>
    <div class="mb-3">
        <label
            v-if="label"
            class="block text-sm font-medium text-gray-700 mb-1"
        >
            {{ label }}
        </label>
        <!-- Inputs fantasma para engañar autofill -->
        <input
            type="text"
            name="fake-user"
            autocomplete="username"
            aria-hidden="true"
            tabindex="-1"
            style="
                position: absolute;
                opacity: 0;
                height: 0;
                width: 0;
                pointer-events: none;
                z-index: -1;
            "
        />
        <input
            type="password"
            name="fake-pass"
            autocomplete="new-password"
            aria-hidden="true"
            tabindex="-1"
            style="
                position: absolute;
                opacity: 0;
                height: 0;
                width: 0;
                pointer-events: none;
                z-index: -1;
            "
        />

        <div class="relative">
            <input
                :type="visible ? 'text' : 'password'"
                :name="name"
                :value="modelValue"
                @input="emit('update:modelValue', $event.target.value)"
                :placeholder="placeholder"
                :minlength="6"
                autocomplete="new-password"
                class="border border-gray-300 px-3 py-2 rounded-lg w-full focus:ring-2 focus:ring-indigo-400 focus:outline-none pr-10"
            />

            <!-- Ojito -->
            <button
                type="button"
                @click="visible = !visible"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
                tabindex="-1"
            >
                <svg
                    v-if="!visible"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                    />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.965 9.965 0 012.021-3.368M9.88 9.88a3 3 0 104.24 4.24M3 3l18 18"
                    />
                </svg>
            </button>
        </div>

        <div v-if="error" class="text-red-500 text-xs mt-1">
            {{ error }}
        </div>
    </div>
</template>
