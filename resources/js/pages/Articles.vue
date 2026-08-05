<template>
  <div>
    <!-- 顶部工具栏 -->
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-gray-900">稿件管理</h2>
      <button
        @click="showForm = true; editingArticle = null"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-sm"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        新建稿件
      </button>
    </div>

    <!-- 状态筛选标签 -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-1">
      <button
        v-for="tab in tabs" :key="tab.value"
        @click="switchTab(tab.value)"
        class="whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition"
        :class="currentTab === tab.value
          ? 'bg-indigo-600 text-white shadow-sm'
          : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- 稿件列表 -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <table v-if="store.articles.length" class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="text-left px-6 py-3 font-medium text-gray-500">标题</th>
            <th class="text-left px-6 py-3 font-medium text-gray-500 hidden md:table-cell">作者</th>
            <th class="text-center px-6 py-3 font-medium text-gray-500">状态</th>
            <th class="text-left px-6 py-3 font-medium text-gray-500 hidden lg:table-cell">敏感词</th>
            <th class="text-right px-6 py-3 font-medium text-gray-500 hidden sm:table-cell">更新</th>
            <th class="text-right px-6 py-3 font-medium text-gray-500">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="article in store.articles" :key="article.id" class="hover:bg-gray-50/50 transition">
            <td class="px-6 py-4">
              <div class="max-w-xs">
                <p class="font-medium text-gray-900 truncate">{{ article.title }}</p>
                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ (article.content || '').substring(0, 60) }}...</p>
              </div>
            </td>
            <td class="px-6 py-4 text-gray-600 hidden md:table-cell">{{ article.author?.name || '—' }}</td>
            <td class="px-6 py-4 text-center"><StatusBadge :status="article.status" /></td>
            <td class="px-6 py-4 hidden lg:table-cell">
              <template v-if="article.sensitive_words_hit?.length">
                <span v-for="(hit, i) in article.sensitive_words_hit.slice(0, 3)" :key="i"
                  class="inline-flex mr-1 px-2 py-0.5 rounded text-xs font-medium"
                  :class="hitLevelClass(hit.level)">{{ hit.word }}</span>
                <span v-if="article.sensitive_words_hit.length > 3" class="text-xs text-gray-400">
                  +{{ article.sensitive_words_hit.length - 3 }}
                </span>
              </template>
              <span v-else class="text-xs text-green-600">无</span>
            </td>
            <td class="px-6 py-4 text-right text-gray-400 text-xs hidden sm:table-cell">{{ fmtDate(article.updated_at) }}</td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-1">
                <button v-if="['draft','rejected'].includes(article.status)"
                  @click="edit(article)" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="编辑">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button v-if="article.status === 'draft'"
                  @click="doSubmit(article)" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition" title="提交审核">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
                <button v-if="article.status === 'draft'"
                  @click="doDelete(article)" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition" title="删除">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <span v-if="!['draft','rejected'].includes(article.status)" class="text-xs text-gray-300">—</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- 空状态 -->
      <div v-else class="py-20 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="mt-4 text-gray-400">{{ store.loading ? '加载中...' : '暂无稿件' }}</p>
      </div>

      <!-- 分页 -->
      <div v-if="store.pagination.last_page > 1" class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
        <span class="text-sm text-gray-500">共 {{ store.pagination.total }} 篇</span>
        <div class="flex gap-1">
          <button :disabled="store.pagination.current_page <= 1"
            @click="store.fetchArticles(store.pagination.current_page - 1)"
            class="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40">上一页</button>
          <button v-for="p in pages" :key="p" @click="store.fetchArticles(p)"
            class="px-3 py-2 text-sm border rounded-lg transition"
            :class="p === store.pagination.current_page ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 hover:bg-gray-50'">{{ p }}</button>
          <button :disabled="store.pagination.current_page >= store.pagination.last_page"
            @click="store.fetchArticles(store.pagination.current_page + 1)"
            class="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40">下一页</button>
        </div>
      </div>
    </div>

    <!-- 弹窗 -->
    <ArticleForm v-if="showForm" :article="editingArticle"
      @close="showForm = false; editingArticle = null"
      @saved="showForm = false; editingArticle = null" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import ArticleForm from '@/components/ArticleForm.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { useArticleStore } from '@/stores/article';

const store = useArticleStore();
const showForm = ref(false);
const editingArticle = ref(null);
const currentTab = ref('all');

const tabs = [
  { label: '全部', value: 'all' },
  { label: '草稿', value: 'draft' },
  { label: '待审核', value: 'pending' },
  { label: '编辑通过', value: 'editor_approved' },
  { label: '已发布', value: 'published' },
  { label: '已驳回', value: 'rejected' },
];

const pages = computed(() => {
  const current = store.pagination.current_page;
  const last = store.pagination.last_page;
  const range = [];
  for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) range.push(i);
  return range;
});

const hitLevels = { forbidden: 'bg-red-100 text-red-700', high: 'bg-orange-100 text-orange-700', medium: 'bg-yellow-100 text-yellow-700', low: 'bg-blue-100 text-blue-700' };
function hitLevelClass(lv) { return hitLevels[lv] || ''; }

function switchTab(v) { currentTab.value = v; store.setFilter(v); }
function edit(a) { editingArticle.value = a; showForm.value = true; }

async function doSubmit(a) {
  if (!confirm('确认提交审核？提交后将不可编辑。')) return;
  try { await store.submitArticle(a.id); } catch (e) { alert(e.response?.data?.message || '提交失败'); }
}

async function doDelete(a) {
  if (!confirm(`确认删除稿件"${a.title}"？`)) return;
  try { await store.deleteArticle(a.id); } catch (e) { alert(e.response?.data?.message || '删除失败'); }
}

function fmtDate(d) { return d ? new Date(d).toLocaleDateString('zh-CN', { month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit' }) : '—'; }

onMounted(() => store.fetchArticles());
</script>
