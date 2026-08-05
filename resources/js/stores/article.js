import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from '@/axios';

export const useArticleStore = defineStore('article', () => {
    const articles = ref([]);
    const currentArticle = ref(null);
    const loading = ref(false);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const filterStatus = ref('all');
    const sensitiveWarnings = ref(null);

    async function fetchArticles(page = 1) {
        loading.value = true;
        try {
            const params = { page, per_page: 15 };
            if (filterStatus.value !== 'all') {
                params.status = filterStatus.value;
            }
            const { data } = await axios.get('/articles', { params });
            articles.value = data.data;
            pagination.value = {
                current_page: data.current_page,
                last_page: data.last_page,
                total: data.total,
            };
        } catch (e) {
            console.error('获取稿件列表失败', e);
        } finally {
            loading.value = false;
        }
    }

    async function fetchArticle(id) {
        loading.value = true;
        try {
            const { data } = await axios.get(`/articles/${id}`);
            currentArticle.value = data.article;
            return data.article;
        } catch (e) {
            console.error('获取稿件详情失败', e);
            return null;
        } finally {
            loading.value = false;
        }
    }

    async function createArticle(form) {
        const { data } = await axios.post('/articles', form);
        sensitiveWarnings.value = data.sensitive_warnings || null;
        await fetchArticles();
        return data;
    }

    async function updateArticle(id, form) {
        const { data } = await axios.put(`/articles/${id}`, form);
        sensitiveWarnings.value = data.sensitive_warnings || null;
        if (currentArticle.value?.id === id) {
            currentArticle.value = await fetchArticle(id);
        }
        await fetchArticles();
        return data;
    }

    async function deleteArticle(id) {
        await axios.delete(`/articles/${id}`);
        articles.value = articles.value.filter(a => a.id !== id);
    }

    async function submitArticle(id) {
        const { data } = await axios.post(`/articles/${id}/submit`);
        await fetchArticles();
        return data;
    }

    function setFilter(status) {
        filterStatus.value = status;
        fetchArticles(1);
    }

    return {
        articles, currentArticle, loading, pagination,
        filterStatus, sensitiveWarnings,
        fetchArticles, fetchArticle, createArticle,
        updateArticle, deleteArticle, submitArticle, setFilter,
    };
});
