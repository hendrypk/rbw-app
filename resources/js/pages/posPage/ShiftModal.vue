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
    <div v-if="isOpen" class="fixed inset-0 bg-black/40 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-950 rounded-[2.5rem] max-w-xl w-full p-8 border border-slate-200/80 dark:border-zinc-800 shadow-2xl space-y-6 animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Header Modal -->
            <div class="flex items-center gap-4">
                <div :class="['p-3.5 rounded-2xl flex items-center justify-center', mode === 'open' ? 'bg-primary/10 text-primary' : 'bg-rose-500/10 text-rose-500']">
                    <Wallet class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="font-bold text-lg tracking-tight text-slate-900 dark:text-zinc-50">
                        {{ mode === 'open' ? 'Buka Shift Kasir Baru' : 'Tutup Shift & Rekap Kasir' }}
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ mode === 'open' ? 'Masukkan modal awal uang tunai di laci.' : 'Tinjau ringkasan performa dan hitung uang fisik.' }}
                    </p>
                </div>
            </div>

            <!-- Ringkasan Performa Shift (Mode Close) -->
            <div v-if="mode === 'close'" class="space-y-4">
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Ringkasan Shift Ini</div>
                
                <div v-if="isLoadingSummary" class="text-center py-8 text-xs text-slate-400 font-medium">
                    Memuat kalkulasi ringkasan shift...
                </div>

                <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block">Total Omzet</span>
                        <div class="font-extrabold text-slate-900 dark:text-white text-sm font-mono">{{ formatRupiah(shiftSummary.total_omzet) }}</div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block">Total Nota</span>
                        <div class="font-extrabold text-slate-900 dark:text-white text-sm font-mono">{{ shiftSummary.total_nota }} <span class="text-[10px] font-normal text-slate-400">Nota</span></div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block">Item Terjual</span>
                        <div class="font-extrabold text-slate-900 dark:text-white text-sm font-mono">{{ shiftSummary.total_items }} <span class="text-[10px] font-normal text-slate-400">Pcs</span></div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block">Tunai (Cash)</span>
                        <div class="font-bold text-slate-900 dark:text-white text-xs font-mono">{{ formatRupiah(shiftSummary.omzet_cash) }}</div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 space-y-1">
                        <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block">QRIS</span>
                        <div class="font-bold text-slate-900 dark:text-white text-xs font-mono">{{ formatRupiah(shiftSummary.omzet_qris) }}</div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 space-y-1">
                        <span class="text-emerald-600 dark:text-emerald-400 text-[10px] uppercase font-bold tracking-wider block">Laba Bersih</span>
                        <div class="font-extrabold text-emerald-700 dark:text-emerald-300 text-sm font-mono">{{ formatRupiah(shiftSummary.net_profit) }}</div>
                    </div>
                </div>

                <!-- Kalkulasi Kas Seharusnya & Selisih -->
                <div class="p-4 bg-slate-50/80 dark:bg-zinc-900 rounded-2xl text-xs space-y-2.5 border border-slate-100 dark:border-zinc-800">
                    <div class="flex justify-between text-slate-500 font-medium">
                        <span>Modal Awal:</span>
                        <span class="font-bold text-slate-700 dark:text-zinc-300 font-mono">{{ formatRupiah(shiftSummary.starting_cash) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-500 font-medium">
                        <span>+ Omzet Tunai (Cash):</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400 font-mono">+ {{ formatRupiah(shiftSummary.omzet_cash) }}</span>
                    </div>
                    <div class="pt-2.5 border-t border-slate-200/60 dark:border-zinc-800 flex justify-between items-center font-bold">
                        <span class="text-slate-700 dark:text-zinc-200 text-[11px] uppercase tracking-wider">Kas Seharusnya di Laci:</span>
                        <span class="text-primary text-sm font-black font-mono">{{ formatRupiah(shiftSummary.expected_cash) }}</span>
                    </div>

                    <div v-if="cashInput > 0" :class="[
                        'p-3 rounded-xl flex items-center justify-between font-bold text-xs mt-2 transition-all border',
                        cashDifference === 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-100 dark:border-emerald-900/50' :
                        cashDifference > 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-100 dark:border-emerald-900/50' :
                        'bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 border-rose-100 dark:border-rose-900/50'
                    ]">
                        <span class="flex items-center gap-1.5 uppercase text-[10px] tracking-wider font-extrabold">
                            <CheckCircle2 v-if="cashDifference === 0" class="w-4 h-4 text-emerald-600" />
                            <ArrowUpRight v-else-if="cashDifference > 0" class="w-4 h-4 text-emerald-600" />
                            <ArrowDownRight v-else class="w-4 h-4 text-rose-600" />
                            Status Kas:
                        </span>
                        <span class="text-xs font-black font-mono">
                            {{ cashDifference === 0 ? 'PAS (Normal)' : cashDifference > 0 ? `SURPLUS +${formatRupiah(cashDifference)}` : `MINUS ${formatRupiah(cashDifference)}` }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Form Input -->
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                        {{ mode === 'open' ? 'Modal Kas Awal (Rp)' : 'Uang Fisik Aktual di Laci (Rp)' }}
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-sm font-bold text-slate-400 font-mono">Rp</span>
                        <input 
                            type="number" 
                            v-model.number="cashInput" 
                            class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 text-slate-900 dark:text-zinc-100 font-bold font-mono text-base focus:outline-none focus:border-slate-400 transition-all"
                            placeholder="0"
                            autofocus
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Catatan</label>
                    <textarea 
                        v-model="notesInput" 
                        rows="2"
                        class="w-full px-4 py-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 text-slate-900 dark:text-zinc-100 text-xs font-medium focus:outline-none focus:border-slate-400 transition-all resize-none"
                        :placeholder="mode === 'open' ? 'Catatan shift...' : 'Catatan penutupan shift...'"
                    ></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 pt-2">
                <button 
                    v-if="!forced && mode === 'close'"
                    @click="emit('close')" 
                    class="w-1/2 py-3.5 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-900 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-2xl text-xs cursor-pointer transition-colors"
                >
                    Batal
                </button>
                <button 
                    @click="handleSubmit" 
                    :disabled="isSubmitting"
                    :class="['py-3.5 font-bold rounded-2xl text-xs transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 shadow-sm', (forced || mode === 'open') ? 'w-full bg-slate-900 text-white dark:bg-white dark:text-slate-900 hover:opacity-90' : 'w-1/2 bg-rose-600 text-white hover:bg-rose-500']"
                >
                    <Lock class="h-4 w-4" />
                    <span>{{ mode === 'open' ? 'Mulai Shift Kasir' : 'Tutup Shift Sekarang' }}</span>
                </button>
            </div>

            <p v-if="forced && mode === 'open'" class="text-[11px] text-center text-rose-500 font-semibold">
                ⚠️ Anda harus membuka shift kasir terlebih dahulu untuk mengakses menu POS.
            </p>
        </div>
    </div>
</template>