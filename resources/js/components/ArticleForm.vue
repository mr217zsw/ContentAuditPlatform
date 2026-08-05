<template>
  <div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
      <!-- 头部 -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900">{{ isEdit ? '编辑稿件' : '新建稿件' }}</h3>
        <button @click="$emit('close')" class="p-1 text-gray-400 hover:text-gray-600 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- 表单主体 -->
      <form @submit.prevent="handleSubmit" class="flex-1 overflow-auto px-6 py-4 space-y-4">
        <!-- 标题 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">标题</label>
          <input
            v-model="form.title"
            type="text"
            required
            maxlength="200"
            placeholder="请输入稿件标题"
            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
          />
          <p class="text-xs text-gray-400 mt-1">{{ form.title.length }}/200</p>
        </div>

        <!-- 正文 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">正文内容</label>
          <textarea
            v-model="form.content"
            required
            rows="8"
            placeholder="请输入稿件正文内容..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition resize-none"
          ></textarea>
        </div>

        <!-- 敏感词检测提示 -->
        <div v-if="sensitiveHits.length > 0" class="rounded-lg p-3"
          :class="levelColor[maxLevel] || 'bg-gray-50'">
          <div class="flex items-center gap-2 mb-2">
            <svg class="w-5 h-5" :class="{
              'text-red-500': maxLevel === 'forbidden',
              'text-orange-500': maxLevel === 'high',
              'text-yellow-500': maxLevel === 'medium',
              'text-blue-500': maxLevel === 'low',
            }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <span class="text-sm font-medium"
              :class="{
                'text-red-700': maxLevel === 'forbidden',
                'text-orange-700': maxLevel === 'high',
                'text-yellow-700': maxLevel === 'medium',
                'text-blue-700': maxLevel === 'low',
              }">
              检测到 {{ sensitiveHits.length }} 个敏感词 ({{ levelLabel[maxLevel] || '未知' }})
            </span>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="(hit, i) in sensitiveHits" :key="i"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
              :class="{
                'bg-red-100 text-red-700': hit.level === 'forbidden',
                'bg-orange-100 text-orange-700': hit.level === 'high',
                'bg-yellow-100 text-yellow-700': hit.level === 'medium',
                'bg-blue-100 text-blue-700': hit.level === 'low',
              }">
              {{ hit.word }}
              <span class="text-[10px] opacity-60">({{ levelLabel[hit.level] }})</span>
            </span>
          </div>
        </div>

        <!-- 提示 -->
        <div v-if="sensitiveHits.length === 0 && (form.title || form.content)" class="flex items-center gap-2 text-sm text-green-600 bg-green-50 rounded-lg px-3 py-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          未检测到敏感词
        </div>

        <!-- API 错误 -->
        <div v-if="apiError" class="text-sm text-red-600 bg-red-50 rounded-lg px-4 py-2">
          {{ apiError }}
        </div>

        <!-- 底部按钮 -->
        <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
          <button type="button" @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
            取消
          </button>
          <button type="submit" :disabled="submitting"
            class="px-6 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition">
            {{ submitting ? '保存中...' : '保存稿件' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch, onMounted } from 'vue';
import { useSensitiveWordCheck } from '@/composables/useSensitiveWordCheck';
import { useArticleStore } from '@/stores/article';

const props = defineProps({
    article: { type: Object, default: null },
});
const emit = defineEmits(['close', 'saved']);

const isEdit = !!props.article;
const store = useArticleStore();
const { hits: sensitiveHits, maxLevel, levelLabel, levelColor, debouncedCheck } = useSensitiveWordCheck();

const form = reactive({
    title: props.article?.title || '',
    content: props.article?.content || '',
});

const submitting = ref(false);
const apiError = ref('');

// 实时敏感词检测（防抖 500ms）
watch(
    () => form.title + form.content,
    (val) => debouncedCheck(val, 500)
);

async function handleSubmit() {
    submitting.value = true;
    apiError.value = '';

    try {
        if (isEdit) {
            await store.updateArticle(props.article.id, { title: form.title, content: form.content });
        } else {
            await store.createArticle({ title: form.title, content: form.content });
        }
        emit('saved');
        emit('close');
    } catch (e) {
        apiError.value = e.response?.data?.message || '保存失败，请重试';
    } finally {
        submitting.value = false;
    }
}
</script>
