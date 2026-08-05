import { ref, computed } from 'vue';

export function usePagination(fetchFn) {
    const currentPage = ref(1);
    const perPage = ref(15);
    const total = ref(0);

    const totalPages = computed(() => Math.ceil(total.value / perPage.value) || 1);
    const hasPrev = computed(() => currentPage.value > 1);
    const hasNext = computed(() => currentPage.value < totalPages.value);

    function goTo(page) {
        if (page >= 1 && page <= totalPages.value) {
            currentPage.value = page;
            fetchFn(page);
        }
    }

    function next() { if (hasNext.value) goTo(currentPage.value + 1); }
    function prev() { if (hasPrev.value) goTo(currentPage.value - 1); }

    function reset() {
        currentPage.value = 1;
        fetchFn(1);
    }

    return { currentPage, perPage, total, totalPages, hasPrev, hasNext, goTo, next, prev, reset };
}
