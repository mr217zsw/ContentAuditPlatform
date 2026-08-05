import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from '@/axios';

export const useDashboardStore = defineStore('dashboard', () => {
    const stats = ref(null);
    const loading = ref(false);

    async function fetchStats() {
        loading.value = true;
        try {
            const { data } = await axios.get('/dashboard/stats');
            stats.value = data;
        } catch (e) {
            console.error('获取统计数据失败', e);
        } finally {
            loading.value = false;
        }
    }

    return { stats, loading, fetchStats };
});
