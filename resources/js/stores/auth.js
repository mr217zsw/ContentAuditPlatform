import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from '@/axios';
import router from '@/router';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'));
    const token = ref(localStorage.getItem('auth_token'));

    const isLoggedIn = computed(() => !!token.value);
    const role = computed(() => user.value?.role || null);
    const isAdmin = computed(() => user.value?.role === 'admin');
    const isEditor = computed(() => user.value?.role === 'editor');
    const isSupervisor = computed(() => user.value?.role === 'supervisor');
    const isFinalApprover = computed(() => user.value?.role === 'final_approver');
    const canAudit = computed(() =>
        ['editor', 'supervisor', 'final_approver', 'admin'].includes(role.value)
    );

    async function login(email, password) {
        const { data } = await axios.post('/auth/login', { email, password });
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem('auth_token', data.token);
        localStorage.setItem('auth_user', JSON.stringify(data.user));
        return data;
    }

    async function logout() {
        try {
            await axios.post('/auth/logout');
        } catch (e) { /* ignore */ }
        token.value = null;
        user.value = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        router.push('/login');
    }

    async function fetchMe() {
        try {
            const { data } = await axios.get('/auth/me');
            user.value = data.user;
            localStorage.setItem('auth_user', JSON.stringify(data.user));
        } catch {
            logout();
        }
    }

    return {
        user, token, isLoggedIn, role,
        isAdmin, isEditor, isSupervisor, isFinalApprover, canAudit,
        login, logout, fetchMe,
    };
});
