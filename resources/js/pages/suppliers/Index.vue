<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useSuppliers } from '@/composables/useSuppliers'; 
import { useOutlet } from '@/composables/useOutlet';
import SupplierModal from './SupplierModal.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal';
import axios from 'axios';
import ConifrmModal from '@/components/ConifrmModal.vue';

defineOptions({ layout: AppSidebarLayout });

const showModal = ref(false);
const activeSupplier = ref<any>(null);
const { success, error } = useSwal();
const { getOutletParam } = useOutlet();

const { suppliers, isLoading, fetchSuppliers } = useSuppliers();

const loadData = () => {
    const params = getOutletParam();
    fetchSuppliers(params);
};

const handleOutletChanged = () => {
    loadData();
};

onMounted(() => {
    loadData();
    window.addEventListener('outlet-changed', handleOutletChanged);
});

onUnmounted(() => {
    window.removeEventListener('outlet-changed', handleOutletChanged);
});

const openCreate = () => {
    activeSupplier.value = null;
    showModal.value = true;
};

const openEdit = (supplier: any) => {
    activeSupplier.value = supplier;
    showModal.value = true;
};

const handleSaved = () => {
    loadData();
};

// State Confirm Modal
const isConfirmModalOpen = ref(false);
const isDeleting = ref(false);
const confirmModalConfig = ref({
    title: 'Hapus Supplier?',
    message: 'Tindakan ini tidak dapat dibatalkan. Supplier akan dihapus secara permanen dari sistem.',
    confirmText: 'Ya, Hapus',
    action: async () => {}
});

const remove = (supplier: any) => {
    confirmModalConfig.value = {
        title: 'Hapus Supplier?',
        message: `Supplier "${supplier.name}" akan dihapus permanen dari sistem.`,
        confirmText: 'Ya, Hapus',
        action: async () => {
            isDeleting.value = true;
            try {
                const response = await axios.delete(`/api/suppliers/${supplier.id}`);
                success('Berhasil', response.data.message);
                loadData();
                isConfirmModalOpen.value = false;
            } catch (err: any) {
                const errorMessage = err.response?.data?.message || 'Terjadi kesalahan saat menghapus.';
                error('Gagal', errorMessage);
                isConfirmModalOpen.value = false;

            } finally {
                isDeleting.value = false;
            }
        }
    };
    isConfirmModalOpen.value = true;
};

const selectedIds = ref<string[]>([]);

const toggleSelectAll = () => {
    if (selectedIds.value.length === suppliers.value.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = suppliers.value.map((s: any) => s.id);
    }
};

const bulkDelete = () => {
    confirmModalConfig.value = {
        title: 'Hapus Supplier Terpilih?',
        message: `Sebanyak ${selectedIds.value.length} supplier terpilih akan dihapus permanen.`,
        confirmText: 'Ya, Hapus Semua',
        action: async () => {
            isDeleting.value = true;
            try {
                const payload = { ids: selectedIds.value };
                const response = await axios.post('/api/suppliers/bulk-delete', payload);
                
                selectedIds.value = [];
                success('Berhasil', response.data.message);
                loadData();
                isConfirmModalOpen.value = false;
            } catch (err: any) {
                error('Gagal', err.response?.data?.message || 'Terjadi kesalahan.');
            } finally {
                isDeleting.value = false;
            }
        }
    };
    isConfirmModalOpen.value = true;
};
</script>

<template>
    <div class="p-6 sm:p-8 space-y-8 max-w-full overflow-x-hidden font-sans">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pb-2">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">Daftar Supplier</h1>
                <p class="text-xs sm:text-sm text-muted-foreground mt-1">
                    Kelola data rekanan supplier bahan baku secara real-time.
                </p>
            </div>
            
            <div class="flex items-center gap-2.5 sm:shrink-0">
                <Button size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm bg-foreground text-background hover:opacity-90 transition-all" @click="openCreate">
                    + New Supplier
                </Button>
            </div>
        </div>

        <div class="space-y-4">
            <div 
                v-if="selectedIds.length > 0" 
                class="flex items-center justify-between rounded-2xl bg-destructive/10 px-5 py-3 border border-destructive/20 animate-in fade-in zoom-in-95 duration-200 shadow-xs"
            >
                <div class="flex items-center gap-2.5 text-xs">
                    <span class="inline-flex items-center justify-center h-6 px-2 rounded-lg bg-destructive text-destructive-foreground font-bold text-xs">
                        {{ selectedIds.length }}
                    </span>
                    <span class="font-semibold text-foreground">supplier dipilih untuk dimodifikasi secara massal</span>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="selectedIds = []" class="text-muted-foreground hover:text-foreground text-xs font-semibold">
                        Batal
                    </button>
                    <Button variant="destructive" size="sm" class="h-8 rounded-xl text-xs font-bold px-4" @click="bulkDelete">
                        Hapus Terpilih
                    </Button>
                </div>
            </div>

            <div v-if="isLoading" class="mt-12 text-center text-muted-foreground text-xs font-medium">
                Memuat data supplier...
            </div>

            <div v-else-if="suppliers.length > 0" class="w-full">
                <div class="overflow-x-auto rounded-2xl border border-border/70 bg-card shadow-xs w-full">
                    <table class="w-full text-sm text-left min-w-[750px]">
                        <thead class="bg-secondary/60 text-muted-foreground text-xs border-b border-border/70">
                            <tr>
                                <th class="px-5 py-3.5 w-10">
                                    <input 
                                        type="checkbox" 
                                        :checked="selectedIds.length === suppliers.length && suppliers.length > 0" 
                                        @change="toggleSelectAll" 
                                        class="rounded border-border accent-primary cursor-pointer" 
                                    />
                                </th>
                                <th class="px-5 py-3.5 font-bold">Nama Supplier</th>
                                <th class="px-5 py-3.5 font-bold">Status</th>
                                <th class="px-5 py-3.5 font-bold">Kontak</th>
                                <th class="px-5 py-3.5 font-bold">Alamat</th>
                                <th class="px-5 py-3.5 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        
                        <tbody class="divide-y divide-border/60 text-xs">
                            <tr v-for="s in suppliers" :key="s.id" class="hover:bg-secondary/40 transition-colors">
                                <td class="px-5 py-4">
                                   <input type="checkbox" v-model="selectedIds" :value="s.id" class="rounded border-border accent-primary cursor-pointer" />
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-foreground">{{ s.name }}</div>
                                    <div class="text-muted-foreground text-[11px] mt-0.5">{{ s.email || 'Tidak ada email' }}</div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span :class="s.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold' : 'bg-secondary text-muted-foreground font-semibold'" class="px-2.5 py-1 rounded-full text-[10px]">
                                        {{ s.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-muted-foreground font-medium whitespace-nowrap">
                                    {{ s.phone || '-' }}
                                </td>
                                <td class="px-5 py-4 text-muted-foreground max-w-xs truncate">
                                    {{ s.address || '-' }}
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="outline" size="sm" class="h-8 px-3 rounded-xl text-xs font-semibold" @click="openEdit(s)">Edit</Button>
                                        <Button variant="destructive" size="sm" class="h-8 px-3 rounded-xl text-xs font-semibold" @click="remove(s)">Hapus</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-24 text-center border border-dashed rounded-3xl bg-card border-border/80 shadow-xs">
                <h3 class="text-sm font-bold text-foreground">Data tidak ditemukan</h3>
                <p class="mt-1 text-xs text-muted-foreground">Belum ada rekanan supplier terdaftar di outlet ini.</p>
            </div>
        </div>
    </div>

    <SupplierModal 
        :show="showModal" 
        :supplier="activeSupplier"
        @close="showModal = false" 
        @saved="handleSaved" 
    />

    <ConifrmModal 
        :show="isConfirmModalOpen"
        :title="confirmModalConfig.title"
        :message="confirmModalConfig.message"
        :confirmText="confirmModalConfig.confirmText"
        :loading="isDeleting"
        @close="isConfirmModalOpen = false"
        @confirm="confirmModalConfig.action"
    />
</template>