<template>
  <div class="min-h-screen flex">
    <!-- 侧边栏 -->
    <aside class="w-60 bg-white border-r border-gray-200 flex flex-col flex-shrink-0">
      <div class="px-5 py-5 border-b border-gray-100">
        <h1 class="text-lg font-bold text-indigo-600">内容审核平台</h1>
      </div>
      <nav class="flex-1 py-4 px-3 space-y-1">
        <router-link
          v-for="item in navItems" :key="item.path" :to="item.path"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="$route.path === item.path
            ? 'bg-indigo-50 text-indigo-700'
            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
        >
          <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
          {{ item.label }}
        </router-link>
      </nav>
      <!-- 用户信息 -->
      <div class="px-4 py-4 border-t border-gray-100">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
            {{ (authStore.user?.name || 'U')[0] }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ authStore.user?.name }}</p>
            <p class="text-xs text-gray-400">{{ roleLabel }}</p>
          </div>
          <button @click="authStore.logout()" class="text-gray-400 hover:text-red-500 transition" title="退出">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- 主内容 -->
    <main class="flex-1 bg-gray-50 min-h-screen">
      <div class="p-8">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, h } from 'vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

const roleLabels = {
    admin: '管理员',
    editor: '编辑',
    supervisor: '主管',
    final_approver: '终审',
};
const roleLabel = computed(() => roleLabels[authStore.role] || authStore.role);

// SVG 图标组件
const dashboardIcon = { template: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>' };
const articleIcon = { template: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>' };
const auditIcon = { template: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>' };
const wordIcon = { template: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>' };

const allNavItems = [
    { label: '工作台', path: '/', icon: dashboardIcon },
    { label: '稿件管理', path: '/articles', icon: articleIcon },
    { label: '审核中心', path: '/audit', icon: auditIcon, roles: ['editor', 'supervisor', 'final_approver', 'admin'] },
    { label: '敏感词', path: '/sensitive-words', icon: wordIcon, roles: ['admin'] },
];

const navItems = computed(() => allNavItems.filter(item => {
    if (!item.roles) return true;
    return item.roles.includes(authStore.role);
}));
</script>
