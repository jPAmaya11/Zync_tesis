import { ref } from 'vue';

/**
 * Búsqueda server-side (con debounce) de usuarios asignables de un espacio.
 *
 * Reutiliza el endpoint `gestion-proyectos.assignable-users` (?project=&q=), el mismo
 * que usa el modal de Crear Actividad. La lista INICIAL (sin texto) la sigue proveyendo
 * el padre vía su prop `availableUsers`; este hook solo trae los RESULTADOS cuando el
 * usuario escribe, así se alcanza a cualquier miembro del espacio (no solo los 200
 * iniciales) sin tope práctico.
 *
 * Uso:
 *   const { query, results, loading, search, clear } = useAssignableUserSearch();
 *   // en el input:  @input="search(projectKey, $event.target.value)"
 *   // lista a mostrar:  const displayUsers = computed(() => query.value.trim() ? results.value : availableUsers);
 */
export function useAssignableUserSearch() {
    const query = ref('');
    const results = ref([]);
    const loading = ref(false);
    let timer = null;

    async function fetchNow(projectKey, term) {
        loading.value = true;
        try {
            const url = route('gestion-proyectos.assignable-users')
                + '?project=' + encodeURIComponent(projectKey || '')
                + '&q=' + encodeURIComponent(term);
            const { data } = await window.axios.get(url);
            results.value = Array.isArray(data) ? data : [];
        } catch {
            results.value = [];
        }
        loading.value = false;
    }

    function search(projectKey, q) {
        query.value = q ?? '';
        clearTimeout(timer);
        const term = query.value.trim();
        if (!term) {
            results.value = [];
            loading.value = false;
            return;
        }
        loading.value = true;
        timer = setTimeout(() => fetchNow(projectKey, term), 300);
    }

    function clear() {
        clearTimeout(timer);
        query.value = '';
        results.value = [];
        loading.value = false;
    }

    return { query, results, loading, search, clear };
}
