<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Sesiones MCP"
        size="2xl"
        :hide-footer="true"
        @close="close"
    >
        <template #subtitle>
            Asistentes de IA conectados a tu cuenta. Cada uno trabaja con tus mismos permisos.
        </template>

        <div class="space-y-5 py-1">
            <!-- ═══ URL del conector (se copia siempre desde aquí) ═══ -->
            <div class="rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-900/20 p-4">
                <div class="flex items-start gap-2 mb-2.5">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                    </svg>
                    <div>
                        <p class="text-sm font-bold text-indigo-800 dark:text-indigo-200">Dirección para conectar</p>
                        <p class="text-xs text-indigo-700/80 dark:text-indigo-300/80">
                            Pégala en Claude al añadir un conector personalizado. Luego inicia sesión con tu cuenta de siempre.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <code class="flex-1 px-3 py-2 text-xs font-mono break-all rounded-lg bg-white dark:bg-gray-900 border border-indigo-200 dark:border-indigo-800 text-gray-800 dark:text-gray-200">{{ mcpUrl }}</code>
                    <button
                        type="button"
                        @click="copy(mcpUrl)"
                        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" /></svg>
                        Copiar
                    </button>
                </div>
            </div>

            <!-- ═══ Cabecera de la lista ═══ -->
            <div class="flex items-center justify-between gap-3">
                <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Conectados
                    <span v-if="!loading && sessions.length" class="text-gray-400 dark:text-gray-500">· {{ sessions.length }}</span>
                </h4>
                <button
                    v-if="!loading && sessions.length"
                    type="button"
                    @click="disconnectAll"
                    :disabled="working"
                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 disabled:opacity-50 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                    </svg>
                    Desconectar todas
                </button>
            </div>

            <!-- ═══ Cargando ═══ -->
            <div v-if="loading" class="flex items-center justify-center gap-2 py-10 text-sm text-gray-400">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" /></svg>
                Buscando sesiones…
            </div>

            <!-- ═══ Estado vacío: se explica cómo conectar ═══ -->
            <div
                v-else-if="!sessions.length"
                class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/30 px-6 py-8 text-center"
            >
                <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mb-3 text-indigo-500">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-800 dark:text-gray-200">Todavía no hay nada conectado</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 max-w-sm mx-auto leading-relaxed">
                    Para conectar un asistente: copia la dirección de arriba, abre Claude, entra en
                    <strong>Conectores</strong>, elige <strong>Añadir conector personalizado</strong> y pégala.
                    Te pedirá iniciar sesión con tu cuenta de Zync y aparecerá aquí.
                </p>
            </div>

            <!-- ═══ Lista de sesiones ═══ -->
            <div v-else class="space-y-2">
                <div
                    v-for="s in sessions"
                    :key="s.id"
                    :class="[
                        'flex items-start justify-between gap-3 px-3.5 py-3 rounded-xl border transition-colors',
                        s.estado === 'expirada'
                            ? 'border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/30 opacity-75'
                            : 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-800/40',
                    ]"
                >
                    <div class="flex items-start gap-3 min-w-0">
                        <div
                            :class="[
                                'shrink-0 w-9 h-9 rounded-xl flex items-center justify-center',
                                s.estado === 'expirada'
                                    ? 'bg-gray-100 dark:bg-gray-800 text-gray-400'
                                    : 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400',
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">
                                    {{ s.name || 'Asistente sin nombre' }}
                                </p>
                                <span
                                    :class="[
                                        'text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md border',
                                        s.estado === 'expirada'
                                            ? 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
                                            : 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
                                    ]"
                                >{{ s.estado === 'expirada' ? 'Expirada' : 'Activa' }}</span>
                                <span
                                    v-if="s.es_oauth === false"
                                    class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md border bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800"
                                    title="Acceso creado a mano por un administrador, no desde un asistente de IA"
                                >Manual</span>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Último uso: <span class="font-medium text-gray-600 dark:text-gray-300">{{ relativo(s.last_used_at) }}</span>
                            </p>

                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5 truncate" :title="s.user_agent || ''">
                                {{ dispositivo(s.user_agent) }}
                                <template v-if="s.ip_address"> · IP {{ s.ip_address }}</template>
                            </p>

                            <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mt-1">
                                Conectado el {{ fecha(s.created_at) }}
                                <template v-if="s.expires_at">
                                    · {{ s.estado === 'expirada' ? 'Expiró el' : 'Expira el' }} {{ fecha(s.expires_at) }}
                                </template>
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="disconnect(s)"
                        :disabled="working"
                        class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-bold rounded-lg text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 disabled:opacity-50 transition-colors"
                        title="Cortar el acceso de este asistente"
                    >
                        <svg v-if="busyId === s.id" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" /></svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                        </svg>
                        <span class="hidden sm:inline">Desconectar</span>
                    </button>
                </div>
            </div>

            <p class="text-[11px] text-gray-400 leading-relaxed">
                Al desconectar, el asistente pierde el acceso de inmediato y tendrá que volver a pedirte permiso
                para conectarse. Si no reconoces alguna sesión, desconéctala.
            </p>
        </div>
    </ModalView>
</template>

<script setup>
import { ref, watch } from 'vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['update:show']);

// Dirección canónica del conector: sin barra final y sin variantes (debe coincidir
// carácter por carácter con lo que espera el servidor MCP). Se arma con el origen
// desde el que se sirve la aplicación para no fijar ningún dominio en el código.
const mcpUrl = `${window.location.origin}/mcp/gestion-proyectos`;

const sessions = ref([]);
const loading = ref(false);
const working = ref(false);
const busyId = ref(null);

watch(() => props.show, (val) => {
    if (val) loadSessions();
    else sessions.value = [];
});

async function loadSessions() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('gestion-proyectos.api-tokens.index'));
        sessions.value = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : []);
    } catch (e) {
        sessions.value = [];
        window.showToast?.(e.response?.data?.message || 'No se pudieron cargar las sesiones.', 'error');
    } finally {
        loading.value = false;
    }
}

async function disconnect(s) {
    if (working.value) return;

    const nombre = s.name || 'este asistente';
    const ok = await confirmar(
        '¿Desconectar esta sesión?',
        `"${nombre}" dejará de tener acceso a tus proyectos de inmediato. Podrás volver a conectarlo cuando quieras.`,
        'Sí, desconectar'
    );
    if (!ok) return;

    working.value = true;
    busyId.value = s.id;
    try {
        await window.axios.delete(route('gestion-proyectos.api-tokens.destroy', s.id));
        window.showToast?.('Sesión desconectada.', 'success');
        await loadSessions();
    } catch (e) {
        window.showToast?.(e.response?.data?.message || 'No se pudo desconectar la sesión.', 'error');
    } finally {
        working.value = false;
        busyId.value = null;
    }
}

async function disconnectAll() {
    if (working.value || !sessions.value.length) return;

    const ok = await confirmar(
        '¿Desconectar todas las sesiones?',
        'Todos los asistentes conectados perderán el acceso a tus proyectos. Tendrás que volver a conectarlos uno por uno.',
        'Sí, desconectar todas'
    );
    if (!ok) return;

    working.value = true;
    try {
        await window.axios.delete(route('gestion-proyectos.api-tokens.destroy-all'));
        window.showToast?.('Todas las sesiones fueron desconectadas.', 'success');
        await loadSessions();
    } catch (e) {
        window.showToast?.(e.response?.data?.message || 'No se pudieron desconectar las sesiones.', 'error');
    } finally {
        working.value = false;
    }
}

async function confirmar(title, message, confirmButtonText) {
    if (typeof window.showConfirm !== 'function') return window.confirm(`${title}\n\n${message}`);
    const r = await window.showConfirm(title, message, {
        confirmButtonText,
        cancelButtonText: 'Cancelar',
    });
    return r?.isConfirmed !== false;
}

async function copy(text) {
    try {
        await navigator.clipboard.writeText(text);
        window.showToast?.('Dirección copiada al portapapeles.', 'success', { timer: 2000 });
    } catch {
        window.showToast?.('No se pudo copiar automáticamente. Cópiala manualmente.', 'error');
    }
}

// "hace 5 minutos", "ayer", "hace 3 días"… sin librerías ni jerga.
function relativo(d) {
    if (!d) return 'Nunca';
    const fechaDato = new Date(d);
    if (isNaN(fechaDato)) return 'Nunca';

    const seg = Math.floor((Date.now() - fechaDato.getTime()) / 1000);
    if (seg < 0) return fecha(d);
    if (seg < 60) return 'hace unos segundos';

    const min = Math.floor(seg / 60);
    if (min < 60) return `hace ${min} minuto${min !== 1 ? 's' : ''}`;

    const hor = Math.floor(min / 60);
    if (hor < 24) return `hace ${hor} hora${hor !== 1 ? 's' : ''}`;

    const dias = Math.floor(hor / 24);
    if (dias === 1) return 'ayer';
    if (dias < 30) return `hace ${dias} días`;

    const meses = Math.floor(dias / 30);
    if (meses < 12) return `hace ${meses} mes${meses !== 1 ? 'es' : ''}`;

    const anios = Math.floor(meses / 12);
    return `hace ${anios} año${anios !== 1 ? 's' : ''}`;
}

// Descripción legible del navegador/sistema a partir del user agent.
// Si no se reconoce nada, se muestra el propio user agent recortado.
function dispositivo(ua) {
    if (!ua) return 'Origen desconocido';

    const navegador =
        /Edg\//i.test(ua) ? 'Edge'
        : /OPR\/|Opera/i.test(ua) ? 'Opera'
        : /Firefox\//i.test(ua) ? 'Firefox'
        : /Chrome\/|CriOS/i.test(ua) ? 'Chrome'
        : /Safari\//i.test(ua) ? 'Safari'
        : null;

    const sistema =
        /Windows/i.test(ua) ? 'Windows'
        : /Android/i.test(ua) ? 'Android'
        : /iPhone|iPad|iOS/i.test(ua) ? 'iOS'
        : /Mac OS X|Macintosh/i.test(ua) ? 'macOS'
        : /Linux/i.test(ua) ? 'Linux'
        : null;

    if (navegador && sistema) return `${navegador} en ${sistema}`;
    if (navegador) return navegador;
    if (sistema) return sistema;

    return ua.length > 48 ? `${ua.slice(0, 48)}…` : ua;
}

function fecha(d) {
    if (!d) return '—';
    const f = new Date(d);
    if (isNaN(f)) return '—';
    return f.toLocaleDateString('es-ES', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function close() {
    emit('update:show', false);
}
</script>
