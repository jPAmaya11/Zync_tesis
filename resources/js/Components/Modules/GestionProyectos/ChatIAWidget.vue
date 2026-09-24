<script setup>
/**
 * Widget flotante del asistente conversacional con IA (Google Gemini).
 * Capítulo 3 de la tesis — tabla chat_ia. Visible para CUALQUIER usuario
 * autenticado, sin distinción de rol (Administrador, Jefe de Proyecto,
 * Desarrollador, Diseñador, Tester pueden usarlo por igual).
 */
import { ref, nextTick, onMounted } from 'vue';
import axios from 'axios';

const abierto = ref(false);
const cargandoHistorial = ref(false);
const enviando = ref(false);
const mensaje = ref('');
const errorMsg = ref('');
const mensajes = ref([]); // [{ id, rol: 'usuario'|'asistente', texto }]
const scrollBox = ref(null);

async function alAbrir() {
    abierto.value = !abierto.value;
    if (abierto.value && mensajes.value.length === 0) {
        await cargarHistorial();
    }
}

async function cargarHistorial() {
    cargandoHistorial.value = true;
    errorMsg.value = '';
    try {
        const { data } = await axios.get(route('gestion-proyectos.chat-ia.index'));
        const historial = [];
        for (const turno of data.historial ?? []) {
            historial.push({ id: `${turno.id}-u`, rol: 'usuario', texto: turno.mensaje });
            if (turno.respuesta) {
                historial.push({ id: `${turno.id}-a`, rol: 'asistente', texto: turno.respuesta });
            }
        }
        mensajes.value = historial;
        await scrollAlFinal();
    } catch (e) {
        errorMsg.value = 'No se pudo cargar el historial del chat.';
    } finally {
        cargandoHistorial.value = false;
    }
}

async function enviarMensaje() {
    const texto = mensaje.value.trim();
    if (!texto || enviando.value) return;

    mensajes.value.push({ id: `local-${Date.now()}`, rol: 'usuario', texto });
    mensaje.value = '';
    errorMsg.value = '';
    enviando.value = true;
    await scrollAlFinal();

    try {
        const { data } = await axios.post(route('gestion-proyectos.chat-ia.store'), {
            mensaje: texto,
        });
        mensajes.value.push({
            id: `${data.turno.id}-a`,
            rol: 'asistente',
            texto: data.turno.respuesta,
        });
    } catch (e) {
        errorMsg.value =
            e.response?.data?.message ||
            'El asistente de IA no está disponible en este momento.';
    } finally {
        enviando.value = false;
        await scrollAlFinal();
    }
}

async function scrollAlFinal() {
    await nextTick();
    if (scrollBox.value) {
        scrollBox.value.scrollTop = scrollBox.value.scrollHeight;
    }
}
</script>

<template>
    <div class="fixed bottom-5 right-5 z-50 flex flex-col items-end">
        <!-- Panel de chat -->
        <transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div
                v-if="abierto"
                class="mb-3 flex h-[28rem] w-80 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    class="flex items-center justify-between bg-gradient-to-r from-indigo-600 to-cyan-600 px-4 py-3 text-white"
                >
                    <div>
                        <p class="text-sm font-semibold">Asistente Zync IA</p>
                        <p class="text-xs opacity-80">Impulsado por Google Gemini</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1 hover:bg-white/20"
                        @click="abierto = false"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div
                    ref="scrollBox"
                    class="flex-1 space-y-3 overflow-y-auto p-3 text-sm"
                >
                    <p v-if="cargandoHistorial" class="text-center text-xs text-gray-400">
                        Cargando conversación…
                    </p>

                    <p
                        v-if="!cargandoHistorial && mensajes.length === 0"
                        class="text-center text-xs text-gray-400"
                    >
                        Pregúntame sobre tus tareas asignadas, o cualquier duda del proyecto.
                    </p>

                    <div
                        v-for="m in mensajes"
                        :key="m.id"
                        :class="m.rol === 'usuario' ? 'flex justify-end' : 'flex justify-start'"
                    >
                        <div
                            :class="[
                                'max-w-[85%] whitespace-pre-wrap rounded-2xl px-3 py-2',
                                m.rol === 'usuario'
                                    ? 'bg-indigo-600 text-white rounded-br-sm'
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100 rounded-bl-sm',
                            ]"
                        >
                            {{ m.texto }}
                        </div>
                    </div>

                    <div v-if="enviando" class="flex justify-start">
                        <div class="rounded-2xl rounded-bl-sm bg-gray-100 px-3 py-2 text-gray-400 dark:bg-gray-700">
                            Escribiendo…
                        </div>
                    </div>

                    <p v-if="errorMsg" class="text-center text-xs text-red-500">{{ errorMsg }}</p>
                </div>

                <form class="flex items-center gap-2 border-t border-gray-200 p-2 dark:border-gray-700" @submit.prevent="enviarMensaje">
                    <input
                        v-model="mensaje"
                        type="text"
                        placeholder="Escribe tu mensaje…"
                        class="flex-1 rounded-full border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        :disabled="enviando"
                    />
                    <button
                        type="submit"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-white disabled:opacity-50"
                        :disabled="enviando || !mensaje.trim()"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.94 2.94a1.5 1.5 0 011.6-.34l13 5a1.5 1.5 0 010 2.8l-13 5a1.5 1.5 0 01-2-1.83L3.6 10 1.54 4.77a1.5 1.5 0 01.4-1.83z" />
                        </svg>
                    </button>
                </form>
            </div>
        </transition>

        <!-- Botón flotante -->
        <button
            type="button"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-cyan-600 text-white shadow-lg transition hover:scale-105"
            title="Asistente Zync IA"
            @click="alAbrir"
        >
            <svg v-if="!abierto" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8-1.34 0-2.61-.26-3.75-.72L3 20l1.05-3.16A7.94 7.94 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>
