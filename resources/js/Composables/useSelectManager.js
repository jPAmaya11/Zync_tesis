import { ref } from 'vue';

const activeSelectId = ref(null);
let selectCounter = 0;

export function useSelectManager() {
    const selectId = ++selectCounter;

    const openSelect = (id) => {
        activeSelectId.value = id;
    };

    const closeSelect = (id) => {
        if (activeSelectId.value === id) {
            activeSelectId.value = null;
        }
    };

    const isThisSelectOpen = () => {
        return activeSelectId.value === selectId;
    };

    const closeOtherSelects = () => {
        openSelect(selectId);
    };

    return {
        selectId,
        openSelect,
        closeSelect,
        isThisSelectOpen,
        closeOtherSelects,
        activeSelectId,
    };
}
