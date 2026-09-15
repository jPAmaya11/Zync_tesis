<template>
    <!-- Capa oscura EXTRA para los modales SCRUM. Se teletransporta a body y se ubica
         justo DEBAJO del backdrop del ModalView (z-50), sumándose a él para un fondo
         más oscuro (sin tocar el componente compartido ModalView). pointer-events-none
         para no interferir con el clic-fuera del propio modal. -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-40 bg-slate-900/50 dark:bg-slate-950/60 pointer-events-none"
                aria-hidden="true"
            ></div>
        </Transition>
    </Teleport>
</template>

<script setup>
defineProps({
    /** Mismo `show` que el modal al que acompaña. */
    show: { type: Boolean, default: false },
});
</script>
