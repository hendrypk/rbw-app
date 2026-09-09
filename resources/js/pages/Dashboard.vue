<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { DollarSign, Receipt, ShoppingCart, Banknote, QrCode, TrendingUp, PackageOpen, Award, Clock, Calendar } from '@lucide/vue';
import axios from 'axios';
import DatePresetFilter from '@/components/DatePresetFilter.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const outlets = ref<Array<{ id: string; name: string }>>([]);
const selectedOutletId = ref<string>('all');
const isLoading = ref(true);
const dateRange = ref({ start: '', end: '' });

const summary = ref({
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

// FUNGSI UNTUK MENANGANI PERUBAHAN TANGGAL
const handleDateChange = (range: { start: string; end: string }) => {
    dateRange.value = range;
    fetchDashboardSummary(); // Panggil ulang API dengan parameter tanggal baru
};

// Ambil daftar outlet
const fetchOutlets = async () => {
    try {
        const response = await axios.get('/api/outlets');
        if (response.data.success) {
            outlets.value = response.data.data;
        }
    } catch (error) {
        console.error("Gagal mengambil daftar outlet", error);
    }
};

// Ambil data ringkasan dashboard dari ReportController
const fetchDashboardSummary = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/summary', {
            headers: { 
                'X-Outlet-ID': selectedOutletId.value 
            },
            params: {
                start_date: dateRange.value.start,
                end_date: dateRange.value.end
            }
        });

        if (response.data.success) {
            summary.value = response.data.data;
        }
    } catch (error) {
        console.error("Gagal memuat ringkasan dashboard", error);
    } finally {
        isLoading.value = false;
    }
};

const handleOutletChange = () => {
    fetchDashboardSummary();
};

const formatRupiah = (val: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};

const formatHourRange = (hourStr: string) => {
    const startHour = parseInt(hourStr.split(':')[0], 10);
    const endHour = (startHour + 1) % 24;
    return `${String(startHour).padStart(2, '0')}:00 - ${String(endHour).padStart(2, '0')}:00`;
};

onMounted(async () => {
    await fetchOutlets();
    // fetchDashboardSummary() tidak perlu dipanggil di sini karena komponen DatePresetFilter 
    // akan memicu event 'change' saat pertama kali dimuat yang otomatis menjalankan fetchDashboardSummary().
});
</script>

<template>
    <Head title="Dashboard Ringkasan Penjualan" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 overflow-x-auto">
        <div class="flex items-center justify-between sm:justify-end gap-1.5 w-full">
            <div class="flex items-center justify-end gap-1.5 w-full sm:w-auto">
                <div class="flex items-center shrink-0">
                    <DatePresetFilter @change="handleDateChange" class="px-2 py-1 rounded-md border border-input bg-secondary text-foreground font-medium text-[11px] outline-none focus:ring-1 focus:ring-ring cursor-pointer" />
                </div>

                <div class="flex items-center shrink-0 max-w-[120px] sm:max-w-[160px]">
                    <select 
                        v-model="selectedOutletId" 
                        @change="handleOutletChange"
                        class="w-full px-2 py-1 rounded-md border border-input bg-secondary text-foreground font-medium text-[11px] outline-none focus:ring-1 focus:ring-ring cursor-pointer truncate"
                    >
                        <option value="all">Semua Outlet</option>
                        <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                            {{ outlet.name }}
                        </option>
                    </select>
                </div>
            </div>

            <button 
                @click="fetchDashboardSummary" 
                :disabled="isLoading"
                title="Muat Ulang"
                class="p-1.5 text-[11px] font-medium bg-secondary text-secondary-foreground rounded-md hover:bg-accent transition-colors cursor-pointer disabled:opacity-50 flex items-center justify-center border border-input shrink-0"
            >
                <span v-if="isLoading" class="w-3.5 h-3.5 border-2 border-muted-foreground border-t-foreground rounded-full animate-spin"></span>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                    <path d="M3 3v5h5"/>
                    <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
                    <path d="M16 21h5v-5"/>
                </svg>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-5 rounded-xl bg-card border border-border space-y-2 relative overflow-hidden shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground relative z-10">
                    <span class="text-xs font-medium uppercase tracking-wider">Total Omzet</span>
                    <DollarSign class="w-4 h-4" />
                </div>
                <div class="text-2xl font-bold tracking-tight text-foreground relative z-10">
                    {{ formatRupiah(summary.total_omzet) }}
                </div>
            </div>

            <div class="p-5 rounded-xl bg-card border border-border space-y-2 relative overflow-hidden shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground relative z-10">
                    <span class="text-xs font-medium uppercase tracking-wider">Total Nota (Transaksi)</span>
                    <Receipt class="w-4 h-4" />
                </div>
                <div class="text-2xl font-bold tracking-tight text-foreground relative z-10">
                    {{ summary.total_nota }} <span class="text-xs font-normal text-muted-foreground">Nota</span>
                </div>
            </div>

            <div class="p-5 rounded-xl bg-card border border-border space-y-2 relative overflow-hidden shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground relative z-10">
                    <span class="text-xs font-medium uppercase tracking-wider">Total Item Terjual</span>
                    <ShoppingCart class="w-4 h-4" />
                </div>
                <div class="text-2xl font-bold tracking-tight text-foreground relative z-10">
                    {{ summary.total_items }} <span class="text-xs font-normal text-muted-foreground">Pcs</span>
                </div>
            </div>
        </div>

        <div class="p-6 rounded-xl bg-card border border-border shadow-2xs flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="space-y-1 text-center md:text-left">
                <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground flex items-center justify-center md:justify-start gap-1.5">
                    <TrendingUp class="w-4 h-4" /> Estimasi Laba Bersih (Net Profit)
                </span>
                <div class="text-3xl font-bold tracking-tight text-foreground">
                    {{ formatRupiah(summary.net_profit) }}
                </div>
                <p class="text-xs text-muted-foreground">Dihitung dari Total Omzet dikurangi Total HPP dan Total Overhead.</p>
            </div>
            <div class="bg-secondary px-4 py-2.5 rounded-lg border border-border text-center shrink-0">
                <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground block">Margin Keuntungan</span>
                <span class="text-lg font-bold text-foreground">{{ summary.profit_margin }}%</span>
            </div>
        </div>

        <div class="pt-2">
            <h3 class="text-xs font-medium uppercase text-muted-foreground mb-3 tracking-wider flex items-center gap-2">
                <Banknote class="w-4 h-4" /> Rincian Pembayaran
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-card border border-border flex items-center justify-between shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-secondary text-foreground rounded-lg shrink-0">
                            <Banknote class="w-4 h-4" />
                        </div>
                        <div>
                            <span class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Tunai (Cash)</span>
                            <div class="text-base font-bold tracking-tight text-foreground mt-0.5">{{ formatRupiah(summary.omzet_cash) }}</div>
                        </div>
                    </div>
                    <div class="text-right space-y-0.5 text-xs">
                        <div class="text-foreground font-medium">{{ summary.nota_cash }} <span class="text-[10px] font-normal text-muted-foreground">Nota</span></div>
                        <div class="text-muted-foreground">{{ summary.items_cash }} <span class="text-[10px] font-normal text-muted-foreground">Pcs</span></div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-card border border-border flex items-center justify-between shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-secondary text-foreground rounded-lg shrink-0">
                            <QrCode class="w-4 h-4" />
                        </div>
                        <div>
                            <span class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">QRIS</span>
                            <div class="text-base font-bold tracking-tight text-foreground mt-0.5">{{ formatRupiah(summary.omzet_qris) }}</div>
                        </div>
                    </div>
                    <div class="text-right space-y-0.5 text-xs">
                        <div class="text-foreground font-medium">{{ summary.nota_qris }} <span class="text-[10px] font-normal text-muted-foreground">Nota</span></div>
                        <div class="text-muted-foreground">{{ summary.items_qris }} <span class="text-[10px] font-normal text-muted-foreground">Pcs</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-2 space-y-3">
            <h3 class="text-xs font-medium uppercase text-muted-foreground tracking-wider flex items-center gap-2">
                <Clock class="w-4 h-4" /> Analisis Jam Ramai Transaksi
            </h3>
            
            <div class="bg-card rounded-xl border border-border p-5 shadow-2xs space-y-4">
                <div v-if="Object.keys(summary.peak_hours).length === 0" class="text-center py-8 text-muted-foreground text-xs flex flex-col items-center justify-center">
                    <Clock class="w-8 h-8 mb-2 opacity-40" />
                    Belum ada data transaksi pada rentang waktu ini.
                </div>
                
                <div v-else class="space-y-3">
                    <div v-for="(data, hour) in summary.peak_hours" :key="hour" class="space-y-1">
                        <div class="flex justify-between text-xs">
                            <span class="font-medium text-foreground">
                                Pukul {{ formatHourRange(hour as string) }}
                            </span>
                            <span class="text-muted-foreground">
                                {{ data.total_transactions }} Nota • <strong class="text-foreground font-medium">{{ formatRupiah(data.total_omzet) }}</strong>
                            </span>
                        </div>
                        <div class="w-full bg-secondary h-2 rounded-full overflow-hidden">
                            <div 
                                class="bg-foreground h-full rounded-full transition-all duration-300" 
                                :style="{ width: `${Math.min((data.total_transactions / Math.max(1, ...Object.values(summary.peak_hours).map((d: any) => d.total_transactions))) * 100, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-2 space-y-3">
            <h3 class="text-xs font-medium uppercase text-muted-foreground tracking-wider flex items-center gap-2">
                <Award class="w-4 h-4" /> Daftar Produk Terjual
            </h3>
            
            <div class="bg-card rounded-xl border border-border overflow-hidden shadow-2xs">
                <div v-if="summary.sold_products.length === 0" class="text-center py-12 text-muted-foreground space-y-2">
                    <PackageOpen class="w-8 h-8 mx-auto opacity-40" />
                    <p class="text-xs">Belum ada produk yang terjual pada rentang waktu ini.</p>
                </div>

                <div v-else class="divide-y divide-border">
                    <div 
                        v-for="(product, index) in summary.sold_products" 
                        :key="index"
                        class="p-4 flex items-center justify-between hover:bg-secondary/50 transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-md bg-secondary text-muted-foreground text-xs font-medium flex items-center justify-center shrink-0">
                                {{ index + 1 }}
                            </span>
                            <div>
                                <h4 class="font-medium text-sm text-foreground tracking-tight">{{ product.item_name }}</h4>
                                <span class="text-xs text-muted-foreground">Terjual: <strong class="text-foreground font-medium">{{ product.total_qty }} Pcs</strong></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold tracking-tight text-foreground">{{ formatRupiah(product.total_revenue) }}</div>
                            <span class="text-[10px] text-muted-foreground">Total Pendapatan</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>