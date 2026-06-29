<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import MaterialModal from './MaterialModal.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal';
import axios from 'axios';
import { useMaterials } from '@/composables/useMaterials.js';

defineOptions({ layout: AppSidebarLayout });

const showModal = ref(false);
const activeMaterial = ref(null); // null = create, object = edit
const { confirm, success, error } = useSwal();
const {
    materials,
    meta,
    isLoading,
    fetchMaterials,
} = useMaterials();

// Inisialisasi halaman aktif saat ini
const currentPage = ref(1);

// Fungsi load data dengan parameter halaman
const loadMaterials = (page = 1) => {
    currentPage.value = page;
    fetchMaterials({ page: page });
};

onMounted(() => {
    loadMaterials();
});

// Fungsi untuk memformat angka dengan 2 desimal
const formatNumber = (value: number | string) => {
    const num = Number(value); 
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
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

const remove = async (id: string) => {
    if (await confirm('Data ini akan dihapus permanen!')) {
        try {
            const response = await axios.delete(`/api/raw-materials/${id}`);
            success('Berhasil', response.data.message);
            loadMaterials(currentPage.value);
        } catch (err: any) {
            error('Gagal', err.response?.data?.message || 'Terjadi kesalahan.');
        }
    }
};

// UUID menggunakan string
const selectedIds = ref<string[]>([]);

const toggleSelectAll = () => {
    if (selectedIds.value.length === materials.value.length && materials.value.length > 0) {
        selectedIds.value = [];
    } else {
        selectedIds.value = materials.value.map(m => m.id);
    }
};

const bulkDelete = async () => {
    if (await confirm(`Yakin ingin menghapus ${selectedIds.value.length} material terpilih?`)) {
        try {
            const response = await axios.post('/api/raw-materials/bulk-delete', { 
                ids: selectedIds.value 
            });
            
            selectedIds.value = [];
            success('Berhasil', response.data.message);
            loadMaterials(currentPage.value);
        } catch (err: any) {
            error('Gagal', err.response?.data?.message || 'Terjadi kesalahan.');
        }
    }
};
</script>

<template>
    <div class="p-6">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Daftar Material</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Kelola stok bahan baku dan inventaris.
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <Button @click="openCreate()">New Material</Button>
            </div>
        </div>

        <div v-if="selectedIds.length > 0" class="mt-8 flex items-center justify-between rounded-lg bg-destructive/10 px-4 py-3 border border-destructive/20 animate-in fade-in zoom-in duration-200">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-destructive">
                    {{ selectedIds.length }} material dipilih
                </span>
                <Button variant="destructive" size="sm" @click="bulkDelete">Hapus Terpilih</Button>
            </div>
            <button @click="selectedIds = []" class="text-muted-foreground hover:text-foreground">
                Batal
            </button>
        </div>

        <div v-if="isLoading" class="mt-8 text-center text-muted-foreground">
            Memuat data...
        </div>

        <div v-else-if="materials.length > 0" class="table-container mt-8">
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <table class="w-full text-sm text-left">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-6 py-4 w-12">
                                <Input type="checkbox" 
                                    :checked="selectedIds.length === materials.length && materials.length > 0" 
                                    @change="toggleSelectAll" />
                            </th>
                            <th class="px-6 py-4 font-medium">#</th>
                            <th class="px-6 py-4 font-medium">Nama Material</th>
                            <th class="px-6 py-4 font-medium">Stok</th>
                            <th class="px-6 py-4 font-medium">Min. Stok</th>
                            <th class="px-6 py-4 font-medium">Avg. Cost</th> <th class="px-6 py-4 font-medium">Last Cost</th> <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-border/50">
                        <tr v-for="(m, index) in materials" :key="m.id" class="group hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                                <input type="checkbox" v-model="selectedIds" :value="m.id" class="rounded border-border" />
                            </td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ (meta?.current_page - 1) * (meta?.per_page || 15) + index + 1 }}
                            </td>
                            <td class="px-6 py-4 font-medium text-foreground">{{ m.name }}</td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ formatNumber(m.stock_qty) }} {{ m.base_unit }}
                            </td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ formatNumber(m.min_stock) }} {{ m.base_unit }}
                            </td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ formatNumber(m.avg_cost) }}
                            </td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ formatNumber(m.last_cost) }}
                            </td>
                            <td class="px-6 py-4">
                                <span :class="[
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    m.is_active 
                                        ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' 
                                        : 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                                ]">
                                    {{ m.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button variant="ghost" size="sm" @click="openEdit(m)" class="text-xs font-medium text-primary hover:underline">Edit</Button>
                                    <span class="text-border">|</span>
                                    <button @click="remove(m.id)" class="text-xs font-medium text-destructive hover:underline">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-border px-4 py-4 sm:px-6 mt-4">
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
                        <p class="text-sm text-muted-foreground">
                            Menampilkan
                            <span class="font-medium">{{ meta.from || 0 }}</span>
                            sampai
                            <span class="font-medium">{{ meta.to || 0 }}</span>
                            dari
                            <span class="font-medium">{{ meta.total || 0 }}</span>
                            hasil
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm gap-1" aria-label="Pagination">
                            <Button 
                                variant="outline" 
                                size="sm"
                                :disabled="meta.current_page === 1" 
                                @click="loadMaterials(meta.current_page - 1)"
                                class="h-9 px-3"
                            >
                                &laquo; Prev
                            </Button>

                            <span class="inline-flex items-center px-4 text-sm font-semibold text-foreground border rounded-md bg-background h-9">
                                Halaman {{ meta.current_page }} dari {{ meta.last_page }}
                            </span>

                            <Button 
                                variant="outline" 
                                size="sm"
                                :disabled="meta.current_page === meta.last_page" 
                                @click="loadMaterials(meta.current_page + 1)"
                                class="h-9 px-3"
                            >
                                Next &raquo;
                            </Button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20 mt-8 text-center border rounded-lg bg-card border-dashed border-border">
            <h3 class="text-lg font-semibold text-foreground">Belum ada material</h3>
            <p class="mt-1 text-sm text-muted-foreground">Tambahkan stok bahan baku pertama Anda.</p>
        </div>
    </div>

    <MaterialModal 
        :show="showModal" 
        :material="activeMaterial"
        @close="showModal = false" 
        @saved="handleSaved" 
    />
</template>