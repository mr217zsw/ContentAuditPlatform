<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
    :class="statusClass"
  >
    <span class="w-1.5 h-1.5 rounded-full" :class="statusDot"></span>
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const statusMap = {
    draft:             { label: '草稿',    class: 'bg-gray-100 text-gray-700',  dot: 'bg-gray-400' },
    pending:           { label: '待初审',  class: 'bg-yellow-100 text-yellow-800', dot: 'bg-yellow-400' },
    editor_approved:   { label: '编辑通过', class: 'bg-blue-100 text-blue-800',   dot: 'bg-blue-400' },
    chief_approved:    { label: '终审通过', class: 'bg-green-100 text-green-800',  dot: 'bg-green-400' },
    published:         { label: '已发布',  class: 'bg-emerald-100 text-emerald-800', dot: 'bg-emerald-500' },
    rejected:          { label: '已驳回',  class: 'bg-red-100 text-red-800',    dot: 'bg-red-400' },
};

const label = computed(() => statusMap[props.status]?.label || props.status);
const statusClass = computed(() => statusMap[props.status]?.class || 'bg-gray-100 text-gray-700');
const statusDot = computed(() => statusMap[props.status]?.dot || 'bg-gray-400');
</script>
