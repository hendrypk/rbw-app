<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Plus, Search, Edit, Trash2, Store, CheckCircle, Clock, Eye } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PurchaseOrderModal from '@/pages/purchase/PurchaseOrderModal.vue';
import { formatDate } from '@/composables/useFormateDate';
import DatePresetFilter from '../DatePresetFilter.vue';
import PurchaseOrderDetailModal from '@/pages/purchase/PurchaseOrderDetailModal.vue';

const purchaseOrders = ref<any[]>([]);
const isLoading = ref(false);
const searchQuery = ref('');
const showModal = ref(false);
const selectedPo = ref(null);
const dateRange = ref({ start: null, end: null });
const showViewModal = ref(false); // Tambahkan ini

const fetchPurchaseOrders = async () => {
    isLoading.value = true;
    try {
        const activeOutletId = localStorage.getItem('active_outlet_id');
        const config = {
            headers: activeOutletId && activeOutletId !== 'all' ? { 'X-Outlet-ID': activeOutletId } : {},
            params: {
                search: searchQuery.value,
                start_date: dateRange.value.start,
                end_date: dateRange.value.end,
            }
        };

        const response = await axios.get('/api/purchase-orders', config);
        purchaseOrders.value = response.data.data || response.data;
    } catch (error) {
        console.error('Gagal memuat data purchase order:', error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchPurchaseOrders();
    window.addEventListener('outlet-changed', () => {
        fetchPurchaseOrders();
    });
});

const handleDateChange = (range: { start: any; end: any }) => {
    dateRange.value = range;
    fetchPurchaseOrders();
};

const handleCreate = () => {
    selectedPo.value = null;
    showModal.value = true;
};

const handleEdit = (po: any) => {
    selectedPo.value = po;
    showModal.value = true;
};

const handleShow = (po: any) => { 
    selectedPo.value = po;
    showViewModal.value = true; 
}; 


const handleDelete = async (id: string) => {
    if (!confirm('Apakah Anda yakin ingin menghapus data pembelian ini?')) return;
    
    try {
        const activeOutletId = localStorage.getItem('active_outlet_id');
        const headers = activeOutletId && activeOutletId !== 'all' ? { 'X-Outlet-ID': activeOutletId } : {};

        await axios.delete(`/api/purchase-orders/${id}`, { headers });
        toast.success("Purchase order berhasil dihapus.");
        fetchPurchaseOrders();
    } catch (error: any) {
        toast.error(error.response?.data?.message || "Gagal menghapus purchase order.");
    }
};

// Hitung total keseluruhan hutang/sisa belum dibayar dari list PO
const totalUnpaidAmount = computed(() => {
    return purchaseOrders.value.reduce((sum, po) => {
        const total = Number(po.total_amount || 0);
        const paid = Number(po.total_payment || 0);
        return sum + Math.max(0, total - paid);
    }, 0);
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-zinc-50 tracking-tight">Purchase Orders</h1>
                <p class="text-xs text-slate-400 mt-0.5">Kelola pengadaan dan pembelian stok bahan baku outlet.</p>
            </div>
            <Button @click="handleCreate" class="gap-2 font-bold cursor-pointer">
                <Plus class="size-4" /> Tambah Pembelian
            </Button>
        </div>

        <div class="space-y-4">
        <!-- Kartu Ringkasan Sisa Tagihan/Belum Dibayar -->
            <!-- <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Belum Dibayar (Hutang)</div>
                        <div class="text-lg font-black font-mono text-rose-600 mt-0.5">
                            Rp {{ totalUnpaidAmount.toLocaleString('id-ID') }}
                        </div>
                    </div>
                    <div class="size-10 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center font-black">
                        Rp
                    </div>
                </div>
            </div> -->

            <!-- Tabel Riwayat PO -->
            <div class="bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 rounded-3xl shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-100 dark:border-zinc-800/80 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full sm:max-w-sm">
                        <Search class="absolute left-3 top-3 size-4 text-slate-400" />
                        <Input v-model="searchQuery" placeholder="Cari supplier..." class="pl-9 text-xs rounded-xl" />
                    </div>
                    
                    <div class="flex items-center shrink-0 w-full sm:w-auto justify-end">
                        <DatePresetFilter @change="handleDateChange" class="px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 text-slate-700 dark:text-zinc-300 font-medium text-xs outline-none focus:ring-1 focus:ring-primary cursor-pointer" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-zinc-900/40 text-slate-400 dark:text-zinc-500 uppercase font-extrabold tracking-wider border-b border-slate-100 dark:border-zinc-800/80">
                            <tr>
                                <th class="p-4">No</th>
                                <th class="p-4">Tanggal</th>
                                <th class="p-4">Supplier</th>
                                <th class="p-4">Total Bruto & Sisa Utang</th>
                                <th class="p-4">Status Bayar</th>
                                <th class="p-4">Status PO</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60 font-medium text-slate-700 dark:text-zinc-300">
                            <tr v-if="isLoading">
                                <td colspan="6" class="text-center py-12 text-slate-400 dark:text-zinc-500">Memuat data pembelian...</td>
                            </tr>
                            <tr v-else-if="purchaseOrders.length === 0">
                                <td colspan="6" class="text-center py-12 text-slate-400 dark:text-zinc-500">Belum ada riwayat pembelian tercatat.</td>
                            </tr>
                            <tr v-for="po in purchaseOrders" :key="po.id" class="hover:bg-slate-50/50 dark:hover:bg-zinc-900/30 transition-colors">
                                <td class="p-4 font-bold text-slate-900 dark:text-zinc-200 font-mono">
                                    {{ po.po_number }}
                                </td>
                                <td class="p-4 font-bold text-slate-900 dark:text-zinc-200 font-mono">
                                    {{ formatDate(po.order_date) }}
                                </td>
                                <td class="p-4 font-bold text-slate-900 dark:text-zinc-200">
                                    {{ po.supplier?.name || '-' }}
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-zinc-100 font-mono">
                                        Rp {{ Number(po.total_amount || 0).toLocaleString('id-ID') }}
                                    </div>
                                    <div v-if="Number(po.total_amount || 0) - Number(po.total_payment || 0) > 0" class="text-[10px] text-slate-500 dark:text-zinc-400 font-mono mt-0.5">
                                        Belum bayar: Rp {{ (Number(po.total_amount || 0) - Number(po.total_payment || 0)).toLocaleString('id-ID') }}
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 border border-slate-200/60 dark:border-zinc-700/60">
                                        {{ po.payment_status }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 border border-slate-200/60 dark:border-zinc-700/60">
                                        {{ po.status }}
                                    </span>
                                </td>
                                <td class="p-4 text-right space-x-1">
                                    <Button variant="ghost" size="icon" @click="handleShow(po)" class="h-8 w-8 text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">
                                        <Eye class="size-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" @click="handleEdit(po)" class="h-8 w-8 text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">
                                        <Edit class="size-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" @click="handleDelete(po.id)" class="h-8 w-8 text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <PurchaseOrderDetailModal 
            :show="showViewModal" 
            :po="selectedPo" 
            @close="showViewModal = false" 
        />
        <PurchaseOrderModal 
            :show="showModal" 
            :po="selectedPo" 
            @close="showModal = false" 
            @saved="fetchPurchaseOrders" 
        />
    </div>
</template>