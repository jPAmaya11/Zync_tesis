import { ref, watch, onMounted } from 'vue';

const isDark = ref(false);

export function useDarkMode() {
    onMounted(() => {
        // Cargar preferencia guardada
        const saved = localStorage.getItem('darkMode');
        if (saved !== null) {
            isDark.value = saved === 'true';
        } else {
            // Detectar preferencia del sistema
            isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        
        // Aplicar tema inicial
        applyTheme();
    });

    watch(isDark, () => {
        applyTheme();
        localStorage.setItem('darkMode', isDark.value.toString());
    });

    function applyTheme() {
        // Deshabilita TODAS las transiciones CSS durante el cambio de tema
        // para evitar que cientos de elementos animen simultáneamente.
        const noTrans = document.createElement('style');
        noTrans.textContent = '*, *::before, *::after { transition: none !important; animation: none !important; }';
        document.head.appendChild(noTrans);

        if (isDark.value) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Fuerza un reflow para que el navegador pinte el nuevo tema antes
        // de re-habilitar las transiciones.
        document.documentElement.offsetHeight; // eslint-disable-line no-unused-expressions
        document.head.removeChild(noTrans);
    }

    function toggleDark() {
        isDark.value = !isDark.value;
    }

    return {
        isDark,
        toggleDark
    };
}
