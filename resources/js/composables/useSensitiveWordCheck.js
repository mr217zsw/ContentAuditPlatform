import { ref, watch } from 'vue';
import axios from '@/axios';

export function useSensitiveWordCheck() {
    const hits = ref([]);
    const checking = ref(false);
    const maxLevel = ref(null);

    let debounceTimer = null;

    async function check(text) {
        if (!text || text.trim().length === 0) {
            hits.value = [];
            maxLevel.value = null;
            return;
        }

        checking.value = true;
        try {
            const { data } = await axios.post('/sensitive-words/check', { text });
            hits.value = data.hits || [];

            const levels = ['forbidden', 'high', 'medium', 'low'];
            maxLevel.value = null;
            for (const lvl of levels) {
                if (hits.value.some(h => h.level === lvl)) {
                    maxLevel.value = lvl;
                    break;
                }
            }
        } catch (e) {
            // ignore errors during typing
        } finally {
            checking.value = false;
        }
    }

    function debouncedCheck(text, delay = 500) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => check(text), delay);
    }

    const levelLabel = {
        forbidden: '禁止',
        high: '高风险',
        medium: '中风险',
        low: '低风险',
    };

    const levelColor = {
        forbidden: 'text-red-600 bg-red-50',
        high: 'text-orange-600 bg-orange-50',
        medium: 'text-yellow-600 bg-yellow-50',
        low: 'text-blue-600 bg-blue-50',
    };

    return { hits, checking, maxLevel, levelLabel, levelColor, check, debouncedCheck };
}
