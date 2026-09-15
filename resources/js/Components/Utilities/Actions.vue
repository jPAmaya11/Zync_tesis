<template>
    <div :class="grid ? 'grid grid-cols-2 gap-1.5 w-fit' : 'flex justify-center gap-1.5'">
        <!-- Botón Fijar (chincheta/pin): activo = ámbar; inactivo = gris neutro,
             para que se vea como botón igual que sus hermanos. -->
        <button
            v-if="pin"
            @click="$emit('pin')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :class="
                pinned
                    ? 'bg-gradient-to-br from-amber-400 to-amber-500 hover:from-amber-500 hover:to-amber-600'
                    : 'bg-gradient-to-br from-slate-400 to-slate-500 hover:from-slate-500 hover:to-slate-600'
            "
            :title="pinned ? unpinTitle : pinTitle"
        >
            <span class="sr-only">{{ pinned ? 'Desfijar' : 'Fijar' }}</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                viewBox="0 0 24 24"
            >
                <line x1="12" x2="12" y1="17" y2="22" />
                <path
                    d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Ver Detalles -->
        <button
            v-if="view"
            @click="$emit('view')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-blue-400 to-blue-500 hover:from-blue-500 hover:to-blue-600 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="viewTitle"
        >
            <span class="sr-only">Ver Detalles</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Tipificar -->
        <button
            v-if="canTipificar"
            @click="$emit('tipificar')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-cyan-500 to-cyan-600 hover:from-cyan-600 hover:to-cyan-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="tipificarTitle"
        >
            <span class="sr-only">Tipificar</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Listado -->
        <button
            v-if="list"
            @click="$emit('list')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="listTitle"
        >
            <span class="sr-only">Listado</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Descargar -->
        <button
            v-if="download"
            @click="$emit('download')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="downloadTitle"
        >
            <span class="sr-only">Descargar</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Copiar Prompt (autocontenido: copia copyText y muestra check) -->
        <button
            v-if="copyPrompt"
            @click="copiarPrompt"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :class="
                promptCopiado
                    ? 'bg-gradient-to-br from-green-500 to-green-600'
                    : 'bg-gradient-to-br from-slate-500 to-slate-600 hover:from-slate-600 hover:to-slate-700'
            "
            :title="promptCopiado ? '¡Copiado!' : copyPromptTitle"
        >
            <span class="sr-only">Copiar prompt</span>
            <!-- Check verde tras copiar -->
            <svg
                v-if="promptCopiado"
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4.5 12.75l6 6 9-13.5"
                />
            </svg>
            <!-- Icono de portapapeles por defecto -->
            <svg
                v-else
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Ver Prompt (emite 'view-prompt'; el padre abre un modal con el
             prompt + las imágenes base). Icono de documento con líneas. -->
        <button
            v-if="viewPrompt"
            @click="$emit('view-prompt')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-slate-500 to-slate-600 hover:from-slate-600 hover:to-slate-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="viewPromptTitle"
        >
            <span class="sr-only">Ver prompt</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h4.5M6.75 3h10.5A2.25 2.25 0 0119.5 5.25v13.5A2.25 2.25 0 0117.25 21H6.75A2.25 2.25 0 014.5 18.75V5.25A2.25 2.25 0 016.75 3z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Editar -->
        <button
            v-if="edit"
            @click="editDisabled ? null : $emit('edit')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg shadow-md transform transition-all duration-200"
            :class="
                editDisabled
                    ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    : 'bg-gradient-to-br from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white hover:shadow-lg hover:scale-105'
            "
            :title="
                editDisabled
                    ? 'No se puede editar - Registro completado'
                    : editTitle
            "
            :disabled="editDisabled"
        >
            <span class="sr-only">Editar</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
                v-if="!editDisabled"
            ></div>
        </button>

        <!-- Botón Regenerar (refresh circular) -->
        <button
            v-if="regenerate"
            @click="$emit('regenerate')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="regenerateTitle"
        >
            <span class="sr-only">Regenerar</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Eliminar -->
        <button
            v-if="remove"
            @click="$emit('delete')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="removeTitle"
        >
            <span class="sr-only">Eliminar</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Historial -->
        <button
            v-if="canViewHistory"
            @click="$emit('history')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-amber-400 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-amber-900 shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="historyTitle || 'Ver historial de asignaciones'"
        >
            <span class="sr-only">Historial</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Historial de Comunicaciones -->
        <button
            v-if="canViewCommunications"
            @click="$emit('communications')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="communicationsTitle || 'Ver historial de comunicaciones'"
        >
            <span class="sr-only">Historial de Comunicaciones</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Lista Negra -->
        <button
            v-if="canBlacklist"
            @click="$emit('blacklist')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-gray-700 to-gray-800 hover:from-gray-800 hover:to-gray-900 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="blacklistTitle || 'Lista Negra'"
        >
            <span class="sr-only">Lista Negra</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Agendar/Reagendar -->
        <button
            v-if="canSchedule"
            @click="
                scheduleDisabled
                    ? null
                    : $emit(hasScheduledAppointment ? 'reschedule' : 'schedule')
            "
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg shadow-md transform transition-all duration-200"
            :class="
                scheduleDisabled
                    ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    : hasScheduledAppointment
                    ? 'bg-gradient-to-br from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white hover:shadow-lg hover:scale-105'
                    : 'bg-gradient-to-br from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white hover:shadow-lg hover:scale-105'
            "
            :title="
                scheduleDisabled
                    ? 'Solo se puede agendar registros completados'
                    : hasScheduledAppointment
                    ? rescheduleTitle
                    : scheduleTitle
            "
            :disabled="scheduleDisabled"
        >
            <span class="sr-only">{{
                hasScheduledAppointment ? 'Reagendar' : 'Agendar'
            }}</span>
            <svg
                v-if="!hasScheduledAppointment"
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z"
                />
            </svg>
            <svg
                v-else
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
                v-if="!scheduleDisabled"
            ></div>
        </button>

        <!-- Botón Enviar Emails -->
        <button
            v-if="canSendEmails"
            @click="$emit('send-emails')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-sky-500 to-sky-600 hover:from-sky-600 hover:to-sky-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="sendEmailsTitle"
        >
            <span class="sr-only">Enviar Emails</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98V19.5z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Cancelar -->
        <button
            v-if="canCancel"
            @click="$emit('cancel')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="cancelTitle"
        >
            <span class="sr-only">Cancelar</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>
        <!-- Botón Asesores (Persona) -->
        <button
            v-if="canManageAdvisors"
            @click="$emit('manage-advisors')"
            class="group relative inline-flex items-center justify-center p-2 rounded-lg bg-gradient-to-br from-violet-500 to-violet-600 hover:from-violet-600 hover:to-violet-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="advisorsTitle"
        >
            <span class="sr-only">Asesores</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200 rounded-lg pointer-events-none"
            ></div>
            <span v-if="advisorsCount > 0" class="absolute -top-1.5 -right-1.5 flex items-center justify-center min-w-[16px] h-4 px-1 text-[9px] font-bold text-white bg-violet-600 border border-white dark:border-gray-800 rounded-full shadow-sm z-20">
                {{ advisorsCount }}
            </span>
        </button>

        <!-- Botón Terminar (Cerrar Campaña) -->
        <button
            v-if="canTerminate"
            @click="$emit('terminate')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-red-500 to-red-600 hover:from-red-700 hover:to-red-800 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="terminateTitle"
        >
            <span class="sr-only">Terminar</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Reset Cuota (refresh circular) -->
        <button
            v-if="canResetQuota"
            @click="$emit('reset-quota')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-teal-500 to-teal-600 hover:from-teal-600 hover:to-teal-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="resetQuotaTitle"
        >
            <span class="sr-only">Reset cuota</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Reset Cuota Mensual (calendar) -->
        <button
            v-if="canResetMonthlyQuota"
            @click="$emit('reset-monthly-quota')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-cyan-500 to-cyan-600 hover:from-cyan-600 hover:to-cyan-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="resetMonthlyQuotaTitle"
        >
            <span class="sr-only">Reset cuota mensual</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Sync Usage (cloud download) — sincronizar contadores con la API real del provider -->
        <button
            v-if="canSyncUsage"
            @click="$emit('sync-usage')"
            class="group relative inline-flex items-center justify-center p-2 overflow-hidden rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="syncUsageTitle"
        >
            <span class="sr-only">Sincronizar contadores</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9.75v6.75m0 0l-3-3m3 3l3-3m-8.25 6a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200"
            ></div>
        </button>

        <!-- Botón Ver Leads Especial -->
        <button
            v-if="canViewLeads"
            @click="$emit('view-leads')"
            class="group relative inline-flex items-center justify-center p-2 rounded-lg bg-gradient-to-br from-blue-700 to-indigo-800 hover:from-blue-800 hover:to-indigo-900 text-white shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200"
            :title="viewLeadsTitle"
        >
            <span class="sr-only">Ver Leads</span>
            <svg
                class="w-4 h-4 relative z-10"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
            </svg>
            <div
                class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200 rounded-lg pointer-events-none"
            ></div>
            <span v-if="leadsCount !== undefined" class="absolute -top-1.5 -right-1.5 flex items-center justify-center min-w-[16px] h-4 px-1 text-[9px] font-bold text-white bg-indigo-500 border border-white dark:border-gray-800 rounded-full shadow-sm z-20">
                {{ leadsCount }}
            </span>
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    // Distribuye los botones en cuadrícula 2 columnas (2x2…) en vez de una fila.
    grid: { type: Boolean, default: false },
    view: { type: Boolean, default: false },
    viewTitle: { type: String, default: 'Ver detalles' },
    edit: { type: Boolean, default: false },
    editDisabled: { type: Boolean, default: false },
    remove: { type: Boolean, default: false },
    list: { type: Boolean, default: false },
    editTitle: { type: String, default: 'Editar' },
    removeTitle: { type: String, default: 'Eliminar' },
    listTitle: { type: String, default: 'Ver listado' },
    canViewHistory: { type: Boolean, default: false },
    historyTitle: { type: String, default: 'Ver historial' },
    canViewCommunications: { type: Boolean, default: false },
    communicationsTitle: {
        type: String,
        default: 'Ver historial de comunicaciones',
    },
    canSchedule: { type: Boolean, default: false },
    scheduleDisabled: { type: Boolean, default: false },
    scheduleTitle: { type: String, default: 'Agendar' },
    hasScheduledAppointment: { type: Boolean, default: false },
    rescheduleTitle: { type: String, default: 'Reagendar' },
    canTipificar: { type: Boolean, default: false },
    tipificarTitle: { type: String, default: 'Tipificar Lead' },
    canBlacklist: { type: Boolean, default: false },
    blacklistTitle: { type: String, default: 'Lista Negra' },
    canSendEmails: { type: Boolean, default: false },
    sendEmailsTitle: { type: String, default: 'Enviar Emails' },
    canCancel: { type: Boolean, default: false },
    cancelTitle: { type: String, default: 'Cancelar' },
    canManageAdvisors: { type: Boolean, default: false },
    advisorsTitle: { type: String, default: 'Gestionar Asesores' },
    advisorsCount: { type: Number, default: 0 },
    canTerminate: { type: Boolean, default: false },
    terminateTitle: { type: String, default: 'Terminar' },
    canViewLeads: { type: Boolean, default: false },
    viewLeadsTitle: { type: String, default: 'Ver Leads' },
    leadsCount: { type: Number, default: undefined },
    canResetQuota: { type: Boolean, default: false },
    resetQuotaTitle: { type: String, default: 'Reset cuota diaria' },
    canResetMonthlyQuota: { type: Boolean, default: false },
    resetMonthlyQuotaTitle: { type: String, default: 'Reset cuota mensual' },
    canSyncUsage: { type: Boolean, default: false },
    syncUsageTitle: { type: String, default: 'Sincronizar contadores con el provider' },
    // Botón Descargar: dispara el evento 'download' (el padre resuelve la URL).
    download: { type: Boolean, default: false },
    downloadTitle: { type: String, default: 'Descargar' },
    // Botón Regenerar: dispara el evento 'regenerate'.
    regenerate: { type: Boolean, default: false },
    regenerateTitle: { type: String, default: 'Regenerar' },
    // Botón Copiar prompt: autocontenido. Copia copyText al portapapeles y
    // muestra el check verde ~1.5 s; no necesita emit ni lógica en el padre.
    copyPrompt: { type: Boolean, default: false },
    copyText: { type: String, default: '' },
    copyPromptTitle: { type: String, default: 'Copiar prompt' },
    // Botón Ver prompt: emite 'view-prompt' para que el padre abra un modal.
    viewPrompt: { type: Boolean, default: false },
    viewPromptTitle: { type: String, default: 'Ver prompt' },
    // Botón Fijar/pin: emite 'pin'. `pinned` marca el estado activo (ámbar).
    pin: { type: Boolean, default: false },
    pinned: { type: Boolean, default: false },
    pinTitle: { type: String, default: 'Fijar arriba' },
    unpinTitle: { type: String, default: 'Desfijar' },
});

// Feedback breve del botón "Copiar prompt": check verde ~1.5 s. Silencioso si
// el navegador bloquea el portapapeles. Mismo patrón que BurbujaTurno.vue.
const promptCopiado = ref(false);
const copiarPrompt = async () => {
    try {
        await navigator.clipboard.writeText(props.copyText || '');
        promptCopiado.value = true;
        setTimeout(() => (promptCopiado.value = false), 1500);
    } catch (e) {
        /* portapapeles no disponible: no se hace nada */
    }
};
</script>
