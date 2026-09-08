<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Wallet, Lock, DollarSign, Receipt, ShoppingCart, TrendingUp, Banknote, QrCode, AlertTriangle, CheckCircle2, ArrowUpRight, ArrowDownRight } from '@lucide/vue';
import { toast } from 'vue-sonner';
import axios from 'axios';

const props = defineProps<{
    isOpen: boolean;
    mode: 'open' | 'close';
    activeShift?: any;
    forced?: boolean;
}>();

const emit = defineEmits(['close', 'success']);

const cashInput = ref<number>(0);
const notesInput = ref('');
const isSubmitting = ref(false);
const isLoadingSummary = ref(false);

// State ringkasan shift untuk preview di modal tutup shift
const shiftSummary = ref({
    starting_cash: 0,
    omzet_cash: 0,
    omzet_qris: 0,
    total_omzet: 0,
    total_nota: 0,
    total_items: 0,
    net_profit: 0,
    expected_cash: 0
});

// Hitung selisih kas secara reaktif (Aktual - Seharusnya)
const cashDifference = computed(() => {
    if (props.mode !== 'close') return 0;
    const actual = Number(cashInput.value || 0);
    const expected = Number(shiftSummary.value.expected_cash || 0);
    return actual - expected;
});

// Reset input & ambil ringkasan data saat modal dibuka
watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        cashInput.value = 0;
        notesInput.value = '';
        
        if (props.mode === 'close' && props.activeShift) {
            await fetchShiftSummary();
        }
    }
});

// Ambil ringkasan performa shift dari endpoint backend
const fetchShiftSummary = async () => {
    isLoadingSummary.value = true;
    try {
        const activeOutletId = localStorage.getItem('active_outlet_id');
        const response = await axios.get('/api/pos/dashboard/summary', {
            headers: { 
                'X-Outlet-ID': activeOutletId,
                'X-Shift-ID': props.activeShift.id 
            }
        });

        if (response.data.success) {
            const data = response.data.data;
            const starting = Number(props.activeShift.starting_cash || 0);
            
            shiftSummary.value = {
                starting_cash: starting,
                omzet_cash: data.omzet_cash || 0,
                omzet_qris: data.omzet_qris || 0,
                total_omzet: data.total_omzet || 0,
                total_nota: data.total_nota || 0,
                total_items: data.total_items || 0,
                net_profit: data.net_profit || 0,
                expected_cash: starting + Number(data.omzet_cash || 0)
            };

            // Otomatis masukkan expected cash ke input aktual sebagai default bantuan kasir
            cashInput.value = shiftSummary.value.expected_cash;
        }
    } catch (error) {
        console.error("Gagal mengambil ringkasan shift", error);
        const starting = Number(props.activeShift?.starting_cash || 0);
        shiftSummary.value.starting_cash = starting;
        shiftSummary.value.expected_cash = starting;
        cashInput.value = starting;
    } finally {
        isLoadingSummary.value = false;
    }
};

const formatRupiah = (val: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};

const handleSubmit = async () => {
    if (cashInput.value < 0) {
        toast.error("Jumlah nominal tidak boleh kurang dari 0");
        return;
    }

    isSubmitting.value = true;
    const activeOutletId = localStorage.getItem('active_outlet_id');

    try {
        if (props.mode === 'open') {
            const response = await axios.post('/api/pos/shifts/open', {
                starting_cash: cashInput.value,
                notes: notesInput.value
            }, {
                headers: { 'X-Outlet-ID': activeOutletId }
            });

            if (response.data.success) {
                toast.success("Shift kasir berhasil dibuka!");
                emit('success', response.data.data);
                emit('close');
            }
        } else {
            if (!props.activeShift) return;

            const response = await axios.post(`/api/pos/shifts/${props.activeShift.id}/close`, {
                actual_cash: cashInput.value,
                notes: notesInput.value
            }, {
                headers: { 'X-Outlet-ID': activeOutletId }
            });

            if (response.data.success) {
                toast.success("Shift kasir berhasil ditutup.");
                emit('success', response.data.data);
                emit('close');
            }
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Gagal memproses shift kasir');
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl max-w-lg w-full p-6 border border-slate-200 dark:border-zinc-800 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200 max-h-[90vh] overflow-y-auto">
            
            <!-- Header Modal -->
            <div class="flex items-center gap-3">
                <div :class="['p-3 rounded-xl', mode === 'open' ? 'bg-primary/10 text-primary' : 'bg-amber-500/10 text-amber-500']">
                    <Wallet class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="font-bold text-lg text-slate-900 dark:text-zinc-50">
                        {{ mode === 'open' ? 'Buka Shift Kasir Baru' : 'Tutup Shift Kasir & Rekap' }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-zinc-400">
                        {{ mode === 'open' ? 'Masukkan modal awal uang tunai di laci kasir.' : 'Tinjau ringkasan performa dan hitung uang fisik di laci.' }}
                    </p>
                </div>
            </div>

            <!-- Ringkasan Performa Shift (Hanya tampil saat mode close) -->
            <div v-if="mode === 'close'" class="space-y-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Ringkasan Performa Shift Ini</div>
                
                <div v-if="isLoadingSummary" class="text-center py-6 text-xs text-slate-400">
                    Memuat kalkulasi ringkasan shift...
                </div>

                <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 text-xs">
                    <!-- Total Omzet -->
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold flex items-center gap-1">
                            <DollarSign class="w-3 h-3 text-primary" /> Total Omzet
                        </span>
                        <div class="font-black text-slate-900 dark:text-white text-sm">{{ formatRupiah(shiftSummary.total_omzet) }}</div>
                    </div>

                    <!-- Total Nota -->
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold flex items-center gap-1">
                            <Receipt class="w-3 h-3 text-amber-500" /> Total Nota
                        </span>
                        <div class="font-black text-slate-900 dark:text-white text-sm">{{ shiftSummary.total_nota }} <span class="text-[10px] font-normal text-slate-400">Nota</span></div>
                    </div>

                    <!-- Total Items -->
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold flex items-center gap-1">
                            <ShoppingCart class="w-3 h-3 text-emerald-500" /> Item Terjual
                        </span>
                        <div class="font-black text-slate-900 dark:text-white text-sm">{{ shiftSummary.total_items }} <span class="text-[10px] font-normal text-slate-400">Pcs</span></div>
                    </div>

                    <!-- Omzet Cash -->
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold flex items-center gap-1">
                            <Banknote class="w-3 h-3 text-emerald-600" /> Tunai (Cash)
                        </span>
                        <div class="font-bold text-slate-900 dark:text-white">{{ formatRupiah(shiftSummary.omzet_cash) }}</div>
                    </div>

                    <!-- Omzet QRIS -->
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold flex items-center gap-1">
                            <QrCode class="w-3 h-3 text-sky-500" /> QRIS
                        </span>
                        <div class="font-bold text-slate-900 dark:text-white">{{ formatRupiah(shiftSummary.omzet_qris) }}</div>
                    </div>

                    <!-- Estimasi Laba Bersih -->
                    <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 space-y-1">
                        <span class="text-emerald-600 dark:text-emerald-400 text-[10px] uppercase font-bold flex items-center gap-1">
                            <TrendingUp class="w-3 h-3" /> Laba Bersih
                        </span>
                        <div class="font-black text-emerald-700 dark:text-emerald-300 text-sm">{{ formatRupiah(shiftSummary.net_profit) }}</div>
                    </div>
                </div>

                <!-- Info Kalkulasi Kas Seharusnya & Status Selisih -->
                <div class="p-3.5 bg-slate-100 dark:bg-zinc-800 rounded-xl text-xs space-y-2 border border-slate-200 dark:border-zinc-700">
                    <div class="flex justify-between text-slate-500">
                        <span>Modal Awal (Starting Cash):</span>
                        <span class="font-bold text-slate-700 dark:text-zinc-300">{{ formatRupiah(shiftSummary.starting_cash) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>+ Total Omzet Tunai (Cash):</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">+ {{ formatRupiah(shiftSummary.omzet_cash) }}</span>
                    </div>
                    <div class="pt-2 border-t border-slate-200 dark:border-zinc-700 flex justify-between items-center font-bold">
                        <span class="text-slate-700 dark:text-zinc-200 text-[11px] uppercase">Uang Kas Seharusnya di Laci:</span>
                        <span class="text-primary text-sm font-black">{{ formatRupiah(shiftSummary.expected_cash) }}</span>
                    </div>

                    <!-- Indikator Surplus / Minus -->
                    <div v-if="cashInput > 0" :class="[
                        'p-2.5 rounded-lg flex items-center justify-between font-bold text-xs mt-1 transition-all',
                        cashDifference > 0 ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20' :
                        cashDifference < 0 ? 'bg-red-500/10 text-red-700 dark:text-red-300 border border-red-500/20' :
                        'bg-slate-200/50 dark:bg-zinc-700/50 text-slate-700 dark:text-zinc-300'
                    ]">
                        <span class="flex items-center gap-1.5 uppercase text-[10px] tracking-wider">
                            <CheckCircle2 v-if="cashDifference === 0" class="w-4 h-4 text-emerald-600" />
                            <ArrowUpRight v-else-if="cashDifference > 0" class="w-4 h-4 text-emerald-600" />
                            <ArrowDownRight v-else class="w-4 h-4 text-red-600" />
                            Status Kas Aktual:
                        </span>
                        <span class="text-xs font-black">
                            {{ cashDifference === 0 ? 'PAS (Normal)' : cashDifference > 0 ? `SURPLUS +${formatRupiah(cashDifference)}` : `MINUS ${formatRupiah(cashDifference)}` }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Form Input -->
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400 mb-1.5">
                        {{ mode === 'open' ? 'Modal Kas Awal (Rp)' : 'Uang Tunai Aktual / Fisik di Laci (Rp)' }}
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-sm font-bold text-slate-400">Rp</span>
                        <input 
                            type="number" 
                            v-model.number="cashInput" 
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-slate-900 dark:text-zinc-100 font-bold focus:ring-2 focus:ring-primary outline-none transition-all"
                            placeholder="0"
                            autofocus
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400 mb-1.5">Catatan / Keterangan</label>
                    <textarea 
                        v-model="notesInput" 
                        rows="2"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-slate-900 dark:text-zinc-100 text-sm focus:ring-2 focus:ring-primary outline-none transition-all resize-none"
                        :placeholder="mode === 'open' ? 'Misal: Shift Pagi, Uang pas.' : 'Keterangan penutupan shift (misal alasan selisih kas)...'"
                    ></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 pt-2">
                <button 
                    v-if="!forced && mode === 'close'"
                    @click="emit('close')" 
                    class="w-1/2 py-3 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-xl hover:bg-slate-200 transition-colors cursor-pointer"
                >
                    Batal
                </button>
                <button 
                    @click="handleSubmit" 
                    :disabled="isSubmitting"
                    :class="['py-3 font-bold rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50', (forced || mode === 'open') ? 'w-full bg-primary text-primary-foreground hover:opacity-90' : 'w-1/2 bg-red-600 text-white hover:bg-red-700']"
                >
                    <Lock class="h-4 w-4" />
                    <span>{{ mode === 'open' ? 'Mulai Shift Kasir' : 'Tutup Shift Sekarang' }}</span>
                </button>
            </div>

            <p v-if="forced && mode === 'open'" class="text-[11px] text-center text-amber-600 dark:text-amber-400 font-medium">
                ⚠️ Anda harus membuka shift kasir terlebih dahulu untuk mengakses menu POS.
            </p>
        </div>
    </div>
</template>