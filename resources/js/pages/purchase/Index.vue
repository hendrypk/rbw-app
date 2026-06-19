<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal';
import { usePurchaseOrders } from '@/composables/usePurchaseOrders'; // Pastikan composable sudah dibuat
import axios from 'axios';
import PurchaseOrderModal from './PurchaseOrderModal.vue';
import PurchaseOrderDetailModal from './PurchaseOrderDetailModal.vue';

defineOptions({ layout: AppSidebarLayout });

const { purchaseOrders, isLoading, fetchPurchaseOrders } = usePurchaseOrders();
const { confirm, success, error } = useSwal();

const showModal = ref(false);
const showViewModal = ref(false); // Tambahkan ini
const activePO = ref(null);
const selectedIds = ref<string[]>([]);

const openCreate = () => { activePO.value = null; showModal.value = true; };
const openEdit = (po: any) => { activePO.value = po; showModal.value = true; };
const openView = (po: any) => { activePO.value = po; showViewModal.value = true; }; // Tambahkan ini

onMounted(() => fetchPurchaseOrders());

const toggleSelectAll = () => {
    selectedIds.value = selectedIds.value.length === purchaseOrders.value.length 
        ? [] 
        : purchaseOrders.value.map(p => p.id);
};

const bulkDelete = async () => {
    if (await confirm(`Hapus ${selectedIds.value.length} PO terpilih?`)) {
        try {
            await axios.post('/api/purchase-orders/bulk-delete', { ids: selectedIds.value });
            selectedIds.value = [];
            success('Berhasil', 'Data PO berhasil dihapus.');
            fetchPurchaseOrders();
        } catch (err: any) {
            error('Gagal', err.response?.data?.message || 'Terjadi kesalahan.');
        }
    }
};

const getStatusColor = (status: string) => {
    return status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
};
</script>

<template>
    <div class="p-6">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Daftar Purchase Order</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Kelola data rekanan pembelian bahan baku.
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <Button @click="openCreate()">New Purchase</Button>
            </div>
        </div>
        <!-- <div v-if="selectedIds.length > 0" class="mt-8 flex items-center justify-between rounded-lg bg-destructive/10 px-4 py-3 border border-destructive/20">
            <span class="text-sm font-medium text-destructive">{{ selectedIds.length }} PO dipilih</span>
            <Button variant="destructive" size="sm" @click="bulkDelete">Hapus Terpilih</Button>
        </div> -->
        <div v-if="selectedIds.length > 0" class="mt-8 flex items-center justify-between rounded-lg bg-destructive/10 px-4 py-3 border border-destructive/20 animate-in fade-in zoom-in duration-200">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-destructive">
                    {{ selectedIds.length }} Purchase dipilih
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

        <div v-else-if="purchaseOrders.length > 0" class="table-container mt-8">
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <table class="w-full text-sm text-left">
                        <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-6 py-4 w-12">
                                <Input type="checkbox" 
                                    :checked="selectedIds.length === purchaseOrders.length && purchaseOrders.length > 0" 
                                    @change="toggleSelectAll" />
                            </th>
                            <th class="px-6 py-4">No. PO</th>
                            <th class="px-6 py-4">Supplier</th>
                            <th class="px-6 py-4">Total</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr v-for="po in purchaseOrders" :key="po.id" class="group hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4"><input type="checkbox" v-model="selectedIds" :value="po.id" /></td>
                            <td class="px-6 py-4 font-medium">{{ po.po_number }}</td>
                            <td class="px-6 py-4">{{ po.supplier?.name || '-' }}</td>
                            <td class="px-6 py-4">Rp {{ Number(po.total_amount).toLocaleString() }}</td>
                            <td class="px-6 py-4"><span :class="['px-2 py-1 rounded-full text-xs', getStatusColor(po.status)]">{{ po.status }}</span></td>
                            <td class="px-6 py-4 text-right">
                                <Button variant="outline" size="sm" @click="openView(po)">Detail</Button>
                                <Button variant="ghost" size="sm" @click="openEdit(po)">Edit</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-20 mt-8 text-center border rounded-lg bg-card border-dashed border-border">
            <h3 class="text-lg font-semibold text-foreground">Belum ada pembelian</h3>
            <p class="mt-1 text-sm text-muted-foreground">Yuk tambahkan pembelian pertama Anda!</p>
        </div>
    </div>
    
    <PurchaseOrderModal :show="showModal" :po="activePO" @close="showModal = false" @saved="fetchPurchaseOrders" />
    <PurchaseOrderDetailModal 
        :show="showViewModal" 
        :po="activePO" 
        @close="showViewModal = false" 
    />
</template>