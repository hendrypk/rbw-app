<!-- @/components/modals/Modal.vue -->
<script setup lang="ts">
import { X } from '@lucide/vue';

defineProps<{
    show: boolean;
    title: string;
    maxWidth?: string; // Contoh: 'max-w-md', 'max-w-xl'
}>();

defineEmits(['close']);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
        <div :class="['bg-white dark:bg-zinc-900 w-full rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col max-h-[90vh]', maxWidth || 'max-w-md']">
            
            <!-- Header -->
            <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-800/50">
                <h4 class="font-black text-sm sm:text-base text-slate-900 dark:text-zinc-100 flex items-center gap-2">
                    <slot name="icon"></slot>
                    {{ title }}
                </h4>
                <button @click="$emit('close')" class="p-1.5 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition cursor-pointer">
                    <X class="h-4 w-4" />
                </button>
            </div>
            
            <!-- Body Content -->
            <div class="p-6 overflow-y-auto flex-1">
                <slot></slot>
            </div>

        </div>
    </div>
</template>