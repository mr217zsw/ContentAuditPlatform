<template>
  <div>
    <h2 class="text-2xl font-bold text-gray-900 mb-6">工作台</h2>

    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow transition">
        <p class="text-sm text-gray-500">待审核稿件</p>
        <p class="text-3xl font-bold mt-1 text-yellow-500">
          {{ loading ? '—' : stats?.pending_count ?? 0 }}
        </p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow transition">
        <p class="text-sm text-gray-500">今日通过</p>
        <p class="text-3xl font-bold mt-1 text-green-500">
          {{ loading ? '—' : stats?.today_approved ?? 0 }}
        </p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow transition">
        <p class="text-sm text-gray-500">今日驳回</p>
        <p class="text-3xl font-bold mt-1 text-red-500">
          {{ loading ? '—' : stats?.today_rejected ?? 0 }}
        </p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow transition">
        <p class="text-sm text-gray-500">本月总计</p>
        <p class="text-3xl font-bold mt-1 text-indigo-500">
          {{ loading ? '—' : stats?.month_total ?? 0 }}
        </p>
      </div>
    </div>

    <!-- 审核趋势图 -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">近7天审核趋势</h3>
      <div v-if="stats?.trend?.length" class="flex items-end gap-2 h-40">
        <div v-for="day in stats.trend" :key="day.date" class="flex-1 flex flex-col items-center gap-1">
          <div class="w-full flex flex-col items-center gap-0.5">
            <span class="text-xs font-semibold text-green-600">{{ day.approved }}</span>
            <div class="w-full bg-green-200 rounded-t transition-all"
              :style="{ height: Math.max(day.approved * 20, 4) + 'px' }"></div>
          </div>
          <div class="w-full flex flex-col items-center gap-0.5">
            <div class="w-full bg-red-200 rounded-t transition-all"
              :style="{ height: Math.max(day.rejected * 20, 4) + 'px' }"></div>
            <span class="text-xs font-semibold text-red-600">{{ day.rejected }}</span>
          </div>
          <span class="text-[10px] text-gray-400 mt-1">{{ (day.date || '').slice(5) }}</span>
        </div>
      </div>
      <div v-else class="py-8 text-center text-sm text-gray-400">
        {{ loading ? '加载中...' : '暂无数据' }}
      </div>
      <div v-if="stats?.trend?.length" class="flex items-center gap-6 mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-green-200 rounded"></span> 通过</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-red-200 rounded"></span> 驳回</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useDashboardStore } from '@/stores/dashboard';

const store = useDashboardStore();
const stats = ref(null);
const loading = ref(true);

onMounted(async () => {
  loading.value = true;
  await store.fetchStats();
  stats.value = store.stats;
  loading.value = false;
});
</script>
