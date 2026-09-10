<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-slate-400 dark:placeholder:text-zinc-500 selection:bg-primary selection:text-primary-foreground dark:bg-zinc-900/50 border-slate-200 dark:border-zinc-800 h-11 w-full min-w-0 rounded-2xl border bg-slate-50/50 px-4 py-2 text-sm shadow-2xs transition-all duration-200 outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
      'focus:border-primary focus:ring-2 focus:ring-primary/20 hover:border-slate-300 dark:hover:border-zinc-700',
      'aria-invalid:ring-rose-500/20 dark:aria-invalid:ring-rose-500/40 aria-invalid:border-rose-500',
      props.class,
    )"
  >
</template>