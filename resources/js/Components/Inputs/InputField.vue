<script setup lang="ts">
// @ts-nocheck
import { computed, ref } from 'vue';

const showDateIcon = ref(false);
const dateInput = ref(null);

// Teléfono fijo a España: prefijo +34 (no editable)
const SPAIN_CODE = '+34';

function isDateType(type) {
    return type === 'date' || type === 'datetime-local';
}

function openDatePicker(event) {
    if (isDateType(props.type) && dateInput.value) {
        dateInput.value.showPicker && dateInput.value.showPicker();
    }
}

const props = defineProps({
    label: String,
    modelValue: [String, Number, Boolean],
    name: String,
    type: {
        type: String,
        default: 'text',
    },
    placeholder: String,
    autocomplete: {
        type: String,
        default: 'off',
    },
    min: [Number, String],
    error: String,
    required: Boolean,
    rows: {
        type: Number,
        default: 4,
    },
    spacing: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue', 'phone:meta']);

// Extrae la parte local (máx. 9 dígitos) de un valor que puede venir
// como "+34 600123456", "34600123456" o "600123456".
function toLocalNumber(value) {
    // Quitar el prefijo de país "+34"/"34" del inicio (lo pinta el badge,
    // nunca el input). Es seguro: ningún nº español empieza por "34".
    const local = String(value ?? '')
        .trim()
        .replace(/^\+?34[\s-]*/, '');
    // Dejar solo dígitos, máximo 9 (formato España)
    return local.replace(/\D/g, '').slice(0, 9);
}

// Lo que se muestra en el input: solo el número local, sin prefijo
const localNumber = computed(() => toLocalNumber(props.modelValue));

function handlePhoneInput(e) {
    // Solo números de España: dígitos, máximo 9
    const local = e.target.value.replace(/\D/g, '').slice(0, 9);
    // Reflejar el saneo de inmediato (evita que queden letras/símbolos)
    if (e.target.value !== local) {
        e.target.value = local;
    }
    const full = local ? `${SPAIN_CODE} ${local}` : '';
    emit('update:modelValue', full);
    emit('phone:meta', full);
}
</script>

<template>
    <div :class="{ 'mb-3': spacing }">
        <label
            v-if="label"
            class="block text-sm font-medium text-gray-700 mb-1"
        >
            {{ label }}
        </label>

        <template v-if="type === 'textarea'">
            <textarea
                :name="name"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :rows="rows"
                :value="modelValue"
                @input="emit('update:modelValue', $event.target.value)"
            ></textarea>
        </template>

        <template v-else-if="type === 'phone'">
            <div class="flex items-center">
                <!-- Prefijo España fijo (no editable) -->
                <span
                    class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2.5 border border-e-0 border-default-medium bg-neutral-secondary-medium text-heading text-sm rounded-s-base select-none cursor-default"
                    title="España (+34)"
                >
                    <span aria-hidden="true">🇪🇸</span>
                    <span class="font-medium">+34</span>
                </span>

                <input
                    type="tel"
                    inputmode="numeric"
                    maxlength="9"
                    :name="name"
                    :placeholder="placeholder"
                    :autocomplete="autocomplete"
                    :value="localNumber"
                    @input="handlePhoneInput"
                    class="flex-1 z-20 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-e-base focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand block px-3 py-2.5 placeholder:text-body"
                />
            </div>
        </template>

        <template v-else>
            <input
                :type="type"
                :name="name"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :min="min"
                :value="modelValue"
                @input="emit('update:modelValue', $event.target.value)"
                @mousedown="
                    isDateType(type) ? $event.preventDefault() : null;
                    openDatePicker($event);
                "
                ref="dateInput"
            />
        </template>

        <div v-if="error" class="text-red-500 text-xs mt-1">
            {{ error }}
        </div>
    </div>
</template>
