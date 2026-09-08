<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { DollarSign, Receipt, ShoppingCart, Banknote, QrCode, TrendingUp, PackageOpen, Award, Clock, Timer } from '@lucide/vue';
import axios from 'axios';

const summary = ref({
    shift_started_at: null as string | null,
    total_omzet: 0,
    omzet_cash: 0,
    nota_cash: 0,
    items_cash: 0,
    omzet_qris: 0,
    nota_qris: 0,
    items_qris: 0,
    total_nota: 0,
    total_items: 0,
    net_profit: 0,
    profit_margin: 0,
    sold_products: [] as Array<{ item_name: string; total_qty: number; total_revenue: number }>,
    peak_hours: {} as Record<string, { total_transactions: number; total_omzet: number }>
});
const isLoading = ref(true);
const activeDuration = ref('00j 00m 00d');
let durationTimer: any = null;

const fetchSummary = async () => {
    isLoading.value = true;
    try {
        const outletId = localStorage.getItem('active_outlet_id');
        const shiftId = localStorage.getItem('active_cashier_shift_id');

        const response = await axios.get('/api/pos/dashboard/summary', {
            headers: { 
                'X-Outlet-ID': outletId,
                'X-Shift-ID': shiftId 
            }
        });

        if (response.data.success) {
            summary.value = response.data.data;
            updateDuration();
        }
    } catch (error) {
        console.error("Gagal mengambil ringkasan dashboard", error);
    } finally {
        isLoading.value = false;
    }
};

// Hitung selisih waktu mulai shift hingga sekarang
const updateDuration = () => {
    if (!summary.value.shift_started_at) {
        activeDuration.value = '-';
        return;
    }

    const start = new Date(summary.value.shift_started_at).getTime();
    const now = new Date().getTime();
    const diff = Math.max(0, Math.floor((now - start) / 1000)); // dalam detik

    const hours = Math.floor(diff / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    const seconds = diff % 60;

    activeDuration.value = `${String(hours).padStart(2, '0')}j ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}d`;
};

const formatTanggalMulai = (dateStr: string | null) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('id-ID', { 
        weekday: 'short', 
        day: 'numeric', 
        month: 'short', 
        year: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false 
    });
};

const formatRupiah = (val: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};

const formatHourRange = (hourStr: string) => {
    const startHour = parseInt(hourStr.split(':')[0], 10);
    const endHour = (startHour + 1) % 24;
    
    const startFormatted = String(startHour).padStart(2, '0') + ':00';
    const endFormatted = String(endHour).padStart(2, '0') + ':00';
    
    return `${startFormatted} - ${endFormatted}`;
};

onMounted(() => {
    fetchSummary();
    durationTimer = setInterval(updateDuration, 1000);
});

onUnmounted(() => {
    if (durationTimer) clearInterval(durationTimer);
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Ringkasan Penjualan & Analisa</h2>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Statistik performa transaksi pada shift / hari aktif saat ini.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Info Shift Mulai & Durasi Live -->
                <div v-if="summary.shift_started_at" class="flex items-center gap-3 px-3.5 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl text-xs shadow-2xs">
                    <div class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400">
                        <Clock class="w-3.5 h-3.5 text-primary" />
                        <span>Mulai: <strong class="text-slate-800 dark:text-zinc-200">{{ formatTanggalMulai(summary.shift_started_at) }}</strong></span>
                    </div>
                    <div class="h-3 w-px bg-slate-200 dark:bg-zinc-700"></div>
                    <div class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                        <Timer class="w-3.5 h-3.5 animate-pulse" />
                        <span>Durasi: {{ activeDuration }}</span>
                    </div>
                </div>

                <button 
                    @click="fetchSummary" 
                    class="px-3.5 py-2 text-xs font-bold bg-slate-100 dark:bg-zinc-800 rounded-xl hover:bg-slate-200 transition-colors cursor-pointer"
                >
                    Muat Ulang
                </button>
            </div>
        </div>

        <!-- Kartu Metrik Utama -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Total Omzet -->
            <div class="p-5 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 border border-primary/20 space-y-2">
                <div class="flex items-center justify-between text-primary">
                    <span class="text-xs font-bold uppercase tracking-wider">Total Omzet</span>
                    <DollarSign class="w-5 h-5" />
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ formatRupiah(summary.total_omzet) }}
                </div>
            </div>

            <!-- Total Nota -->
            <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 space-y-2">
                <div class="flex items-center justify-between text-amber-500">
                    <span class="text-xs font-bold uppercase tracking-wider">Total Nota (Transaksi)</span>
                    <Receipt class="w-5 h-5" />
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ summary.total_nota }} <span class="text-xs font-normal text-slate-400">Nota</span>
                </div>
            </div>

            <!-- Total Items -->
            <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 space-y-2">
                <div class="flex items-center justify-between text-emerald-500">
                    <span class="text-xs font-bold uppercase tracking-wider">Total Item Terjual</span>
                    <ShoppingCart class="w-5 h-5" />
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ summary.total_items }} <span class="text-xs font-normal text-slate-400">Pcs</span>
                </div>
            </div>
        </div>

        <!-- Kartu Analisa Laba Bersih (Net Profit) -->
        <div class="p-6 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-md flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="space-y-1 text-center md:text-left">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-100 flex items-center justify-center md:justify-start gap-1.5">
                    <TrendingUp class="w-4 h-4" /> Estimasi Laba Bersih (Net Profit)
                </span>
                <div class="text-3xl font-black">
                    {{ formatRupiah(summary.net_profit) }}
                </div>
                <p class="text-xs text-emerald-100 opacity-90">Dihitung dari Total Omzet dikurangi Total HPP dan Total Overhead.</p>
            </div>
            <div class="bg-white/15 backdrop-blur-md px-5 py-3 rounded-xl border border-white/20 text-center shrink-0">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-200 block">Margin Keuntungan</span>
                <span class="text-xl font-black">{{ summary.profit_margin }}%</span>
            </div>
        </div>

        <!-- Rincian Omzet Berdasarkan Metode Pembayaran (Tunai vs QRIS) -->
        <div class="pt-2">
            <h3 class="text-xs font-bold uppercase text-slate-400 mb-3 tracking-wider">Rincian Pembayaran (Tunai vs QRIS)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Cash -->
                <div class="p-4 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 flex items-center justify-between shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-emerald-500/10 text-emerald-600 rounded-lg shrink-0">
                            <Banknote class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Tunai (Cash)</span>
                            <div class="text-base font-black text-slate-900 dark:text-white mt-0.5">{{ formatRupiah(summary.omzet_cash) }}</div>
                        </div>
                    </div>
                    <div class="text-right space-y-0.5 text-xs">
                        <div class="text-slate-600 dark:text-zinc-300 font-bold">{{ summary.nota_cash }} <span class="text-[10px] font-normal text-slate-400">Nota</span></div>
                        <div class="text-emerald-600 dark:text-emerald-400 font-bold">{{ summary.items_cash }} <span class="text-[10px] font-normal text-slate-400">Pcs</span></div>
                    </div>
                </div>

                <!-- QRIS -->
                <div class="p-4 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 flex items-center justify-between shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-sky-500/10 text-sky-600 rounded-lg shrink-0">
                            <QrCode class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">QRIS</span>
                            <div class="text-base font-black text-slate-900 dark:text-white mt-0.5">{{ formatRupiah(summary.omzet_qris) }}</div>
                        </div>
                    </div>
                    <div class="text-right space-y-0.5 text-xs">
                        <div class="text-slate-600 dark:text-zinc-300 font-bold">{{ summary.nota_qris }} <span class="text-[10px] font-normal text-slate-400">Nota</span></div>
                        <div class="text-sky-600 dark:text-sky-400 font-bold">{{ summary.items_qris }} <span class="text-[10px] font-normal text-slate-400">Pcs</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analisis Jam Ramai -->
        <div class="pt-2 space-y-3">
            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider flex items-center gap-2">
                <Clock class="w-4 h-4 text-primary" /> Analisis Jam Ramai Transaksi
            </h3>
            
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 p-5 shadow-2xs space-y-4">
                <div v-if="Object.keys(summary.peak_hours).length === 0" class="text-center py-8 text-slate-400 text-xs">
                    Belum ada data jam transaksi tercatat.
                </div>
                
                <div v-else class="space-y-3">
                    <div v-for="(data, hour) in summary.peak_hours" :key="hour" class="space-y-1">
                        <div class="flex justify-between text-xs">
                            <span class="font-bold text-slate-700 dark:text-zinc-300">
                                Pukul {{ formatHourRange(hour as string) }}
                            </span>
                            <span class="text-slate-500">
                                {{ data.total_transactions }} Nota • <strong class="text-primary">{{ formatRupiah(data.total_omzet) }}</strong>
                            </span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-zinc-800 h-2.5 rounded-full overflow-hidden">
                            <div 
                                class="bg-primary h-full rounded-full transition-all duration-500" 
                                :style="{ width: `${Math.min((data.total_transactions / Math.max(1, ...Object.values(summary.peak_hours).map((d: any) => d.total_transactions))) * 100, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daftar Produk Terjual -->
        <div class="pt-2 space-y-3">
            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider flex items-center gap-2">
                <Award class="w-4 h-4 text-primary" /> Daftar Produk Terjual
            </h3>
            
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 overflow-hidden shadow-2xs">
                <div v-if="summary.sold_products.length === 0" class="text-center py-12 text-slate-400 space-y-2">
                    <PackageOpen class="w-10 h-10 mx-auto opacity-40" />
                    <p class="text-xs">Belum ada produk yang terjual pada shift/hari ini.</p>
                </div>

                <div v-else class="divide-y divide-slate-100 dark:divide-zinc-800">
                    <div 
                        v-for="(product, index) in summary.sold_products" 
                        :key="index"
                        class="p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-zinc-800/40 transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-bold flex items-center justify-center shrink-0">
                                {{ index + 1 }}
                            </span>
                            <div>
                                <h4 class="font-bold text-sm text-slate-900 dark:text-white">{{ product.item_name }}</h4>
                                <span class="text-xs text-slate-500">Terjual: <strong class="text-primary">{{ product.total_qty }} Pcs</strong></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-slate-900 dark:text-white">{{ formatRupiah(product.total_revenue) }}</div>
                            <span class="text-[10px] text-slate-400">Total Pendapatan</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>