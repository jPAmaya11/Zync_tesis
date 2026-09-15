<script setup>
/**
 * Avatar de usuario para la tabla SCRUM. Si `admin` es true (administrador/propietario
 * del espacio activo o admin global), el avatar lleva un anillo ámbar y un tooltip
 * "Administrador del espacio" al pasar el mouse. Solo renderiza el círculo del avatar;
 * el nombre va aparte en la tabla.
 */
defineProps({
    user:     { type: Object,  default: null },
    admin:    { type: Boolean, default: false },
    size:     { type: String,  default: 'w-6 h-6' },
    fallback: { type: String,  default: 'bg-indigo-500' },
});

function initials(name) {
    return (name || '?').split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
}
</script>

<template>
    <span
        class="relative inline-flex shrink-0"
        :title="admin ? 'Administrador del espacio' : null"
    >
        <img
            v-if="user?.avatar_url"
            :src="user.avatar_url"
            loading="lazy"
            :class="[size, 'rounded-full object-cover aspect-square', admin && 'ring-2 ring-amber-400 dark:ring-amber-500']"
            alt=""
        />
        <span
            v-else
            :class="[size, fallback, 'rounded-full text-white text-[10px] font-bold flex items-center justify-center', admin && 'ring-2 ring-amber-400 dark:ring-amber-500']"
        >{{ initials(user?.display_name) }}</span>
    </span>
</template>
