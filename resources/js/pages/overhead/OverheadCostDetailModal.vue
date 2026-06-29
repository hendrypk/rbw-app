<script setup lang="ts">
defineProps({
    show: Boolean,
    overhead: Object
});
const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-in fade-in duration-200">
        <div class="bg-card border border-border w-full max-w-md rounded-xl shadow-lg overflow-hidden animate-in zoom-in-95 duration-200 text-foreground">
            <div class="px-6 py-4 border-b border-border flex items-center justify-between">
                <h3 class="text-lg font-semibold">Detail Komponen Overhead</h3>
                <button @click="emit('close')" class="text-muted-foreground hover:text-foreground text-xl">&times;</button>
            </div>
            
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-3 gap-2 border-b border-border/50 pb-2">
                    <span class="text-xs text-muted-foreground">ID Sistem</span>
                    <span class="text-xs font-mono col-span-2 text-gray-500">{{ overhead?.id }}</span>
                </div>
                <div class="grid grid-cols-3 gap-2 border-b border-border/50 pb-2">
                    <span class="text-xs text-muted-foreground">Nama Komponen</span>
                    <span class="text-xs font-medium col-span-2">{{ overhead?.name }}</span>
                </div>
                <div class="grid grid-cols-3 gap-2 border-b border-border/50 pb-2">
                    <span class="text-xs text-muted-foreground">Nominal Beban</span>
                    <span class="text-xs font-semibold col-span-2 text-red-600 dark:text-red-400">
                        Rp {{ Number(overhead?.amount || 0).toLocaleString() }}
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-2 border-b border-border/50 pb-2">
                    <span class="text-xs text-muted-foreground">Tipe Atribusi</span>
                    <span class="text-xs capitalize col-span-2">{{ overhead?.type || 'per_porsi' }}</span>
                </div>
                <div class="grid grid-cols-3 gap-2 pb-1">
                    <span class="text-xs text-muted-foreground">Status Aktif</span>
                    <div class="col-span-2">
                        <span :class="overhead?.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'" class="px-2 py-0.5 rounded-full text-xs font-medium">
                            {{ overhead?.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-muted/30 border-t border-border flex justify-end">
                <Button variant="outline" size="sm" @click="emit('close')">Tutup Detail</Button>
            </div>
        </div>
    </div>
</template>