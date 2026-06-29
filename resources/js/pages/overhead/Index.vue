<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal';
import { useOverheadCosts } from '@/composables/useOverheadCosts'; // 1. Import Composable
import axios from 'axios';
import OverheadCostModal from './OverheadCostModal.vue';
import OverheadCostDetailModal from './OverheadCostDetailModal.vue';

defineOptions({ layout: AppSidebarLayout });

const { confirm, success, error } = useSwal();
// 2. Gunakan Destructuring State dari Composable
const { overheads, isLoading, fetchOverheads } = useOverheadCosts();

const showModal = ref(false);
const showViewModal = ref(false);
const activeOverhead = ref<any>(null);
const selectedIds = ref<string[]>([]);

const openCreate = () => { activeOverhead.value = null; showModal.value = true; };
const openEdit = (item: any) => { activeOverhead.value = item; showModal.value = true; };
const openView = (item: any) => { activeOverhead.value = item; showViewModal.value = true; };

onMounted(() => fetchOverheads());

const toggleSelectAll = () => {
    selectedIds.value = selectedIds.value.length === overheads.value.length 
        ? [] 
        : overheads.value.map(o => o.id);
};

const toggleStatus = async (item: any) => {
    try {
        const targetStatus = !item.is_active;
        await axios.put(`/api/overhead-costs/${item.id}`, { is_active: targetStatus });
        item.is_active = targetStatus;
        success('Berhasil', `Status biaya ${item.name} berhasil diubah.`);
    } catch (err) {
        error('Gagal', 'Gagal mengubah status.');
    }
};

const bulkDelete = async () => {
    if (await confirm(`Hapus ${selectedIds.value.length} komponen biaya terpilih?`)) {
        try {
            await axios.post('/api/overhead-costs/bulk-delete', { ids: selectedIds.value });
            selectedIds.value = [];
            success('Berhasil', 'Data overhead berhasil dihapus.');
            fetchOverheads(); // Memanggil ulang via composable
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
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Master Overhead Costs</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Kelola data komponen biaya tidak langsung untuk kalkulasi HPP otomatis menu.
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <Button @click="openCreate()">New Overhead</Button>
            </div>
        </div>

        <div v-if="selectedIds.length > 0" class="mt-8 flex items-center justify-between rounded-lg bg-destructive/10 px-4 py-3 border border-destructive/20">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-destructive">
                    {{ selectedIds.length }} Komponen dipilih
                </span>
                <Button variant="destructive" size="sm" @click="bulkDelete">Hapus Terpilih</Button>
            </div>
            <button @click="selectedIds = []" class="text-muted-foreground hover:text-foreground text-sm">
                Batal
            </button>
        </div>

        <div v-if="isLoading" class="mt-8 text-center text-muted-foreground">
            Memuat data...
        </div>

        <div v-else-if="overheads.length > 0" class="table-container mt-8">
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <table class="w-full text-sm text-left">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-6 py-4 w-12">
                                <Input type="checkbox" 
                                    :checked="selectedIds.length === overheads.length && overheads.length > 0" 
                                    @change="toggleSelectAll" />
                            </th>
                            <th class="px-6 py-4">Nama Komponen Biaya</th>
                            <th class="px-6 py-4">Nominal</th>
                            <th class="px-6 py-4">Tipe</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr v-for="item in overheads" :key="item.id" class="group hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4"><input type="checkbox" v-model="selectedIds" :value="item.id" /></td>
                            <td class="px-6 py-4 font-medium">{{ item.name }}</td>
                            <td class="px-6 py-4 font-semibold text-red-600">Rp {{ Number(item.amount).toLocaleString() }}</td>
                            <td class="px-6 py-4 capitalize text-muted-foreground">{{ item.type || 'per_porsi' }}</td>
                            <td class="px-6 py-4">
                                <button @click="toggleStatus(item)" :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'" class="px-2.5 py-1 text-xs font-medium rounded-full inline-flex items-center gap-1.5 transition-colors">
                                    <span :class="item.is_active ? 'bg-green-500' : 'bg-gray-400'" class="w-1.5 h-1.5 rounded-full"></span>
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Button variant="outline" size="sm" @click="openView(item)">Detail</Button>
                                <Button variant="ghost" size="sm" @click="openEdit(item)">Edit</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20 mt-8 text-center border rounded-lg bg-card border-dashed border-border">
            <h3 class="text-lg font-semibold text-foreground">Belum ada overhead cost</h3>
            <p class="mt-1 text-sm text-muted-foreground">Yuk tambahkan komponen master biaya pertama Anda!</p>
        </div>
    </div>
    
    <OverheadCostModal :show="showModal" :overhead="activeOverhead" @close="showModal = false" @saved="fetchOverheads" />
    <OverheadCostDetailModal :show="showViewModal" :overhead="activeOverhead" @close="showViewModal = false" />
</template>