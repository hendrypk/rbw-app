<!-- @/components/modals/ConfirmModal.vue -->
<script setup lang="ts">
defineProps<{
    show: boolean;
    title?: string;
    message?: string;
    confirmText?: string;
    cancelText?: string;
    loading?: boolean;
}>();

defineEmits(['close', 'confirm']);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-70 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-200">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-sm rounded-3xl border border-slate-100 dark:border-zinc-800 p-6 shadow-2xl text-center">
            
            <div class="w-12 h-12 bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <h3 class="text-lg font-black text-slate-900 dark:text-zinc-100 mb-1">
                {{ title || 'Konfirmasi Tindakan' }}
            </h3>
            
            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6 leading-relaxed">
                {{ message || 'Tindakan ini tidak dapat dibatalkan.' }}
            </p>

            <div class="flex items-center gap-3">
                <button 
                    type="button" 
                    @click="$emit('close')" 
                    :disabled="loading"
                    class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 transition active:scale-[0.98] cursor-pointer disabled:opacity-50"
                >
                    {{ cancelText || 'Batal' }}
                </button>

                <button 
                    type="button" 
                    @click="$emit('confirm')" 
                    :disabled="loading"
                    class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-white bg-red-600 hover:bg-red-700 transition active:scale-[0.98] shadow-md shadow-red-600/20 cursor-pointer disabled:opacity-50"
                >
                    {{ loading ? 'Memproses...' : (confirmText || 'Ya, Hapus') }}
                </button>
            </div>

        </div>
    </div>
</template>