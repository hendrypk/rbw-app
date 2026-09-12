<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Modal from '@/components/ui/Modal.vue';
import { History, ArrowDownRight, ArrowUpRight, TrendingUp, TrendingDown, Layers } from '@lucide/vue';
import DatePresetFilter from '@/components/DatePresetFilter.vue';

const props = defineProps<{ 
    show: boolean, 
    material?: any | null 
}>();

const emit = defineEmits(['close']);

const historyList = ref<any[]>([]);
const isLoading = ref(false);
const dateRange = ref({ start: '', end: '' });

const formatNumber = (value: number | string) => {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);
};

const formatDate = (dateString: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const handleDateChange = (range: { start: string; end: string }) => {
    dateRange.value = range;
    fetchLedger();
};

const fetchLedger = async () => {
    if (!props.material?.id) return;
    isLoading.value = true;
    try {
        const res = await axios.get(`/api/raw-materials/${props.material.id}/ledger`, {
            params: {
                start_date: dateRange.value.start,
                end_date: dateRange.value.end
            }
        });
        historyList.value = res.data.data || res.data;
    } catch (e) {
        console.error('Gagal memuat riwayat ledger stok', e);
        historyList.value = [];
    } finally {
        isLoading.value = false;
    }
};

const totalIn = computed(() => {
    return historyList.value
        .filter(item => item.movement_type === 'in')
        .reduce((sum, item) => sum + Number(item.qty || 0), 0);
});

const totalOut = computed(() => {
    return historyList.value
        .filter(item => item.movement_type === 'out')
        .reduce((sum, item) => sum + Number(item.qty || 0), 0);
});

const navigateToDetail = (item: any) => {
    if (!item.reference_type || !item.reference_id) return;

    if (item.reference_type.includes('PurchaseOrderItem')) {
        const poId = item.reference?.purchase_order_id;
        if (poId) {
            emit('close');
            router.visit(`/purchase/${poId}`);
        }
    }
};

// Tambahan State untuk Modal Penyesuaian Persediaan
const showAdjustmentModal = ref(false);
const adjustmentProcessing = ref(false);
const adjustmentForm = ref({
    adjustment_type: 'in', // 'in' (tambah stok) atau 'out' (kurangi stok)
    qty: 0,
    notes: ''
});

const openAdjustmentModal = () => {
    adjustmentForm.value = {
        adjustment_type: 'in',
        qty: 0,
        notes: ''
    };
    showAdjustmentModal.value = true;
};

const submitAdjustment = async () => {
    if (!props.material?.id) return;
    adjustmentProcessing.value = true;
    try {
        await axios.post(`/api/raw-materials/${props.material.id}/adjust-stock`, adjustmentForm.value);
        showAdjustmentModal.value = false;
        fetchLedger(); // Refresh riwayat ledger
        // Opsional: emit event jika butuh update data induk di parent
    } catch (e: any) {
        console.error('Gagal melakukan penyesuaian stok', e);
        alert(e.response?.data?.message || 'Terjadi kesalahan sistem.');
    } finally {
        adjustmentProcessing.value = false;
    }
};

watch(() => props.show, (newVal) => {
    if (newVal && props.material?.id) {
        if (dateRange.value.start && dateRange.value.end) {
            fetchLedger();
        }
    }
});
</script>

<template>
    <Modal :show="show" :title="`Riwayat Stok: ${material?.name || ''}`" maxWidth="max-w-5xl" @close="$emit('close')">
        <template #icon>
            <History class="w-5 h-5 text-primary" />
        </template>

        <div class="space-y-5 text-xs">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-border/60">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto flex-1">
                    <div class="bg-secondary/60 border border-border/70 rounded-2xl p-4 flex flex-col shadow-xs">
                        <span class="text-xs text-muted-foreground font-bold flex items-center gap-1.5">
                            <TrendingUp class="w-4 h-4 text-emerald-500" /> Total Masuk
                        </span>
                        <span class="font-extrabold text-emerald-600 dark:text-emerald-400 text-lg mt-1">
                            +{{ formatNumber(totalIn) }}
                        </span>
                    </div>
                    <div class="bg-secondary/60 border border-border/70 rounded-2xl p-4 flex flex-col shadow-xs">
                        <span class="text-xs text-muted-foreground font-bold flex items-center gap-1.5">
                            <TrendingDown class="w-4 h-4 text-rose-500" /> Total Keluar
                        </span>
                        <span class="font-extrabold text-rose-600 dark:text-rose-400 text-lg mt-1">
                            -{{ formatNumber(totalOut) }}
                        </span>
                    </div>
                    <div class="bg-secondary/60 border border-border/70 rounded-2xl p-4 flex flex-col shadow-xs">
                        <span class="text-xs text-muted-foreground font-bold flex items-center gap-1.5">
                            <Layers class="w-4 h-4 text-primary" /> Stok Saat Ini
                        </span>
                        <span class="font-extrabold text-foreground text-lg mt-1">
                            {{ formatNumber(material?.stock_qty) }} <span class="text-xs font-semibold text-muted-foreground">{{ material?.base_unit }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 justify-end shrink-0">
                    <Button type="button" size="sm" @click="openAdjustmentModal" class="h-10 px-4 rounded-xl text-xs font-bold bg-primary text-primary-foreground shadow-xs cursor-pointer">
                        + Penyesuaian Stok
                    </Button>
                    <DatePresetFilter @change="handleDateChange" class="px-4 py-2.5 rounded-xl border border-border bg-card text-foreground font-semibold text-xs outline-none focus:ring-1 focus:ring-primary cursor-pointer shadow-xs" />
                </div>

                <Modal :show="showAdjustmentModal" :title="`Penyesuaian Stok: ${material?.name || ''}`" maxWidth="max-w-md" @close="showAdjustmentModal = false">
                    <div class="space-y-4 text-xs">
                        <div class="space-y-1.5">
                            <label class="font-semibold text-muted-foreground">Jenis Penyesuaian</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button 
                                    type="button" 
                                    @click="adjustmentForm.adjustment_type = 'in'"
                                    :class="['py-2.5 px-2 rounded-xl font-bold border transition-all cursor-pointer text-center', adjustmentForm.adjustment_type === 'in' ? 'bg-emerald-500/10 border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-border bg-card text-muted-foreground']"
                                >
                                    Stok Masuk
                                </button>
                                <button 
                                    type="button" 
                                    @click="adjustmentForm.adjustment_type = 'out'"
                                    :class="['py-2.5 px-2 rounded-xl font-bold border transition-all cursor-pointer text-center', adjustmentForm.adjustment_type === 'out' ? 'bg-rose-500/10 border-rose-500 text-rose-600 dark:text-rose-400' : 'border-border bg-card text-muted-foreground']"
                                >
                                    Stok Keluar
                                </button>
                                <button 
                                    type="button" 
                                    @click="adjustmentForm.adjustment_type = 'actual'; adjustmentForm.qty = Number(material?.stock_qty || 0);"
                                    :class="['py-2.5 px-2 rounded-xl font-bold border transition-all cursor-pointer text-center', adjustmentForm.adjustment_type === 'actual' ? 'bg-primary/10 border-primary text-primary' : 'border-border bg-card text-muted-foreground']"
                                >
                                    Aktual Stok
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="font-semibold text-muted-foreground">
                                {{ adjustmentForm.adjustment_type === 'actual' ? 'Jumlah Qty Aktual di Gudang' : 'Jumlah Qty' }}
                            </label>
                            <div class="relative flex items-center">
                                <input 
                                    type="number" 
                                    v-model.number="adjustmentForm.qty" 
                                    :min="adjustmentForm.adjustment_type === 'actual' ? '0' : '0.0001'" 
                                    step="any"
                                    placeholder="0"
                                    class="w-full h-11 rounded-xl border border-border bg-card pl-4 pr-16 font-mono font-bold text-foreground focus:outline-none focus:ring-1 focus:ring-primary" 
                                />
                                <span class="absolute right-4 text-xs font-bold text-muted-foreground uppercase pointer-events-none">
                                    {{ material?.base_unit }}
                                </span>
                            </div>
                            <p v-if="adjustmentForm.adjustment_type === 'actual'" class="text-[11px] text-muted-foreground italic">
                                Sistem saat ini: <span class="font-mono font-bold text-foreground">{{ formatNumber(material?.stock_qty) }} {{ material?.base_unit }}</span>. Selisih akan otomatis dihitung.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="font-semibold text-muted-foreground">Catatan / Alasan Penyesuaian</label>
                            <input 
                                type="text" 
                                v-model="adjustmentForm.notes" 
                                placeholder="Contoh: Stok opname / Barang rusak" 
                                class="w-full h-11 rounded-xl border border-border bg-card px-4 text-foreground focus:outline-none focus:ring-1 focus:ring-primary" 
                            />
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-border/60">
                            <button type="button" @click="showAdjustmentModal = false" class="px-4 py-2.5 rounded-xl border border-border font-semibold text-muted-foreground hover:bg-secondary cursor-pointer">
                                Batal
                            </button>
                            <button type="button" :disabled="adjustmentProcessing || !adjustmentForm.qty" @click="submitAdjustment" class="px-5 py-2.5 rounded-xl font-bold bg-primary text-primary-foreground shadow-xs cursor-pointer disabled:opacity-50">
                                {{ adjustmentProcessing ? 'Menyimpan...' : 'Simpan Penyesuaian' }}
                            </button>
                        </div>
                    </div>
                </Modal>
            </div>

            <div v-if="isLoading" class="py-16 text-center text-muted-foreground font-medium text-sm">
                Memuat riwayat transaksi...
            </div>

            <div v-else-if="historyList.length > 0" class="overflow-x-auto rounded-2xl border border-border/70 bg-card shadow-xs">
                <table class="w-full text-left min-w-[750px]">
                    <thead class="bg-secondary/60 text-muted-foreground border-b border-border/70 text-xs">
                        <tr>
                            <th class="px-5 py-4 font-bold">Waktu</th>
                            <th class="px-5 py-4 font-bold">Tipe</th>
                            <th class="px-5 py-4 font-bold">Keterangan</th>
                            <th class="px-5 py-4 font-bold text-right">Qty</th>
                            <th class="px-5 py-4 font-bold text-right">Stok Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60 text-xs">
                        <tr v-for="item in historyList" :key="item.id" class="hover:bg-secondary/40 transition-colors">
                            <td class="px-5 py-4 text-muted-foreground whitespace-nowrap font-medium">
                                {{ formatDate(item.created_at) }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span :class="[
                                    'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-bold',
                                    item.movement_type === 'in' 
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' 
                                        : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                ]">
                                    <ArrowDownRight v-if="item.movement_type === 'in'" class="w-3.5 h-3.5" />
                                    <ArrowUpRight v-else class="w-3.5 h-3.5" />
                                    {{ item.movement_type === 'in' ? 'MASUK' : 'KELUAR' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 font-semibold text-foreground">
                                <div 
                                    @click="navigateToDetail(item)" 
                                    :class="{ 'cursor-pointer text-primary hover:underline inline-flex items-center gap-1 group': item.reference_type?.includes('PurchaseOrderItem') }"
                                >
                                    {{ item.notes || '-' }}
                                    <ArrowUpRight v-if="item.reference_type?.includes('PurchaseOrderItem')" class="w-3 h-3 opacity-70 group-hover:opacity-100" />
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right font-bold whitespace-nowrap text-sm" :class="item.movement_type === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                {{ item.movement_type === 'in' ? '+' : '-' }}{{ formatNumber(item.qty) }} <span class="text-xs font-normal text-muted-foreground">{{ material?.base_unit }}</span>
                            </td>
                            <td class="px-5 py-4 text-right font-bold text-foreground whitespace-nowrap text-sm">
                                {{ formatNumber(item.stock_after) }} <span class="text-xs font-normal text-muted-foreground">{{ material?.base_unit }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-20 text-center border border-dashed rounded-3xl bg-card border-border/80">
                <h3 class="text-sm font-bold text-foreground">Belum ada riwayat transaksi</h3>
                <p class="mt-1 text-xs text-muted-foreground">Pergerakan stok untuk material ini belum tercatat pada rentang waktu tersebut.</p>
            </div>
        </div>
    </Modal>
</template>