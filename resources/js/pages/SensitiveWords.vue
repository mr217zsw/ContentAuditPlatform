<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">敏感词管理</h2>
        <p class="text-sm text-gray-500 mt-1">共 {{ words.length }} 个敏感词</p>
      </div>
      <button @click="showAdd = true"
        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-sm">
        添加敏感词
      </button>
    </div>

    <div class="flex gap-2 mb-6">
      <button v-for="lv in levelFilters" :key="lv.value" @click="currentLevel = lv.value"
        class="rounded-lg px-4 py-2 text-sm font-medium transition"
        :class="currentLevel === lv.value ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'">
        {{ lv.label }}
      </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
      <table v-if="filteredWords.length" class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="text-left px-6 py-3 font-medium text-gray-500">敏感词</th>
            <th class="text-center px-6 py-3 font-medium text-gray-500">风险等级</th>
            <th class="text-left px-6 py-3 font-medium text-gray-500 hidden sm:table-cell">创建时间</th>
            <th class="text-right px-6 py-3 font-medium text-gray-500">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="w in filteredWords" :key="w.id" class="hover:bg-gray-50/50 transition">
            <td class="px-6 py-3 font-medium text-gray-900">{{ w.word }}</td>
            <td class="px-6 py-3 text-center">
              <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium" :class="hitLevels[w.level] || ''">
                {{ levelLabels[w.level] || w.level }}
              </span>
            </td>
            <td class="px-6 py-3 text-xs text-gray-400 hidden sm:table-cell">{{ w.created_at ? new Date(w.created_at).toLocaleDateString('zh-CN') : '—' }}</td>
            <td class="px-6 py-3 text-right">
              <button @click="del(w)" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="py-20 text-center text-gray-400 text-sm">{{ loading ? '加载中...' : '暂无敏感词' }}</div>
    </div>

    <!-- 添加弹窗 -->
    <div v-if="showAdd" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">添加敏感词</h3>
        <form @submit.prevent="add" class="space-y-4">
          <input v-model="nw.word" type="text" required maxlength="100" placeholder="请输入敏感词"
            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"/>
          <select v-model="nw.level"
            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            <option value="low">低风险</option><option value="medium">中风险</option><option value="high">高风险</option><option value="forbidden">禁止</option>
          </select>
          <div v-if="addErr" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ addErr }}</div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showAdd = false; addErr = ''" class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">取消</button>
            <button type="submit" :disabled="adding" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">{{ adding ? '添加中...' : '确认添加' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from '@/axios';

const words = ref([]);
const loading = ref(true);
const currentLevel = ref('all');
const showAdd = ref(false);
const adding = ref(false);
const addErr = ref('');
const nw = ref({ word: '', level: 'medium' });

const levelFilters = [
  { label: '全部', value: 'all' }, { label: '禁止', value: 'forbidden' },
  { label: '高风险', value: 'high' }, { label: '中风险', value: 'medium' }, { label: '低风险', value: 'low' },
];
const levelLabels = { forbidden: '禁止', high: '高风险', medium: '中风险', low: '低风险' };
const hitLevels = { forbidden: 'bg-red-100 text-red-700', high: 'bg-orange-100 text-orange-700', medium: 'bg-yellow-100 text-yellow-700', low: 'bg-blue-100 text-blue-700' };

const filteredWords = computed(() => currentLevel.value === 'all' ? words.value : words.value.filter(w => w.level === currentLevel.value));

async function fetch() {
  loading.value = true;
  try {
    const params = currentLevel.value !== 'all' ? { level: currentLevel.value } : {};
    const { data } = await axios.get('/sensitive-words', { params });
    words.value = data.data;
  } catch (e) { console.error(e); } finally { loading.value = false; }
}

async function add() {
  adding.value = true; addErr.value = '';
  try { await axios.post('/sensitive-words', nw.value); showAdd.value = false; nw.value = { word: '', level: 'medium' }; await fetch(); }
  catch (e) { addErr.value = e.response?.data?.message || '添加失败'; }
  finally { adding.value = false; }
}

async function del(w) {
  if (!confirm(`确认删除"${w.word}"？`)) return;
  try { await axios.delete(`/sensitive-words/${w.id}`); words.value = words.value.filter(x => x.id !== w.id); }
  catch (e) { alert(e.response?.data?.message || '删除失败'); }
}

onMounted(fetch);
</script>
