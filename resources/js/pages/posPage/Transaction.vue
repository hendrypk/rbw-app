<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';

import axios from 'axios';
import { 
    Search, 
    ClipboardList, 
    Receipt, 
    ArrowLeft, 
    User, 
    CheckCircle2, 
    Clock,
    Printer,
    FileText,
    Calendar,
    DollarSign
} from '@lucide/vue';
import webPos from '@/routes/web-pos';
import { SearchXIcon } from '@lucide/vue';

// State
const transactions = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const activeFilter = ref('all'); // 'all', 'unpaid', 'paid'
const selectedTransaction = ref(null);

// Ambil data transaksi dari endpoint API yang Anda buat
const fetchTransactions = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/pos/orders');
        if (response.data.success) {
            transactions.value = response.data.data;
            
            // Auto-select transaksi pertama jika data tersedia
            if (transactions.value.length > 0) {
                selectedTransaction.value = transactions.value[0];
            }
        }
    } catch (error) {
        console.error("Gagal memuat data transaksi:", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchTransactions();
});

// Helper Format Tanggal Indonesia
const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Filter data di sisi frontend (Search nama/nomor nota + Tab Status)
const filteredTransactions = computed(() => {
    return transactions.value.filter(trx => {
        const matchesSearch = trx.order_number.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                              (trx.customer_name && trx.customer_name.toLowerCase().includes(searchQuery.value.toLowerCase()));
        
        if (activeFilter.value === 'all') return matchesSearch;
        return matchesSearch && trx.status === activeFilter.value;
    });
});

const selectTransaction = (trx) => {
    selectedTransaction.value = trx;
};
</script>

<template>
    <!-- <Head title="Daftar Transaksi" /> -->

    <div class="flex h-screen w-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 overflow-hidden">
        
        <div class="w-full md:w-[420px] flex flex-col bg-white dark:bg-zinc-900 border-r border-slate-200 dark:border-zinc-800 h-full shrink-0">
            
            <div class="p-4 border-b border-slate-100 dark:border-zinc-800 flex items-center gap-3 bg-slate-50/50 dark:bg-zinc-900/50">
                <Link :href="webPos.index()" class="p-2 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-zinc-50">Riwayat Transaksi</h3>
                    <p class="text-[11px] text-slate-400">Daftar invoice & order unpaid</p>
                </div>
            </div>

            <div class="p-4 border-b border-slate-100 dark:border-zinc-800">
                <div class="relative">
                    <SearchXIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                    <input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Cari nomor nota atau nama..."
                        class="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-slate-400 text-slate-900 dark:text-white"
                    />
                </div>
            </div>

            <div class="px-4 py-2 border-b border-slate-100 dark:border-zinc-800 bg-slate-50/30 grid grid-cols-3 gap-1 shrink-0">
                <button 
                    @click="activeFilter = 'all'"
                    :class="['py-1.5 text-xs font-bold rounded-lg transition-all border', activeFilter === 'all' ? 'bg-slate-900 border-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm' : 'bg-white border-slate-200 dark:bg-zinc-800 dark:border-zinc-700 text-slate-500 hover:bg-slate-50']"
                >
                    Semua
                </button>
                <button 
                    @click="activeFilter = 'unpaid'"
                    :class="['py-1.5 text-xs font-bold rounded-lg transition-all border flex items-center justify-center gap-1.5', activeFilter === 'unpaid' ? 'bg-amber-600 border-amber-600 text-white shadow-sm' : 'bg-white border-slate-200 dark:bg-zinc-800 dark:border-zinc-700 text-amber-600 hover:bg-amber-50']"
                >
                    <Clock class="h-3.5 w-3.5" /> Order
                </button>
                <button 
                    @click="activeFilter = 'paid'"
                    :class="['py-1.5 text-xs font-bold rounded-lg transition-all border flex items-center justify-center gap-1.5', activeFilter === 'paid' ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm' : 'bg-white border-slate-200 dark:bg-zinc-800 dark:border-zinc-700 text-emerald-600 hover:bg-emerald-50']"
                >
                    <CheckCircle2 class="h-3.5 w-3.5" /> Invoice
                </button>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-zinc-800/60 custom-scrollbar">
                <div v-if="isLoading" class="p-8 text-center text-xs text-slate-400 animate-pulse">
                    Sedang menarik data transaksi...
                </div>

                <div v-else-if="filteredTransactions.length === 0" class="text-center py-20 text-xs text-slate-400">
                    Tidak ada transaksi ditemukan.
                </div>

                <div 
                    v-else
                    v-for="trx in filteredTransactions" 
                    :key="trx.id"
                    @click="selectTransaction(trx)"
                    :class="['p-4 flex items-start gap-3 cursor-pointer transition-all border-b border-slate-50 dark:border-zinc-800/50 hover:bg-slate-50 dark:hover:bg-zinc-800/30', selectedTransaction?.id === trx.id ? 'bg-slate-100/70 dark:bg-zinc-800 border-l-4 border-slate-900 dark:border-zinc-100' : '']"
                >
                    <div :class="['p-2 rounded-xl shrink-0', trx.status === 'paid' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30' : 'bg-amber-50 text-amber-600 dark:bg-amber-950/30']">
                        <Receipt v-if="trx.status === 'paid'" class="h-4 w-4" />
                        <ClipboardList v-else class="h-4 w-4" />
                    </div>

                    <div class="flex-1 min-w-0 space-y-0.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-xs text-slate-900 dark:text-zinc-100 truncate">{{ trx.order_number }}</span>
                            <span class="text-[10px] text-slate-400 shrink-0">{{ formatDate(trx.created_at) }}</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-medium truncate">{{ trx.customer_name || 'Pelanggan Umum' }}</p>
                        
                        <div class="pt-1 flex items-center justify-between">
                            <span :class="['px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wide', trx.status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400']">
                                {{ trx.status === 'paid' ? 'Lunas' : 'Unpaid' }}
                            </span>
                            <span class="text-xs font-black text-slate-900 dark:text-zinc-50">
                                Rp {{ Number(trx.final_total).toLocaleString('id-ID') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hidden md:flex flex-1 flex-col h-full bg-slate-50 dark:bg-zinc-900/30 overflow-hidden">
            
            <div v-if="!selectedTransaction" class="flex-1 flex flex-col items-center justify-center text-slate-400 p-8 space-y-2">
                <FileText class="h-10 w-10 text-slate-300" />
                <p class="text-xs">Klik salah satu transaksi untuk melihat invoice lengkap.</p>
            </div>

            <div v-else class="flex-1 flex flex-col h-full overflow-hidden">
                
                <div class="p-5 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800 flex justify-between items-center shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-black text-slate-900 dark:text-zinc-50 tracking-tight">{{ selectedTransaction.order_number }}</h2>
                            <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider text-white', selectedTransaction.status === 'paid' ? 'bg-emerald-600' : 'bg-amber-500']">
                                {{ selectedTransaction.status === 'paid' ? 'Invoice (Lunas)' : 'Order (Belum Lunas)' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">Metode Bayar: <span class="uppercase font-bold text-slate-600 dark:text-zinc-300">{{ selectedTransaction.payment_method }}</span></p>
                    </div>
                    
                    <button class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg shadow-sm transition-colors">
                        <Printer class="h-3.5 w-3.5" /> Cetak Struk
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-5 custom-scrollbar">
                    
                    <div class="grid grid-cols-3 gap-4 bg-white dark:bg-zinc-900 p-4 rounded-xl border border-slate-100 dark:border-zinc-800 shadow-sm text-xs">
                        <div class="space-y-0.5">
                            <span class="text-slate-400 font-medium block">Nama Pelanggan</span>
                            <span class="font-bold text-slate-800 dark:text-zinc-200">{{ selectedTransaction.customer_name || 'Pelanggan Umum' }}</span>
                        </div>
                        <div class="space-y-0.5">
                            <span class="text-slate-400 font-medium block">Waktu Transaksi</span>
                            <span class="font-bold text-slate-800 dark:text-zinc-200">{{ formatDate(selectedTransaction.created_at) }}</span>
                        </div>
                        <div class="space-y-0.5">
                            <span class="text-slate-400 font-medium block">Nominal Tunai Masuk</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400 font-mono">Rp {{ Number(selectedTransaction.amount_paid).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50/60 dark:bg-zinc-900/40 border-b border-slate-100 dark:border-zinc-800 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                            Rincian Pembelian Item
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-zinc-800/40">
                            <div v-for="item in selectedTransaction.items" :key="item.id" class="p-4 flex items-center justify-between text-xs">
                                <div class="space-y-0.5">
                                    <h4 class="font-bold text-slate-800 dark:text-zinc-200">{{ item.menu?.name || 'Item POS' }}</h4>
                                    <span class="text-slate-400 font-medium">Rp {{ Number(item.price).toLocaleString('id-ID') }} × {{ item.quantity }}</span>
                                </div>
                                <span class="font-bold font-mono text-slate-900 dark:text-zinc-50">
                                    Rp {{ Number(item.subtotal).toLocaleString('id-ID') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedTransaction.notes" class="bg-amber-50/50 dark:bg-amber-950/10 p-3.5 rounded-xl border border-amber-100 dark:border-amber-900/30 text-xs">
                        <span class="font-bold text-amber-700 dark:text-amber-400 block mb-0.5">📝 Catatan Kasir/Dapur:</span>
                        <p class="text-slate-600 dark:text-zinc-300 font-medium">{{ selectedTransaction.notes }}</p>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-slate-100 dark:border-zinc-800 shadow-sm space-y-2 text-xs">
                        <div class="flex justify-between text-slate-500 font-medium">
                            <span>Subtotal kotor</span>
                            <span>Rp {{ Number(selectedTransaction.subtotal).toLocaleString('id-ID') }}</span>
                        </div>
                        <div v-if="selectedTransaction.discount > 0" class="flex justify-between text-red-500 font-medium">
                            <span>Potongan Diskon</span>
                            <span>-Rp {{ Number(selectedTransaction.discount).toLocaleString('id-ID') }}</span>
                        </div>
                        <div v-if="selectedTransaction.transaction_fee > 0" class="flex justify-between text-slate-500 font-medium">
                            <span>Biaya Tambahan / Layanan</span>
                            <span>+Rp {{ Number(selectedTransaction.transaction_fee).toLocaleString('id-ID') }}</span>
                        </div>
                        <div class="border-t border-slate-100 dark:border-zinc-800 pt-3 flex justify-between items-center text-sm font-bold">
                            <span class="text-slate-800 dark:text-zinc-200">Total Tagihan Bersih (Net)</span>
                            <span class="text-base font-black text-slate-900 dark:text-zinc-50 font-mono">
                                Rp {{ Number(selectedTransaction.final_total).toLocaleString('id-ID') }}
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</template>