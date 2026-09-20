<script setup lang="ts">
import { ref, onMounted, watch, onUnmounted } from 'vue';
import axios from 'axios';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useSwal } from '@/composables/useSwal';
import { useOutlet } from '@/composables/useOutlet';
import DatePresetFilter from '@/components/DatePresetFilter.vue';

defineOptions({ layout: AppSidebarLayout });

const { error } = useSwal();
const { getOutletId } = useOutlet();
const selectedOutletId = ref<string>(localStorage.getItem('active_outlet_id') || 'all');


const isLoading = ref(false);
const reportData = ref<any>(null);

const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];

const startDate = ref(firstDay);
const endDate = ref(lastDay);

const handleDateChange = (startOrRange: any, end?: string) => {
    if (typeof startOrRange === 'object' && startOrRange !== null) {
        startDate.value = startOrRange.startDate || startOrRange.start;
        endDate.value = startOrRange.endDate || startOrRange.end;
    } else if (typeof startOrRange === 'string' && typeof end === 'string') {
        startDate.value = startOrRange;
        endDate.value = end;
    }
    fetchReport();
};

const fetchReport = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/reports/finance/profit-and-loss', {
            params: {
                outlet_id: selectedOutletId.value || '',
                start_date: startDate.value,
                end_date: endDate.value,
            }
        });
        if (response.data?.success) {
            reportData.value = response.data.data;
        }
    } catch (err: any) {
        error('Gagal', err.response?.data?.message || 'Gagal memuat laporan.');
    } finally {
        isLoading.value = false;
    }
};

// Fungsi untuk memformat angka dengan tanda kurung jika bersaldo normal Kredit
const formatRpCreditNormal = (value: number) => {
    const absVal = Math.abs(value);
    const formatted = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(absVal);

    // Jika nilai negatif atau tidak nol, bungkus dengan kurung
    if (value !== 0) {
        return `(${formatted.replace('IDR', '').trim()})`;
    }
    return formatted.replace('IDR', '').trim();
};


const handleOutletChanged = () => {
    selectedOutletId.value = getOutletId() || 'all';
    fetchReport()
};

onMounted(() => {
    fetchReport();
    handleOutletChanged();
    window.addEventListener('outlet-changed', handleOutletChanged);
});

onUnmounted(() => {
    window.removeEventListener('outlet-changed', handleOutletChanged);
});

watch(() => selectedOutletId.value, () => fetchReport());

const formatRp = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value || 0).replace('IDR', '').trim();
};
</script>

<template>
    <div class="min-h-screen bg-gray-50/50 dark:bg-black text-gray-900 dark:text-gray-100 p-6 space-y-6">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 max-w-6xl mx-auto">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Laporan Laba Rugi & Rasio Keuangan</h1>
                <p class="text-gray-500 text-xs mt-1">Ringkasan performa finansial berdasarkan struktur akun operasional.</p>
            </div>
            <DatePresetFilter :initial-start="startDate" :initial-end="endDate" @change="handleDateChange" />
        </div>

        <div v-if="isLoading" class="py-24 flex justify-center">
            <div class="w-6 h-6 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin"></div>
        </div>

        <div v-else-if="reportData" class="max-w-6xl mx-auto space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 rounded-2xl shadow-xs space-y-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Margin Laba Bersih</span>
                    <div class="text-2xl font-black" :class="(reportData?.total?.net_profit ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ reportData?.total?.trading_income ? ((reportData.total.net_profit / reportData.total.trading_income) * 100).toFixed(1) : '0.0' }}%
                    </div>
                    <span class="text-[11px] text-gray-400 font-medium">Persentase laba dari pendapatan</span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 rounded-2xl shadow-xs space-y-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Margin Laba Kotor</span>
                    <div class="text-2xl font-black" :class="(reportData?.total?.gross_profit ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ reportData?.total?.trading_income ? ((reportData.total.gross_profit / reportData.total.trading_income) * 100).toFixed(1) : '0.0' }}%
                    </div>
                    <span class="text-[11px] text-gray-400 font-medium">Efisiensi produksi/modal</span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 rounded-2xl shadow-xs space-y-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Rasio Biaya Operasional</span>
                    <div class="text-2xl font-black text-gray-800 dark:text-gray-100">
                        {{ reportData?.total?.trading_income ? ((reportData.total.expenses / reportData.total.trading_income) * 100).toFixed(1) : '0.0' }}%
                    </div>
                    <span class="text-[11px] text-gray-400 font-medium">Beban terhadap pendapatan</span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 rounded-2xl shadow-xs space-y-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Laba Bersih</span>
                    <div class="text-xl font-black" :class="(reportData?.total?.net_profit ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ formatRp(reportData?.total?.net_profit) }}
                    </div>
                    <span class="text-[11px] text-gray-400 font-medium">Net Profit Periode Ini</span>
                </div>
            </div>

             <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm text-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/75 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 text-gray-500 uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4 font-bold">Keterangan Komponen</th>
                                <th class="py-3 px-4 font-bold text-right">Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr class="bg-gray-100/60 dark:bg-gray-800/30 font-bold">
                                <td colspan="2" class="py-2.5 px-4 text-gray-700 dark:text-gray-300">Pendapatan</td>
                            </tr>
                            <tr v-for="item in reportData?.data?.sales" :key="item.account_id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20">
                                <td class="py-2.5 px-4 pl-6 text-gray-600 dark:text-gray-400">
                                    {{ item.account.name }}
                                    <span class="text-[10px] text-gray-400">({{ item.account.ref_code }})</span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-medium text-gray-800 dark:text-gray-200">{{ formatRpCreditNormal(item.net) }}</td>
                            </tr>

                            <tr class="bg-gray-50/60 dark:bg-gray-800/20 font-bold border-t border-gray-200 dark:border-gray-800">
                                <td class="py-3 px-4 text-gray-800 dark:text-gray-200">Total Pendapatan</td>
                                <td class="py-3 px-4 text-right font-bold text-sm text-gray-800 dark:text-gray-200">
                                    {{ formatRpCreditNormal(reportData?.total?.trading_income) }}
                                </td>
                            </tr>

                            <tr class="bg-gray-100/60 dark:bg-gray-800/30 font-bold">
                                <td colspan="2" class="py-2.5 px-4 text-gray-700 dark:text-gray-300 pt-4">Beban Pokok Penjualan (HPP)</td>
                            </tr>
                            <tr v-for="item in reportData?.data?.cost_of_sales" :key="item.account_id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20">
                                <td class="py-2.5 px-4 pl-6 text-gray-600 dark:text-gray-400">
                                    {{ item.account.name }}
                                    <span class="text-[10px] text-gray-400">({{ item.account.ref_code }})</span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-medium text-gray-800 dark:text-gray-200">{{ formatRp(item.net) }}</td>
                            </tr>

                            <tr class="bg-gray-100/40 dark:bg-gray-800/20 font-bold border-t border-gray-200 dark:border-gray-800">
                                <td class="py-3 px-4 text-gray-800 dark:text-gray-200">Total Beban Pokok Penjualan</td>
                                <td class="py-3 px-4 text-right font-bold text-gray-800 dark:text-gray-200">{{ formatRp(reportData?.total?.cost_of_sales) }}</td>
                            </tr>

                            <tr class="font-bold text-sm border-t-2 border-gray-300 dark:border-gray-700"
                                :class="(reportData?.total?.gross_profit ?? 0) >= 0 ? 'bg-emerald-50/60 dark:bg-emerald-950/20 text-emerald-800 dark:text-emerald-300' : 'bg-rose-50/60 dark:bg-rose-950/20 text-rose-800 dark:text-rose-300'">
                                <td class="py-3.5 px-4">LABA / RUGI KOTOR</td>
                                <td class="py-3.5 px-4 text-right font-bold text-base">
                                    <span class="ml-2 text-xs font-normal opacity-80">
                                        ({{ reportData?.total?.trading_income ? ((reportData.total.gross_profit / reportData.total.trading_income) * 100).toFixed(1) : '0.0' }}%)
                                    </span>
                                    {{ formatRp(reportData?.total?.gross_profit) }}
                                </td>
                            </tr>

                            <tr class="bg-gray-100/60 dark:bg-gray-800/30 font-bold">
                                <td colspan="2" class="py-2.5 px-4 text-gray-700 dark:text-gray-300 pt-4">Biaya Operasional</td>
                            </tr>
                            <tr v-for="item in reportData?.data?.expenses" :key="item.account_id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20">
                                <td class="py-2.5 px-4 pl-6 text-gray-600 dark:text-gray-400">
                                    {{ item.account.name }}
                                    <span class="text-[10px] text-gray-400">({{ item.account.ref_code }})</span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-medium text-gray-800 dark:text-gray-200">{{ formatRp(item.net) }}</td>
                            </tr>

                            <tr class="bg-gray-100/40 dark:bg-gray-800/20 font-bold border-t border-gray-200 dark:border-gray-800">
                                <td class="py-3 px-4 text-gray-800 dark:text-gray-200">Total Biaya Operasional</td>
                                <td class="py-3 px-4 text-right font-bold text-gray-800 dark:text-gray-200">{{ formatRp(reportData?.total?.expenses) }}</td>
                            </tr>

                            <tr class="font-black text-sm border-t-2"
                                :class="(reportData?.total?.net_profit ?? 0) >= 0 ? 'bg-emerald-100/60 dark:bg-emerald-900/40 border-emerald-500 text-emerald-700 dark:text-emerald-300' : 'bg-rose-100/60 dark:bg-rose-900/40 border-rose-500 text-rose-700 dark:text-rose-300'">
                                <td class="py-4 px-4">LABA / RUGI BERSIH</td>
                                <td class="py-4 px-4 text-right font-black text-base">
                                    {{ formatRp(reportData?.total?.net_profit) }}
                                    <span class="ml-2 text-xs font-normal opacity-80">
                                        ({{ reportData?.total?.trading_income ? ((reportData.total.net_profit / reportData.total.trading_income) * 100).toFixed(1) : '0.0' }}%)
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</template>
