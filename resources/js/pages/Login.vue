<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-indigo-600">内容审核平台</h1>
        <p class="mt-2 text-gray-500">请登录您的账号</p>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">邮箱</label>
            <input
              v-model="form.email"
              type="email"
              required
              placeholder="请输入邮箱"
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">密码</label>
            <input
              v-model="form.password"
              type="password"
              required
              placeholder="请输入密码"
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
            />
          </div>
          <div v-if="error" class="text-sm text-red-600 bg-red-50 rounded-lg px-4 py-2">
            {{ error }}
          </div>
          <button type="submit" :disabled="loading"
            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition">
            {{ loading ? '登录中...' : '登录' }}
          </button>
        </form>

        <!-- 演示账号提示 -->
        <div class="mt-6 pt-4 border-t border-gray-100">
          <p class="text-xs text-gray-400 mb-2">演示账号：</p>
          <div class="space-y-1 text-xs text-gray-500">
            <p>管理员: admin@audit.local / admin123</p>
            <p>编辑: editor@audit.local / editor123</p>
            <p>主管: supervisor@audit.local / supervisor123</p>
            <p>终审: final@audit.local / final123</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();
const loading = ref(false);
const error = ref('');
const form = reactive({ email: '', password: '' });

async function handleLogin() {
  loading.value = true;
  error.value = '';
  try {
    await authStore.login(form.email, form.password);
    router.push('/');
  } catch (e) {
    error.value = e.response?.data?.message || '登录失败，请检查账号密码';
  } finally {
    loading.value = false;
  }
}
</script>
