<script setup lang="ts">

import { computed } from 'vue'

const props = defineProps({ meta: { type: Object, default: () => ({}) } })
defineEmits(['change'])

const pages = computed(() => {
  const { current_page: cur, last_page: last } = props.meta
  if (!last || last <= 7) return Array.from({ length: last ?? 0 }, (_, i) => i + 1)
  const p = []
  if (cur > 3) p.push(1, '…')
  for (let i = Math.max(1, cur - 1); i <= Math.min(last, cur + 1); i++) p.push(i)
  if (cur < last - 2) p.push('…', last)
  return p
})
</script>
<template>
  <div v-if="meta.last_page > 1" class="flex items-center justify-between pt-4 border-t border-gray-100 mt-4">
    <p class="text-sm text-gray-400">
      {{ meta.from }}–{{ meta.to }} dari {{ meta.total }} data
    </p>
    <div class="flex gap-1">
      <button v-for="p in pages" :key="p"
        class="px-3 py-1 rounded text-sm"
        :class="p === meta.current_page
          ? 'bg-indigo-600 text-white'
          : 'text-gray-600 hover:bg-gray-100'"
        @click="p !== '…' && $emit('change', p)">
        {{ p }}
      </button>
    </div>
  </div>
</template>