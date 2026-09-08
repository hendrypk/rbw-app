<script setup lang="ts">
defineProps<{
    isOpen: boolean;
    outlets: any[];
}>();

const emit = defineEmits(['select']);
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-[100] bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4 select-none">
        <div class="bg-white dark:bg-zinc-900 rounded-3xl p-6 w-full max-w-lg shadow-2xl">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-black text-slate-900 dark:text-white">Pilih Outlet Kasir</h2>
                <p class="text-slate-500 dark:text-zinc-400 mt-2">Pilih cabang yang akan Anda kelola transaksinya saat ini.</p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <button 
                    v-for="outlet in outlets" 
                    :key="outlet.id"
                    @click="emit('select', outlet)"
                    class="p-4 rounded-2xl border-2 border-slate-200 dark:border-zinc-800 hover:border-primary hover:bg-primary/5 transition-all text-left group cursor-pointer"
                >
                    <h3 class="font-bold text-lg text-slate-900 dark:text-zinc-100 group-hover:text-primary">{{ outlet.name }}</h3>
                    <p class="text-sm text-slate-500 line-clamp-2 mt-1">{{ outlet.address }}</p>
                </button>
            </div>

            <div v-if="!outlets || outlets.length === 0" class="text-center py-8 text-red-500 font-semibold">
                Akun Anda belum ditugaskan ke outlet mana pun. Hubungi Admin.
            </div>
        </div>
    </div>
</template>