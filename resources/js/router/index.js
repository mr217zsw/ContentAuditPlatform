import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('@/pages/Login.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: () => import('@/layouts/DefaultLayout.vue'),
        children: [
            {
                path: '',
                name: 'Dashboard',
                component: () => import('@/pages/Dashboard.vue'),
                meta: { title: '工作台' },
            },
            {
                path: 'articles',
                name: 'Articles',
                component: () => import('@/pages/Articles.vue'),
                meta: { title: '稿件管理' },
            },
            {
                path: 'audit',
                name: 'Audit',
                component: () => import('@/pages/Audit.vue'),
                meta: { title: '审核中心' },
            },
            {
                path: 'sensitive-words',
                name: 'SensitiveWords',
                component: () => import('@/pages/SensitiveWords.vue'),
                meta: { title: '敏感词管理', role: 'admin' },
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// 路由守卫
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('auth_token');
    if (!token && !to.meta.guest) {
        next({ name: 'Login' });
    } else if (token && to.meta.guest) {
        next({ name: 'Dashboard' });
    } else {
        next();
    }
});

export default router;
