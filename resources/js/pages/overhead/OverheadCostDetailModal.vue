<script setup lang="ts">
import { computed } from 'vue';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/button/Button.vue';
import { Receipt, Link as LinkIcon, UtensilsCrossed } from '@lucide/vue';

// Definisikan struktur data menu yang terhubung
interface ConnectedMenu {
    id: string;
    name: string;
    code?: string;
}

interface Overhead {
    id: string;
    name: string;
    amount: number;
    type: string;
    started_at?: string | null;
    is_active: boolean;
    menus?: ConnectedMenu[]; // Tambahan relasi menu
}

const props = defineProps<{
    show: boolean;
    overhead: Overhead | null;
}>();

const emit = defineEmits(['close', 'connect-menu']);

// Helper untuk format mata uang
const formatCurrency = (value: number | undefined) => {
    if (value === undefined) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(value);
};

// Helper untuk format tipe
const formatType = (type: string | undefined) => {
    if (!type) return '-';
    return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>

<template>
    <Modal :show="show" title="Detail Komponen Biaya" maxWidth="max-w-md" @close="$emit('close')">
        <template #icon>
            <Receipt class="w-5 h-5 text-primary" />
        </template>

        <div v-if="overhead" class="space-y-6">
            <!-- Informasi Dasar (Read Only) -->
            <div class="bg-slate-50 dark:bg-zinc-900/50 p-4 rounded-xl border border-slate-100 dark:border-zinc-800 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="text-muted-foreground font-medium mb-1">Nama Komponen</p>
                        <p class="font-semibold text-foreground">{{ overhead.name }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground font-medium mb-1">Nominal</p>
                        <p class="font-bold text-red-600 dark:text-red-400">{{ formatCurrency(overhead.amount) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground font-medium mb-1">Tipe Pembebanan</p>
                        <p class="font-semibold text-foreground">{{ formatType(overhead.type) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground font-medium mb-1">Status</p>
                        <span :class="overhead.is_active ? 'text-green-700 bg-green-100' : 'text-gray-700 bg-gray-100'"
                              class="px-2 py-0.5 rounded-md font-semibold text-[10px]">
                            {{ overhead.is_active ? 'Aktif' : 'Non-aktif' }}
                        </span>
                    </div>
                    <div v-if="overhead.type !== 'per_porsi'" class="col-span-2">
                        <p class="text-muted-foreground font-medium mb-1">Tanggal Mulai Berlaku</p>
                        <p class="font-semibold text-foreground">{{ overhead.started_at || 'Belum diatur' }}</p>
                    </div>
                </div>
            </div>

            <!-- Daftar Menu Terhubung -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                        <UtensilsCrossed class="w-4 h-4 text-muted-foreground" />
                        Menu Terhubung
                    </h3>

                    <!-- Tombol Hubungkan ke Menu -->
                    <Button variant="outline" size="sm" class="h-8 px-3 rounded-lg text-xs font-semibold flex items-center gap-1.5" @click="$emit('connect-menu', overhead)">
                        <LinkIcon class="w-3.5 h-3.5" />
                        Hubungkan Menu
                    </Button>
                </div>

                <div v-if="overhead.menus && overhead.menus.length > 0" class="border border-border/60 rounded-xl overflow-hidden">
                    <ul class="divide-y divide-border/60 text-xs">
                        <li v-for="menu in overhead.menus" :key="menu.id" class="px-3 py-2.5 flex items-center justify-between hover:bg-muted/30 transition-colors">
                            <span class="font-medium text-foreground">{{ menu.name }}</span>
                            <span v-if="menu.code" class="text-muted-foreground font-mono bg-secondary px-1.5 py-0.5 rounded text-[10px]">{{ menu.code }}</span>
                        </li>
                    </ul>
                </div>

                <div v-else class="text-center py-6 border border-dashed border-border/80 rounded-xl bg-card">
                    <p class="text-xs text-muted-foreground mb-2">Belum ada menu yang terhubung dengan biaya ini.</p>
                </div>
            </div>

            <!-- Footer Action -->
            <div class="flex items-center justify-end pt-2">
                <Button type="button" variant="default" size="sm" class="h-9 px-6 rounded-xl text-xs font-semibold" @click="$emit('close')">
                    Tutup
                </Button>
            </div>
        </div>
    </Modal>
</template>
