import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from '@/axios';

export const useAuditStore = defineStore('audit', () => {
    const pendingArticles = ref([]);
    const auditLogs = ref([]);
    const loading = ref(false);
    const pendingCount = ref(0);

    async function fetchPending(page = 1) {
        loading.value = true;
        try {
            const { data } = await axios.get('/audit/pending', { params: { page, per_page: 15 } });
            pendingArticles.value = data.data;
            pendingCount.value = data.total;
            return data;
        } catch (e) {
            console.error('获取待审核列表失败', e);
        } finally {
            loading.value = false;
        }
    }

    async function approve(articleId, comment = '') {
        const { data } = await axios.post(`/audit/${articleId}/approve`, { comment });
        pendingArticles.value = pendingArticles.value.filter(a => a.id !== articleId);
        pendingCount.value = Math.max(0, pendingCount.value - 1);
        return data;
    }

    async function reject(articleId, comment) {
        const { data } = await axios.post(`/audit/${articleId}/reject`, { comment });
        pendingArticles.value = pendingArticles.value.filter(a => a.id !== articleId);
        pendingCount.value = Math.max(0, pendingCount.value - 1);
        return data;
    }

    async function fetchAuditLogs(articleId, page = 1) {
        const { data } = await axios.get(`/audit/${articleId}/logs`, { params: { page, per_page: 20 } });
        auditLogs.value = data.data;
        return data;
    }

    return {
        pendingArticles, auditLogs, loading, pendingCount,
        fetchPending, approve, reject, fetchAuditLogs,
    };
});
