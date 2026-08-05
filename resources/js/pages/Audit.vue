<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">审核中心</h2>
        <p class="text-sm text-gray-500 mt-1">
          待审核: <span class="font-semibold text-indigo-600">{{ store.pendingCount }}</span> 篇
        </p>
      </div>
    </div>

    <!-- 待审核列表 -->
    <div v-if="store.pendingArticles.length" class="space-y-4">
      <div v-for="article in store.pendingArticles" :key="article.id"
        class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-gray-900 truncate">{{ article.title }}</h4>
            <p class="text-sm text-gray-600 mt-2 line-clamp-3">{{ article.content }}</p>

            <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-gray-400">
              <span>作者: {{ article.author?.name || '—' }}</span>
              <span>提交: {{ fmtDate(article.submitted_at) }}</span>
              <span class="font-medium text-indigo-600">{{ levelMap[article.approval_level] || article.approval_level }}</span>
            </div>

            <!-- 敏感词 -->
            <div v-if="article.sensitive_words_hit?.length" class="flex flex-wrap gap-1.5 mt-3">
              <span v-for="(hit, i) in article.sensitive_words_hit" :key="i"
                class="inline-flex px-2 py-0.5 rounded text-xs font-medium"
                :class="hitLevels[hit.level] || ''">
                {{ hit.word }} ({{ hit.level }})
              </span>
            </div>
          </div>

          <div v-if="!actioning || actioning.id !== article.id" class="flex flex-col gap-2 flex-shrink-0">
            <button @click="openApprove(article)"
              class="px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition shadow-sm">通过</button>
            <button @click="openReject(article)"
              class="px-4 py-2 text-sm font-semibold text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition">驳回</button>
          </div>
        </div>

        <!-- 审核操作 -->
        <div v-if="actioning?.id === article.id" class="mt-4 pt-4 border-t border-gray-100">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ actionType === 'reject' ? '驳回原因 *' : '审核意见（可选）' }}
          </label>
          <textarea v-model="comment" rows="3" required
            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 outline-none transition resize-none"
            :class="actionType === 'reject' ? 'focus:ring-red-500' : 'focus:ring-green-500'"
            :placeholder="actionType === 'reject' ? '请输入驳回原因...' : '可输入审核意见...'"></textarea>
          <div class="flex items-center gap-3 mt-3">
            <button @click="confirm(article)" :disabled="submitting || (actionType==='reject' && !comment.trim())"
              class="px-4 py-2 text-sm font-semibold text-white rounded-lg transition disabled:opacity-50"
              :class="actionType === 'reject' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'">
              {{ submitting ? '处理中...' : (actionType === 'reject' ? '确认驳回' : '确认通过') }}
            </button>
            <button @click="cancelAction" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">取消</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 空 -->
    <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm py-20 text-center">
      <svg class="w-16 h-16 mx-auto text-green-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="mt-4 text-gray-400">暂无待审核稿件</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAuditStore } from '@/stores/audit';

const store = useAuditStore();
const actioning = ref(null);
const actionType = ref('');
const comment = ref('');
const submitting = ref(false);

const levelMap = { editor: '初审', supervisor: '主管审核', final: '终审' };
const hitLevels = { forbidden: 'bg-red-100 text-red-700', high: 'bg-orange-100 text-orange-700', medium: 'bg-yellow-100 text-yellow-700', low: 'bg-blue-100 text-blue-700' };

function openApprove(a) { actioning.value = a; actionType.value = 'approve'; comment.value = ''; }
function openReject(a) { actioning.value = a; actionType.value = 'reject'; comment.value = ''; }
function cancelAction() { actioning.value = null; actionType.value = ''; comment.value = ''; }

async function confirm(article) {
  submitting.value = true;
  try {
    if (actionType.value === 'approve') await store.approve(article.id, comment.value);
    else await store.reject(article.id, comment.value);
    cancelAction();
    await store.fetchPending();
  } catch (e) {
    alert(e.response?.data?.message || '操作失败');
  } finally { submitting.value = false; }
}

function fmtDate(d) { return d ? new Date(d).toLocaleDateString('zh-CN', { month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit' }) : '—'; }

onMounted(() => store.fetchPending());
</script>
