import { computed, ref } from 'vue';

/**
 * Hook para cargar y consultar usuarios asignables a proyectos.
 *
 * Centraliza la lista de usuarios para que el modal de Crear/Editar Espacio
 * y cualquier otro lugar que necesite asignar dueños/asignados use la misma
 * fuente — evita refetches innecesarios y mantiene options consistentes.
 *
 * Retorna:
 *  - users: ref con array de usuarios { account_id, display_name, email, avatar_url }
 *  - loading: ref booleana
 *  - userOptions: computed de opciones formateadas para SmartSelect
 *  - findUser(id): helper para encontrar el objeto user por account_id
 *  - loadUsers(query?): refetch desde la API (opcionalmente filtra por query)
 */
export function useProjectUsers() {
    const users = ref([]);
    const loading = ref(false);

    const userOptions = computed(() =>
        users.value.map((u) => ({
            value: u.account_id,
            label: u.display_name + (u.email ? ' · ' + u.email : ''),
        }))
    );

    const findUser = (id) =>
        users.value.find((u) => u.account_id == id) ?? null;

    async function loadUsers(query = '') {
        loading.value = true;
        try {
            const url =
                route('gestion-proyectos.assignable-users') +
                (query ? '?q=' + encodeURIComponent(query) : '');
            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (resp.ok) users.value = await resp.json();
        } catch {
            // Falla silenciosa: la UI muestra "Sin opciones" en SmartSelect.
        }
        loading.value = false;
    }

    return { users, loading, userOptions, findUser, loadUsers };
}
