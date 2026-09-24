<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import ApplicationLogo from "@/Components/Layout/ApplicationLogo.vue";

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
});

/* ---------------------------------------------------------------------- */
/*  Idioma (ES / EN) — solo para esta pantalla, por ahora.                 */
/* ---------------------------------------------------------------------- */
const locale = ref(localStorage.getItem("zync_login_locale") || "es");

function setLocale(value) {
    locale.value = value;
    localStorage.setItem("zync_login_locale", value);
}

const translations = {
    es: {
        statusOperational: "Sistema operativo",
        badge: "Acceso Empresarial Seguro",
        title: "Bienvenido a Zync",
        subtitle: "Tu espacio colaborativo en perfecta sincronía",
        emailLabel: "Correo electrónico",
        emailTag: "Corporativo",
        emailPlaceholder: "nombre@empresa.com",
        passwordLabel: "Contraseña",
        remember: "Recordar sesión",
        forgot: "¿Olvidaste tu contraseña?",
        submit: "Iniciar sesión",
        submitting: "Ingresando...",
        footer: "Zync Technologies Inc.",
        security: "Seguridad",
        privacy: "Privacidad",
        terms: "Términos",
    },
    en: {
        statusOperational: "Systems Operational",
        badge: "Secure Enterprise Access",
        title: "Welcome to Zync",
        subtitle: "Your collaborative space in perfect sync",
        emailLabel: "Email address",
        emailTag: "Corporate",
        emailPlaceholder: "name@company.com",
        passwordLabel: "Password",
        remember: "Remember me",
        forgot: "Forgot your password?",
        submit: "Sign in",
        submitting: "Signing in...",
        footer: "Zync Technologies Inc.",
        security: "Security",
        privacy: "Privacy",
        terms: "Terms",
    },
};

const t = computed(() => translations[locale.value]);

/* ---------------------------------------------------------------------- */
/*  Formulario                                                             */
/* ---------------------------------------------------------------------- */
const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const emailInput = ref(null);
const showPassword = ref(false);

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};

onMounted(() => {
    emailInput.value?.focus();
});
</script>

<template>
    <Head title="Login" />

    <div
        class="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-gradient-to-br from-indigo-50 via-white to-cyan-50 px-4 py-10"
    >
        <!-- Manchas decorativas de fondo -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-indigo-200/40 blur-3xl"
            />
            <div
                class="absolute -bottom-24 -right-16 h-80 w-80 rounded-full bg-cyan-200/40 blur-3xl"
            />
        </div>

        <!-- Barra superior: estado + idioma -->
        <div
            class="absolute inset-x-0 top-0 z-10 flex items-center justify-between px-4 py-4 sm:px-8"
        >
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-white/70 px-3 py-1 text-xs font-medium text-gray-500 shadow-sm backdrop-blur"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ t.statusOperational }}
            </span>

            <div
                class="inline-flex overflow-hidden rounded-full border border-gray-200 bg-white/70 text-xs font-semibold shadow-sm backdrop-blur"
            >
                <button
                    type="button"
                    @click="setLocale('es')"
                    :class="[
                        'px-3 py-1.5 transition-colors',
                        locale === 'es'
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-500 hover:text-gray-700',
                    ]"
                >
                    ES
                </button>
                <button
                    type="button"
                    @click="setLocale('en')"
                    :class="[
                        'px-3 py-1.5 transition-colors',
                        locale === 'en'
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-500 hover:text-gray-700',
                    ]"
                >
                    EN
                </button>
            </div>
        </div>

        <!-- Tarjeta principal -->
        <div class="relative z-10 w-full max-w-md">
            <div
                class="rounded-2xl border border-gray-100 bg-white/90 p-8 shadow-xl backdrop-blur sm:p-10"
            >
                <!-- Logo -->
                <div class="flex justify-center">
                    <div
                        class="flex h-28 w-28 items-center justify-center rounded-2xl bg-white p-2 shadow ring-1 ring-gray-100"
                    >
                        <ApplicationLogo class="h-full w-full" />
                    </div>
                </div>

                <!-- Badge -->
                <div class="mt-5 flex justify-center">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-indigo-600"
                    >
                        {{ t.badge }}
                    </span>
                </div>

                <!-- Título -->
                <h1
                    class="mt-3 text-center text-2xl font-bold text-gray-900"
                >
                    {{ t.title }}
                </h1>
                <p class="mt-1 text-center text-sm text-gray-500">
                    {{ t.subtitle }}
                </p>

                <!-- Mensaje de estado (p. ej. link de reset enviado) -->
                <div
                    v-if="status"
                    class="mt-6 rounded-lg bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700"
                >
                    {{ status }}
                </div>

                <!-- Formulario -->
                <form @submit.prevent="submit" class="mt-8 space-y-5">
                    <!-- Correo -->
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label
                                for="email"
                                class="text-xs font-semibold text-gray-600"
                            >
                                {{ t.emailLabel }}
                            </label>
                            <span class="text-[11px] text-gray-400">{{
                                t.emailTag
                            }}</span>
                        </div>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 7.5 12 13l9-5.5M4.5 5h15A1.5 1.5 0 0 1 21 6.5v11A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5Z"
                                    />
                                </svg>
                            </span>
                            <input
                                id="email"
                                ref="emailInput"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="username"
                                :placeholder="t.emailPlaceholder"
                                class="block w-full rounded-lg border border-gray-200 bg-gray-50/70 py-2.5 pl-10 pr-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
                            />
                        </div>
                        <p
                            v-if="form.errors.email"
                            class="mt-1.5 text-xs font-medium text-red-500"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Contraseña -->
                    <div>
                        <label
                            for="password"
                            class="mb-1 block text-xs font-semibold text-gray-600"
                        >
                            {{ t.passwordLabel }}
                        </label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <rect
                                        x="4.5"
                                        y="10.5"
                                        width="15"
                                        height="9"
                                        rx="1.8"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M7.5 10.5V7.75a4.5 4.5 0 1 1 9 0v2.75"
                                    />
                                </svg>
                            </span>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="block w-full rounded-lg border border-gray-200 bg-gray-50/70 py-2.5 pl-10 pr-10 text-sm text-gray-800 placeholder:text-gray-400 focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                                tabindex="-1"
                            >
                                <svg
                                    v-if="showPassword"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 3l18 18M10.6 10.6a2.5 2.5 0 0 0 3.53 3.53M6.5 6.7C4.4 8.1 2.9 10 2 12c1.6 3.6 5.5 7 10 7 1.6 0 3.1-.4 4.4-1.1M9.9 4.2A10.6 10.6 0 0 1 12 4c4.5 0 8.4 3.4 10 7-.6 1.3-1.4 2.6-2.5 3.7"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2 12c1.6-3.6 5.5-7 10-7s8.4 3.4 10 7c-1.6 3.6-5.5 7-10 7s-8.4-3.4-10-7Z"
                                    />
                                    <circle cx="12" cy="12" r="2.8" />
                                </svg>
                            </button>
                        </div>
                        <p
                            v-if="form.errors.password"
                            class="mt-1.5 text-xs font-medium text-red-500"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Recordarme / olvidé contraseña -->
                    <div class="flex items-center justify-between text-sm">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-gray-600"
                        >
                            <input
                                type="checkbox"
                                v-model="form.remember"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400"
                            />
                            {{ t.remember }}
                        </label>
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="font-medium text-indigo-600 hover:text-indigo-500"
                        >
                            {{ t.forgot }}
                        </Link>
                    </div>

                    <!-- Botón -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-700 to-indigo-500 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-md transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg
                            v-if="form.processing"
                            class="h-4 w-4 animate-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        <span>{{
                            form.processing ? t.submitting : t.submit
                        }}</span>
                        <svg
                            v-if="!form.processing"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4.5 12h15m0 0-6-6m6 6-6 6"
                            />
                        </svg>
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div
                class="mt-6 flex flex-col items-center justify-between gap-2 px-2 text-[11px] text-gray-400 sm:flex-row"
            >
                <span>© {{ new Date().getFullYear() }} {{ t.footer }}</span>
                <div class="flex items-center gap-4">
                    <span class="cursor-default hover:text-gray-500">{{
                        t.security
                    }}</span>
                    <span class="cursor-default hover:text-gray-500">{{
                        t.privacy
                    }}</span>
                    <span class="cursor-default hover:text-gray-500">{{
                        t.terms
                    }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
