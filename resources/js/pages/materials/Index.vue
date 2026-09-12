<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import MaterialModal from './MaterialModal.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal';
import { useOutlet } from '@/composables/useOutlet';
import axios from 'axios';
import { useMaterials } from '@/composables/useMaterials.js';
import ConifrmModal from '@/components/ConifrmModal.vue';
import { Search } from '@lucide/vue';
import MaterialHistoryModal from './MaterialHistoryModal.vue';

defineOptions({ layout: AppSidebarLayout });

const showHistoryModal = ref(false);
const selectedMaterialForHistory = ref<any>(null);
const showModal = ref(false);
const activeMaterial = ref(null);
const searchQuery = ref('');
const { success, error } = useSwal();
const { getOutletParam } = useOutlet();
const {
    materials,
    meta,
    isLoading,
    fetchMaterials,
} = useMaterials();

const currentPage = ref(1);

const loadMaterials = (page = 1) => {
    currentPage.value = page;
    const outletParams = getOutletParam();
    fetchMaterials({ 
        page: page, 
        search: searchQuery.value,
        ...outletParams 
    });
};

// Watch pencarian dengan debounce sederhana atau trigger langsung
watch(searchQuery, () => {
    loadMaterials(1);
});

const handleOutletChanged = () => {
    loadMaterials(1);
};

onMounted(() => {
    loadMaterials();
    window.addEventListener('outlet-changed', handleOutletChanged);
});

onUnmounted(() => {
    window.removeEventListener('outlet-changed', handleOutletChanged);
});

const formatNumber = (value: number | string) => {
    const num = Number(value); 
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};


const openHistory = (material: any) => {
    selectedMaterialForHistory.value = material;
    showHistoryModal.value = true;
};

const openCreate = () => {
    activeMaterial.value = null;
    showModal.value = true;
};

const openEdit = (material: any) => {
    activeMaterial.value = material;
    showModal.value = true;
};

const handleSaved = () => {
    loadMaterials(currentPage.value);
};

// State Confirm Modal
const isConfirmModalOpen = ref(false);
const isDeleting = ref(false);
const confirmModalConfig = ref({
    title: 'Hapus Material?',
    message: 'Tindakan ini tidak dapat dibatalkan.',
    confirmText: 'Ya, Hapus',
    action: async () => {}
});

const remove = (material: any) => {
    confirmModalConfig.value = {
        title: 'Hapus Material?',
        message: `Material "${material.name}" akan dihapus permanen dari sistem.`,
        confirmText: 'Ya, Hapus',
        action: async () => {
            isDeleting.value = true;
            try {
                const response = await axios.delete(`/api/raw-materials/${material.id}`);
                success('Berhasil', response.data.message);
                loadMaterials(currentPage.value);
                isConfirmModalOpen.value = false;
            } catch (err: any) {
                error('Gagal', err.response?.data?.message || 'Terjadi kesalahan.');
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
    if (selectedIds.value.length === materials.value.length && materials.value.length > 0) {
        selectedIds.value = [];
    } else {
        selectedIds.value = materials.value.map((m: any) => m.id);
    }
};

const bulkDelete = () => {
    confirmModalConfig.value = {
        title: 'Hapus Material Terpilih?',
        message: `Sebanyak ${selectedIds.value.length} material terpilih akan dihapus permanen.`,
        confirmText: 'Ya, Hapus Semua',
        action: async () => {
            isDeleting.value = true;
            try {
                const response = await axios.post('/api/raw-materials/bulk-delete', { 
                    ids: selectedIds.value 
                });
                
                selectedIds.value = [];
                success('Berhasil', response.data.message);
                loadMaterials(currentPage.value);
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
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">Daftar Material</h1>
                <p class="text-xs sm:text-sm text-muted-foreground mt-1">
                    Kelola stok bahan baku dan inventaris outlet secara real-time.
                </p>
            </div>
            
            <div class="flex items-center gap-2.5 sm:shrink-0">
                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Cari material..." 
                        class="h-9 pl-9 rounded-xl text-xs bg-card" 
                    />
                </div>
                <Button size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm bg-foreground text-background hover:opacity-90 transition-all" @click="openCreate">
                    + New Material
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
                    <span class="font-semibold text-foreground">material dipilih untuk dimodifikasi secara massal</span>
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
                Memuat data material...
            </div>

            <div v-else-if="materials.length > 0" class="w-full">
                <div class="overflow-x-auto rounded-2xl border border-border/70 bg-card shadow-xs w-full">
                    <table class="w-full text-sm text-left min-w-[850px]">
                        <thead class="bg-secondary/60 text-muted-foreground text-xs border-b border-border/70">
                            <tr>
                                <th class="px-5 py-3.5 w-10">
                                    <input 
                                        type="checkbox" 
                                        :checked="selectedIds.length === materials.length && materials.length > 0" 
                                        @change="toggleSelectAll" 
                                        class="rounded border-border accent-primary cursor-pointer" 
                                    />
                                </th>
                                <th class="px-5 py-3.5 font-bold">#</th>
                                <th class="px-5 py-3.5 font-bold">Nama Material</th>
                                <th class="px-5 py-3.5 font-bold">Stok</th>
                                <th class="px-5 py-3.5 font-bold">Min. Stok</th>
                                <th class="px-5 py-3.5 font-bold">Avg. Cost</th>
                                <th class="px-5 py-3.5 font-bold">Last Cost</th>
                                <th class="px-5 py-3.5 font-bold">Status</th>
                                <th class="px-5 py-3.5 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        
                        <tbody class="divide-y divide-border/60 text-xs">
                            <tr v-for="(m, index) in materials" :key="m.id" class="hover:bg-secondary/40 transition-colors">
                                <td class="px-5 py-4">
                                   <input type="checkbox" v-model="selectedIds" :value="m.id" class="rounded border-border accent-primary cursor-pointer" />
                                </td>
                                <td class="px-5 py-4 text-muted-foreground">
                                    {{ (meta?.current_page - 1) * (meta?.per_page || 15) + index + 1 }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-foreground">{{ m.name }}</div>
                                </td>
                                <td class="px-5 py-4 text-muted-foreground font-medium whitespace-nowrap">
                                    {{ formatNumber(m.stock_qty) }} {{ m.base_unit }}
                                </td>
                                <td class="px-5 py-4 text-muted-foreground whitespace-nowrap">
                                    {{ formatNumber(m.min_stock) }} {{ m.base_unit }}
                                </td>
                                <td class="px-5 py-4 text-muted-foreground whitespace-nowrap">
                                    {{ formatNumber(m.avg_cost) }}
                                </td>
                                <td class="px-5 py-4 text-muted-foreground whitespace-nowrap">
                                    {{ formatNumber(m.last_cost) }}
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span :class="m.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold' : 'bg-secondary text-muted-foreground font-semibold'" class="px-2.5 py-1 rounded-full text-[10px]">
                                        {{ m.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="outline" size="sm" class="h-8 px-3 rounded-xl text-xs font-semibold" @click="openEdit(m)">Edit</Button>
                                        <Button variant="destructive" size="sm" class="h-8 px-3 rounded-xl text-xs font-semibold" @click="remove(m)">Hapus</Button>
                                        <Button variant="outline" size="sm" class="h-8 px-3 rounded-xl text-xs font-semibold" @click="openHistory(m)">Riwayat</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-border/70 px-4 py-4 sm:px-6 mt-4">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="meta.current_page === 1" 
                            @click="loadMaterials(meta.current_page - 1)"
                        >
                            Previous
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="meta.current_page === meta.last_page" 
                            @click="loadMaterials(meta.current_page + 1)"
                        >
                            Next
                        </Button>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Menampilkan
                                <span class="font-bold text-foreground">{{ meta.from || 0 }}</span>
                                sampai
                                <span class="font-bold text-foreground">{{ meta.to || 0 }}</span>
                                dari
                                <span class="font-bold text-foreground">{{ meta.total || 0 }}</span>
                                hasil
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-xl shadow-xs gap-1.5" aria-label="Pagination">
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    :disabled="meta.current_page === 1" 
                                    @click="loadMaterials(meta.current_page - 1)"
                                    class="h-8 px-3 rounded-xl text-xs"
                                >
                                    &laquo; Prev
                                </Button>

                                <span class="inline-flex items-center px-4 text-xs font-semibold text-foreground border border-border/70 rounded-xl bg-card h-8">
                                    Halaman {{ meta.current_page }} dari {{ meta.last_page }}
                                </span>

                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    :disabled="meta.current_page === meta.last_page" 
                                    @click="loadMaterials(meta.current_page + 1)"
                                    class="h-8 px-3 rounded-xl text-xs"
                                >
                                    Next &raquo;
                                </Button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-24 text-center border border-dashed rounded-3xl bg-card border-border/80 shadow-xs">
                <h3 class="text-sm font-bold text-foreground">Data tidak ditemukan</h3>
                <p class="mt-1 text-xs text-muted-foreground">Belum ada stok bahan baku atau inventaris terdaftar.</p>
            </div>
        </div>
    </div>

    <MaterialHistoryModal 
        :show="showHistoryModal"
        :material="selectedMaterialForHistory"
        @close="showHistoryModal = false"
    />

    <MaterialModal 
        :show="showModal" 
        :material="activeMaterial"
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