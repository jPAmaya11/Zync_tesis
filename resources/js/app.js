// ============================================================================
// Estilos y Bootstrap
// ============================================================================
import '../css/app.css';
// Corrección de modo oscuro del módulo SCRUM, acotada por `.gp-scope`/`.gp-modal`.
import '../css/gestion-proyectos.css';
import './bootstrap';
// flag-icons: banderas SVG por código ISO
import 'flag-icons/css/flag-icons.min.css';

// ============================================================================
// Vue y Plugins Principales
// ============================================================================
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { MotionPlugin } from '@vueuse/motion';
import { createApp, h } from 'vue';

// ============================================================================
// PrimeVue (Componentes y Tema)
// ============================================================================
import Aura from '@primeuix/themes/aura';
import PrimeVue from 'primevue/config';

// ============================================================================
// Plugins y Utilidades Personalizadas
// ============================================================================
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PermissionsPlugin from './plugins/permissions.js';

// ============================================================================
// Configuración de Aplicación
// ============================================================================
const appName = import.meta.env.VITE_APP_NAME || 'Zync';

// ============================================================================
// Inicialización de Inertia + Vue
// ============================================================================
createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue")
        ),
    setup({ el, App, props, plugin }) {
        // Crear instancia de la aplicación Vue
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(MotionPlugin)
            .use(PermissionsPlugin);

        // Registrar PrimeVue con tema Aura sincronizado con Tailwind dark mode
        app.use(PrimeVue, {
            theme: {
                preset: Aura,
                options: {
                    darkModeSelector: '.dark', // Sincronizado con Tailwind
                },
            },
        });

        // Observar cambios en la clase 'dark' del documento HTML
        const observer = new MutationObserver(() => {
            // PrimeVue responde automáticamente a cambios en la clase dark
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        // Registrar componentes globales de Inertia
        app.component('Head', Head);
        app.component('Link', Link);

        // Montar la aplicación
        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
