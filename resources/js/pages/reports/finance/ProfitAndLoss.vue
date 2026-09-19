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

const formatRp = (amount: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
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
                    <div class="text-2xl font-black">{{ reportData?.ratios?.net_margin || 0 }}%</div>
                    <span class="text-[11px] text-emerald-500 font-medium">Persentase laba dari pendapatan</span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 rounded-2xl shadow-xs space-y-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Margin Laba Kotor</span>
                    <div class="text-2xl font-black">{{ reportData?.ratios?.gross_margin || 0 }}%</div>
                    <span class="text-[11px] text-emerald-500 font-medium">Efisiensi produksi/modal</span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 rounded-2xl shadow-xs space-y-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Rasio Biaya Operasional</span>
                    <div class="text-2xl font-black">{{ reportData?.ratios?.opex_ratio || 0 }}%</div>
                    <span class="text-[11px] text-gray-400 font-medium">Beban terhadap pendapatan</span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 rounded-2xl shadow-xs space-y-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Laba Bersih</span>
                    <div class="text-xl font-black text-emerald-600">{{ formatRp(reportData?.net_profit?.amount) }}</div>
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
                            <tr v-for="item in reportData?.revenues?.items" :key="item.code" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20">
                                <td class="py-2.5 px-4 pl-6 text-gray-600 dark:text-gray-400">{{ item.name }} <span class="text-[10px] text-gray-400">({{ item.code }})</span></td>
                                <td class="py-2.5 px-4 text-right font-medium">{{ formatRp(item.balance) }}</td>
                            </tr>

                            <tr class="bg-emerald-50/40 dark:bg-emerald-950/20 font-bold border-t border-emerald-100 dark:border-emerald-900/30">
                                <td class="py-3 px-4 text-emerald-900 dark:text-emerald-200">Total Pendapatan</td>
                                <td class="py-3 px-4 text-right text-emerald-600 dark:text-emerald-400 font-bold text-sm">{{ formatRp(reportData?.revenues?.total) }}</td>
                            </tr>

                            <tr class="bg-gray-100/60 dark:bg-gray-800/30 font-bold">
                                <td colspan="2" class="py-2.5 px-4 text-gray-700 dark:text-gray-300 pt-4">Beban Pokok Penjualan (HPP)</td>
                            </tr>
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20">
                                <td class="py-2.5 px-4 pl-6 text-gray-600 dark:text-gray-400">Total HPP / Modal Produksi Terjual</td>
                                <td class="py-2.5 px-4 text-right font-medium text-red-500">- {{ formatRp(reportData?.cogs?.total) }}</td>
                            </tr>

                            <tr :class="[
                                'font-bold border-t',
                                (reportData?.gross_profit?.amount || 0) >= 0
                                    ? 'bg-emerald-50/40 dark:bg-emerald-950/20 border-emerald-100 dark:border-emerald-900/30'
                                    : 'bg-red-50/40 dark:bg-red-950/20 border-red-100 dark:border-red-900/30'
                            ]">
                                <td class="py-3 px-4 text-gray-800 dark:text-gray-200">
                                    {{ (reportData?.gross_profit?.amount || 0) >= 0 ? 'Laba Kotor' : 'Rugi Kotor' }}
                                    <span :class="[
                                        'text-[10px] font-bold px-2 py-0.5 rounded-full ml-2',
                                        (reportData?.gross_profit?.amount || 0) >= 0
                                            ? 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/60 dark:text-emerald-300'
                                            : 'text-red-700 bg-red-100 dark:bg-red-900/60 dark:text-red-300'
                                    ]">
                                        Margin: {{ reportData?.gross_profit?.margin }}%
                                    </span>
                                </td>
                                <td :class="[
                                    'py-3 px-4 text-right font-bold text-sm',
                                    (reportData?.gross_profit?.amount || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                                ]">
                                    {{ formatRp(reportData?.gross_profit?.amount) }}
                                </td>
                            </tr>

                            <tr class="bg-gray-100/60 dark:bg-gray-800/30 font-bold">
                                <td colspan="2" class="py-2.5 px-4 text-gray-700 dark:text-gray-300 pt-4">Biaya Operasional (Beban)</td>
                            </tr>
                            <tr v-for="item in reportData?.expenses?.items" :key="item.code" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20">
                                <td class="py-2.5 px-4 pl-6 text-gray-600 dark:text-gray-400">{{ item.name }} <span class="text-[10px] text-gray-400">({{ item.code }})</span></td>
                                <td class="py-2.5 px-4 text-right font-medium">{{ formatRp(item.balance) }}</td>
                            </tr>

                            <tr class="bg-gray-100/40 dark:bg-gray-800/20 font-bold border-t border-gray-200 dark:border-gray-800">
                                <td class="py-3 px-4 text-gray-800 dark:text-gray-200">Total Biaya Operasional</td>
                                <td class="py-3 px-4 text-right text-red-500 font-bold">- {{ formatRp(reportData?.expenses?.total) }}</td>
                            </tr>

                            <tr :class="[
                                'font-black text-sm border-t-2',
                                (reportData?.net_profit?.amount || 0) >= 0
                                    ? 'bg-emerald-100/60 dark:bg-emerald-900/40 border-emerald-500'
                                    : 'bg-red-100/60 dark:bg-red-900/40 border-red-500'
                            ]">
                                <td class="py-4 px-4 text-gray-900 dark:text-gray-100">
                                    {{ (reportData?.net_profit?.amount || 0) >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}
                                    <span :class="[
                                        'text-[10px] font-bold px-2 py-0.5 rounded-full ml-2',
                                        (reportData?.net_profit?.amount || 0) >= 0
                                            ? 'text-emerald-800 bg-emerald-200 dark:bg-emerald-800 dark:text-emerald-200'
                                            : 'text-red-800 bg-red-200 dark:bg-red-800 dark:text-red-200'
                                    ]">
                                        Net Margin: {{ reportData?.net_profit?.margin }}%
                                    </span>
                                </td>
                                <td :class="[
                                    'py-4 px-4 text-right font-black text-base',
                                    (reportData?.net_profit?.amount || 0) >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-600 dark:text-red-400'
                                ]">
                                    {{ formatRp(reportData?.net_profit?.amount) }}
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</template>
