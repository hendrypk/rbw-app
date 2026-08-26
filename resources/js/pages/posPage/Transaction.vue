<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
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
    DollarSign,
    QrCode,
    CreditCard,
    Check,
    X
} from '@lucide/vue';
import webPos from '@/routes/web-pos';
import { SearchXIcon } from '@lucide/vue';
import { toast } from 'vue-sonner';
import QrcodeVue from 'qrcode.vue';
import PosLayout from '@/layouts/PosLayout.vue';

// Import composables thermal printer & receipt builder
import { useThermalPrinter } from '@/composables/useThermalPrinter';
import { useReceiptBuilder, ReceiptData } from '@/composables/useReceiptBuilder';

defineOptions({
    layout: PosLayout
});

// Inisialisasi Composable Printer & Builder
const { print } = useThermalPrinter();
const { generateReceiptText } = useReceiptBuilder();

// Interface TypeScript untuk Data Transaksi
interface TransactionItem {
    id?: number | string;
    name?: string;
    product_name?: string;
    quantity?: number;
    qty?: number;
    price: number;
    subtotal?: number;
    menu?: {
        name?: string;
    };
}

interface Transaction {
    id: number | string;
    order_number: string;
    customer_name?: string;
    status: string;
    final_total: number;
    total?: number;
    subtotal?: number;
    discount?: number;
    created_at?: string;
    payment_method?: string;
    notes?: string;
    items?: TransactionItem[];
}

// State Utama dengan Type Safety
const transactions = ref<Transaction[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');
const activeFilter = ref('all');
const selectedTransaction = ref<Transaction | null>(null);

// State Modal Pembayaran & QRIS
const isPaymentModalOpen = ref(false);
const paymentMethod = ref('cash');
const amountPaidInput = ref(0);
const isGeneratingQris = ref(false);

const isQrisModalOpen = ref(false);
const qrisData = ref({
    invoiceNo: '',
    referenceNo: '',
    qrContent: ''
});
const qrisPaymentStatus = ref('PENDING'); // PENDING, SUCCESS, FAILED
let statusInterval: ReturnType<typeof setInterval> | undefined = undefined;

// State Countdown Timer (15 Menit)
const remainingSeconds = ref(900);
let timerInterval: ReturnType<typeof setInterval> | null = null;

const formattedCountdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const startCountdown = () => {
    remainingSeconds.value = 900;
    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        if (remainingSeconds.value > 0) {
            remainingSeconds.value--;
        } else {
            qrisPaymentStatus.value = 'FAILED';
            if (timerInterval) clearInterval(timerInterval);
            if (statusInterval) clearInterval(statusInterval);
        }
    }, 1000);
};

// Ambil data transaksi dari endpoint API
const fetchTransactions = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/pos/orders');
        if (response.data.success) {
            transactions.value = response.data.data;
            
            if (transactions.value.length > 0 && !selectedTransaction.value) {
                selectedTransaction.value = transactions.value[0];
            } else if (selectedTransaction.value) {
                const updated = transactions.value.find(t => t.id === selectedTransaction.value?.id);
                if (updated) selectedTransaction.value = updated;
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

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const filteredTransactions = computed(() => {
    return transactions.value.filter(trx => {
        const matchesSearch = trx.order_number.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                              (trx.customer_name && trx.customer_name.toLowerCase().includes(searchQuery.value.toLowerCase()));
        
        if (activeFilter.value === 'all') return matchesSearch;
        return matchesSearch && trx.status === activeFilter.value;
    });
});

const selectTransaction = (trx: Transaction) => {
    selectedTransaction.value = trx;
};

// =========================================================
// HANDLER CETAK STRUK BLUETOOTH / FALLBACK
// =========================================================
const handlePrintReceipt = async () => {
    if (!selectedTransaction.value) {
        toast.error("Tidak ada transaksi yang dipilih untuk dicetak.");
        return;
    }

    try {
        const transactionData: ReceiptData = {
            storeName: "MANUFIX.ID POS",
            storeAddress: "Bantul, Yogyakarta",
            cashierName: "Admin",
            items: (selectedTransaction.value.items || []).map((item: Record<string, any>) => ({
                name: item.menu?.name || item.name || item.product_name || 'Item POS',
                qty: Number(item.quantity || item.qty || 1),
                price: Number(item.price || 0)
            })),
            total: Number(selectedTransaction.value.final_total || selectedTransaction.value.total || 0),
            footerMessage: `No. Invoice: ${selectedTransaction.value.order_number}\nTerima Kasih Telah Berbelanja!`
        };

        const strukText = generateReceiptText(transactionData);
        await print(strukText);
        toast.success("Perintah cetak dikirim.");
    } catch (error: any) {
        console.error("Gagal mencetak struk:", error);
        toast.error("Gagal mencetak struk.");
    }
};

const openPaymentModal = (trx: Transaction) => {
    selectedTransaction.value = trx;
    amountPaidInput.value = Number(trx.final_total);
    paymentMethod.value = 'cash';
    isPaymentModalOpen.value = true;
};

const closePaymentModal = () => {
    isPaymentModalOpen.value = false;
};

const processPayment = async () => {
    if (!selectedTransaction.value) return;

    if (paymentMethod.value === 'qris') {
        await handleQrisCheckout();
        return;
    }

    try {
        const response = await axios.post(`/api/pos/orders/${selectedTransaction.value.id}/pay`, {
            payment_method: paymentMethod.value,
            amount_paid: paymentMethod.value === 'cash' ? amountPaidInput.value : selectedTransaction.value.final_total
        });

        if (response.data.success || response.status === 200) {
            toast.success(`Order ${selectedTransaction.value.order_number} berhasil dilunasi!`);
            closePaymentModal();
            fetchTransactions();
        }
    } catch (error: any) {
        console.error("Gagal memproses pembayaran:", error);
        toast.error(error.response?.data?.message || 'Gagal memproses pelunasan order.');
    }
};

const handleQrisCheckout = async () => {
    if (isGeneratingQris.value || !selectedTransaction.value) return;
    isGeneratingQris.value = true;

    try {
        closePaymentModal();

        const qrisResponse = await axios.post('/api/payment/qris/generate', {
            order_number: selectedTransaction.value.order_number,
            amount: Number(selectedTransaction.value.final_total)
        });

        if (qrisResponse.data.status === 'success') {
            qrisData.value.invoiceNo = selectedTransaction.value.order_number;
            qrisData.value.referenceNo = qrisResponse.data.data.reference_no;
            qrisData.value.qrContent = qrisResponse.data.data.qr_content;

            isQrisModalOpen.value = true;
            qrisPaymentStatus.value = 'PENDING';
            startCountdown();
            startPollingStatus();
        } else {
            throw new Error(qrisResponse.data.message || 'Gagal meng-generate QRIS DOKU');
        }
    } catch (error: any) {
        const errorMsg = error.response?.data?.error || error.response?.data?.message || error.message || 'Gagal menyiapkan QRIS';
        toast.error(errorMsg);
        console.error('QRIS Checkout Error:', error);
    } finally {
        isGeneratingQris.value = false;
    }
};

const startPollingStatus = () => {
    if (statusInterval) clearInterval(statusInterval);

    statusInterval = setInterval(async () => {
        try {
            const response = await axios.post('/api/payment/qris/check-status', {
                order_number: qrisData.value.invoiceNo,
                reference_no: qrisData.value.referenceNo
            });

            if (response.data.status === 'success' && response.data.paid) {
                qrisPaymentStatus.value = 'SUCCESS';
                clearInterval(statusInterval);
                if (timerInterval) clearInterval(timerInterval);
                fetchTransactions();
            } else if (response.data.status === 'FAILED') {
                qrisPaymentStatus.value = 'FAILED';
                clearInterval(statusInterval);
                if (timerInterval) clearInterval(timerInterval);
            }
        } catch (error) {
            console.error('Gagal mengecek status pembayaran', error);
        }
    }, 4000);
};

const closeQrisModal = () => {
    isQrisModalOpen.value = false;
    if (statusInterval) clearInterval(statusInterval);
    if (timerInterval) clearInterval(timerInterval);
    fetchTransactions();
};

onBeforeUnmount(() => {
    if (statusInterval) clearInterval(statusInterval);
    if (timerInterval) clearInterval(timerInterval);
});
</script>

<template>
    <div class="flex h-screen w-full bg-slate-100 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 overflow-hidden font-sans">
        
        <!-- KOLOM KIRI: LIST TRANSAKSI -->
        <div class="w-full md:w-105 flex flex-col bg-white dark:bg-zinc-900 border-r border-slate-200 dark:border-zinc-800 h-full shrink-0 shadow-sm">
            <div class="p-4 border-b border-slate-100 dark:border-zinc-800 flex items-center gap-3 bg-slate-50/50 dark:bg-zinc-900/50">
                <Link :href="webPos.index()" class="p-2 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors shadow-2xs">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h3 class="font-black text-sm text-slate-900 dark:text-zinc-50 tracking-tight">Riwayat Transaksi</h3>
                    <p class="text-[11px] text-slate-400 font-medium">Kelola invoice & order pending</p>
                </div>
            </div>

            <div class="p-4 border-b border-slate-100 dark:border-zinc-800">
                <div class="relative">
                    <SearchXIcon class="absolute left-3.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                    <input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Cari nomor nota atau nama pelanggan..."
                        class="w-full pl-10 pr-4 py-2.5 text-xs bg-slate-50 dark:bg-zinc-800/80 border border-slate-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-primary text-slate-900 dark:text-white placeholder:text-slate-400 font-medium"
                    />
                </div>
            </div>

            <div class="px-4 py-2 border-b border-slate-100 dark:border-zinc-800 bg-slate-50/30 grid grid-cols-3 gap-1.5 shrink-0">
                <button @click="activeFilter = 'all'" :class="['py-2 text-xs font-bold rounded-xl transition-all border', activeFilter === 'all' ? 'bg-slate-900 border-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm' : 'bg-white border-slate-200 dark:bg-zinc-800 dark:border-zinc-700 text-slate-500']">Semua</button>
                <button @click="activeFilter = 'unpaid'" :class="['py-2 text-xs font-bold rounded-xl transition-all border flex items-center justify-center gap-1.5', activeFilter === 'unpaid' ? 'bg-amber-500 border-amber-500 text-white shadow-sm' : 'bg-white border-slate-200 dark:bg-zinc-800 dark:border-zinc-700 text-amber-600']"><Clock class="h-3.5 w-3.5" /> Order</button>
                <button @click="activeFilter = 'paid'" :class="['py-2 text-xs font-bold rounded-xl transition-all border flex items-center justify-center gap-1.5', activeFilter === 'paid' ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm' : 'bg-white border-slate-200 dark:bg-zinc-800 dark:border-zinc-700 text-emerald-600']"><CheckCircle2 class="h-3.5 w-3.5" /> Lunas</button>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-zinc-800/60 custom-scrollbar">
                <div v-if="isLoading" class="p-8 text-center text-xs text-slate-400 animate-pulse">Memuat data transaksi...</div>
                <div v-else-if="filteredTransactions.length === 0" class="text-center py-20 text-xs text-slate-400">Tidak ada transaksi ditemukan.</div>
                
                <div 
                    v-else
                    v-for="trx in filteredTransactions" 
                    :key="trx.id"
                    @click="selectTransaction(trx)"
                    :class="['p-4 flex items-start gap-3 cursor-pointer transition-all border-b border-slate-50 dark:border-zinc-800/50 hover:bg-slate-50 dark:hover:bg-zinc-800/30', selectedTransaction?.id === trx.id ? 'bg-primary/5 dark:bg-zinc-800/80 border-l-4 border-primary' : '']"
                >
                    <div :class="['p-2.5 rounded-2xl shrink-0', trx.status === 'paid' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40' : 'bg-amber-50 text-amber-600 dark:bg-amber-950/40']">
                        <Receipt v-if="trx.status === 'paid'" class="h-4 w-4" />
                        <ClipboardList v-else class="h-4 w-4" />
                    </div>
                    <div class="flex-1 min-w-0 space-y-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-extrabold text-xs text-slate-900 dark:text-zinc-100 truncate tracking-tight">{{ trx.order_number }}</span>
                            <span class="text-[10px] text-slate-400 font-mono shrink-0">{{ formatDate(trx.created_at ?? '') }}</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-semibold truncate">{{ trx.customer_name || 'Pelanggan Umum' }}</p>
                        <div class="pt-1 flex items-center justify-between">
                            <span :class="['px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider', trx.status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400']">
                                {{ trx.status === 'paid' ? 'Lunas' : 'Belum Bayar' }}
                            </span>
                            <span class="text-xs font-black text-slate-900 dark:text-zinc-50 font-mono">Rp {{ Number(trx.final_total).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: DETAIL STRUK & TOMBOL BAYAR -->
        <div class="hidden md:flex flex-1 flex-col h-full bg-slate-100 dark:bg-zinc-950 overflow-hidden">
            <div v-if="!selectedTransaction" class="flex-1 flex flex-col items-center justify-center text-slate-400 p-8 space-y-2">
                <FileText class="h-12 w-12 text-slate-300 dark:text-zinc-700" />
                <p class="text-xs font-medium">Pilih transaksi di sebelah kiri untuk melihat rincian.</p>
            </div>

            <div v-else class="flex-1 flex flex-col h-full overflow-y-auto p-6 md:p-10 custom-scrollbar items-center">
                <div class="w-full max-w-xl bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200/80 dark:border-zinc-800 shadow-xl overflow-hidden flex flex-col">
                    
                    <div class="p-6 md:p-8 border-b border-dashed border-slate-200 dark:border-zinc-800 text-center space-y-2 bg-slate-50/50 dark:bg-zinc-900/40">
                        <div class="inline-flex p-3 rounded-2xl bg-primary/10 text-primary mb-1"><Receipt class="h-6 w-6" /></div>
                        <h2 class="text-lg font-black text-slate-900 dark:text-zinc-50 tracking-tight">MANUFIX.ID POS</h2>
                        <p class="text-xs text-slate-400 font-medium">Bukti Transaksi / Nota Pembayaran</p>
                        <span :class="['px-3 py-1 rounded-full text-xs font-black tracking-wider uppercase', selectedTransaction.status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200']">
                            {{ selectedTransaction.status === 'paid' ? '✓ LUNAS (PAID)' : '⏳ BELUM LUNAS (ORDER)' }}
                        </span>
                    </div>

                    <div class="p-6 md:p-8 space-y-6 text-xs flex-1">
                        <div class="grid grid-cols-2 gap-4 bg-slate-50 dark:bg-zinc-800/40 p-4 rounded-2xl border border-slate-100 dark:border-zinc-800">
                            <div class="space-y-1"><span class="text-slate-400 font-semibold block uppercase text-[10px]">No. Invoice</span><span class="font-extrabold text-slate-800 dark:text-zinc-200 font-mono">{{ selectedTransaction.order_number }}</span></div>
                            <div class="space-y-1"><span class="text-slate-400 font-semibold block uppercase text-[10px]">Waktu</span><span class="font-bold text-slate-800 dark:text-zinc-200">{{ formatDate(selectedTransaction.created_at ?? '') }}</span></div>
                            <div class="space-y-1"><span class="text-slate-400 font-semibold block uppercase text-[10px]">Pelanggan</span><span class="font-bold text-slate-800 dark:text-zinc-200">{{ selectedTransaction.customer_name || 'Pelanggan Umum' }}</span></div>
                            <div class="space-y-1"><span class="text-slate-400 font-semibold block uppercase text-[10px]">Metode Bayar</span><span class="font-bold text-slate-800 dark:text-zinc-200 uppercase">{{ selectedTransaction.payment_method }}</span></div>
                        </div>

                        <div class="space-y-3">
                            <span class="text-[11px] font-black tracking-wider text-slate-400 uppercase block">Rincian Item Pesanan</span>
                            <div class="divide-y divide-slate-100 dark:divide-zinc-800/60 border-y border-slate-100 dark:border-zinc-800">
                                <div v-for="item in selectedTransaction.items" :key="item.id" class="py-3 flex items-center justify-between">
                                    <div class="space-y-0.5 pr-4"><h4 class="font-bold text-slate-800 dark:text-zinc-200 text-xs">{{ item.menu?.name || item.name || 'Item POS' }}</h4><span class="text-[11px] text-slate-400 font-medium">Rp {{ Number(item.price).toLocaleString('id-ID') }} × {{ item.quantity || item.qty || 1 }}</span></div>
                                    <span class="font-extrabold font-mono text-slate-900 dark:text-zinc-50 text-xs">Rp {{ Number(item.subtotal || (item.price * (item.quantity || item.qty || 1))).toLocaleString('id-ID') }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedTransaction.notes" class="bg-amber-50/60 dark:bg-amber-950/20 p-3.5 rounded-2xl border border-amber-200/60">
                            <span class="font-bold text-amber-700 dark:text-amber-400 block mb-0.5 text-[11px]">📝 Catatan:</span>
                            <p class="text-slate-600 dark:text-zinc-300 font-medium">{{ selectedTransaction.notes }}</p>
                        </div>

                        <div class="space-y-2 pt-2 border-t border-dashed border-slate-200 dark:border-zinc-800">
                            <div class="flex justify-between text-slate-500 font-medium"><span>Subtotal</span><span>Rp {{ Number(selectedTransaction.subtotal || selectedTransaction.final_total).toLocaleString('id-ID') }}</span></div>
                            <div v-if="selectedTransaction.discount && selectedTransaction.discount > 0" class="flex justify-between text-red-500 font-medium"><span>Diskon</span><span>-Rp {{ Number(selectedTransaction.discount).toLocaleString('id-ID') }}</span></div>
                            <div class="pt-3 flex justify-between items-center text-sm font-bold border-t border-slate-200 dark:border-zinc-800">
                                <span class="text-slate-900 dark:text-zinc-50 font-black text-sm">Total Tagihan Bersih</span>
                                <span class="text-lg font-black text-primary font-mono">Rp {{ Number(selectedTransaction.final_total).toLocaleString('id-ID') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Struk dengan Handler Cetak Bluetooth -->
                    <div class="p-6 bg-slate-50 dark:bg-zinc-900/80 border-t border-slate-200/80 dark:border-zinc-800 flex items-center gap-3">
                        <button 
                            @click="handlePrintReceipt"
                            class="flex-1 py-3 bg-white dark:bg-zinc-800 hover:bg-slate-100 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold rounded-2xl shadow-2xs transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <Printer class="h-4 w-4" /> Cetak Struk
                        </button>
                        
                        <button 
                            v-if="selectedTransaction && selectedTransaction.status !== 'paid'"
                            @click="openPaymentModal(selectedTransaction)"
                            class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black rounded-2xl shadow-md transition-all flex items-center justify-center gap-2 active:scale-95 cursor-pointer"
                        >
                            <DollarSign class="h-4 w-4" /> Bayar Sekarang
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MODAL PEMBAYARAN INTERAKTIF                               -->
        <!-- ========================================================= -->
        <div v-if="isPaymentModalOpen && selectedTransaction" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col">
                
                <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/40">
                    <h4 class="font-bold text-sm text-slate-900 dark:text-zinc-100">Penyelesaian Pembayaran Invoice</h4>
                    <button @click="closePaymentModal" class="p-1.5 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                </div>

                <div class="p-6 space-y-5">
                    <div class="p-4 bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 rounded-2xl flex items-center justify-between shadow-sm">
                        <span class="text-xs font-semibold opacity-80">Total Tagihan:</span>
                        <span class="text-xl font-black tracking-tight font-mono">Rp {{ Number(selectedTransaction?.final_total || 0).toLocaleString('id-ID') }}</span>
                    </div>

                    <div class="space-y-2">
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Pilih Metode Pembayaran</span>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="method in ['cash', 'qris', 'edc']" :key="method" @click="paymentMethod = method" :class="['py-2.5 text-xs font-bold rounded-xl border uppercase transition-all', paymentMethod === method ? 'bg-slate-900 border-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm' : 'bg-white border-slate-200 text-slate-500 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400']">
                                {{ method }}
                            </button>
                        </div>
                    </div>

                    <!-- Kalkulator Uang Tunai -->
                    <div v-if="paymentMethod === 'cash'" class="p-4 bg-slate-50 dark:bg-zinc-800/50 rounded-2xl space-y-3 border border-slate-200/60 dark:border-zinc-700">
                        <div class="grid grid-cols-4 gap-2 text-xs">
                            <button @click="amountPaidInput = Number(selectedTransaction.final_total)" class="py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg font-semibold text-slate-700 dark:text-zinc-200 shadow-2xs">Uang Pas</button>
                            <button @click="amountPaidInput = 25000" class="py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg font-semibold text-slate-700 dark:text-zinc-200 shadow-2xs">25k</button>
                            <button @click="amountPaidInput = 50000" class="py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg font-semibold text-slate-700 dark:text-zinc-200 shadow-2xs">50k</button>
                            <button @click="amountPaidInput = 100000" class="py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg font-semibold text-slate-700 dark:text-zinc-200 shadow-2xs">100k</button>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-xs pt-1">
                            <span class="font-medium text-slate-500 dark:text-zinc-400">Nominal Tunai Diterima:</span>
                            <input v-model.number="amountPaidInput" type="number" class="w-36 text-right font-bold px-3 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none text-slate-900 dark:text-white font-mono" />
                        </div>
                        <div v-if="amountPaidInput >= Number(selectedTransaction?.final_total || 0)" class="flex justify-between items-center text-xs text-emerald-600 font-bold bg-emerald-50 dark:bg-emerald-950/30 p-2.5 rounded-xl">
                            <span>Kembalian:</span>
                            <span class="font-mono">Rp {{ (amountPaidInput - Number(selectedTransaction.final_total)).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 grid grid-cols-2 gap-3">
                    <button @click="closePaymentModal" type="button" class="py-3 bg-white border border-slate-200 text-slate-700 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300 font-bold rounded-2xl text-xs">Kembali</button>
                    
                    <button @click="processPayment" :disabled="isGeneratingQris" type="button" class="py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-2xl text-xs shadow-md transition-all flex items-center justify-center gap-2">
                        <span v-if="isGeneratingQris" class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                        {{ paymentMethod === 'qris' ? (isGeneratingQris ? 'Memproses QR...' : 'Generate QRIS') : 'Konfirmasi Lunas' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- DOKU QRIS MODAL DENGAN STRUKTUR BARU & COUNTDOWN TIMER    -->
        <!-- ========================================================= -->
        <div v-if="isQrisModalOpen" class="fixed inset-0 z-100 flex items-center justify-center p-4 sm:p-8 bg-slate-900/90 dark:bg-black/90 backdrop-blur-md transition-all duration-300">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-4xl border border-slate-200/60 dark:border-zinc-800 shadow-2xl overflow-hidden flex flex-col relative">
                
                <!-- Modal Header -->
                <div class="bg-slate-50 dark:bg-zinc-800/50 p-5 sm:px-8 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                    <h4 class="font-extrabold text-base sm:text-lg text-slate-800 dark:text-zinc-100 uppercase tracking-widest">Pembayaran QRIS</h4>
                    <button @click="closeQrisModal" class="p-2 bg-white dark:bg-zinc-700 rounded-full text-slate-400 hover:text-slate-700 dark:hover:text-zinc-100 border border-slate-200 dark:border-zinc-600 shadow-sm transition-all hover:scale-105">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- STATE 1: PENDING (QR CODE DISPLAYED) -->
                <div v-if="qrisPaymentStatus === 'PENDING'" class="p-6 sm:p-10 flex flex-col items-center">
                    
                    <div class="w-full bg-slate-50 dark:bg-zinc-800/50 rounded-3xl p-6 mb-8 border border-slate-100 dark:border-zinc-700/50 space-y-4 shadow-sm">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 pb-5 border-b border-slate-200 dark:border-zinc-700 border-dashed">
                            <span class="text-sm sm:text-base font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Total Tagihan</span>
                            <span class="text-lg sm:text-xl font-black text-slate-900 dark:text-zinc-50 tracking-tight text-center">
                                Rp {{ Number(selectedTransaction?.final_total || 0).toLocaleString('id-ID') }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <div class="flex justify-between sm:justify-start items-center gap-3">
                                <span class="text-sm font-medium text-slate-500 dark:text-zinc-400">Invoice:</span>
                                <span class="font-bold text-sm text-slate-700 dark:text-zinc-200 font-mono bg-slate-200 dark:bg-zinc-700 px-2.5 py-1 rounded-md">{{ qrisData.invoiceNo }}</span>
                            </div>
                            <div class="flex justify-between sm:justify-end items-center gap-3">
                                <span class="text-sm font-medium text-slate-500 dark:text-zinc-400">Pelanggan:</span>
                                <span class="font-bold text-sm sm:text-base text-slate-800 dark:text-zinc-100">{{ selectedTransaction?.customer_name || 'Pelanggan Umum' }}</span>
                            </div>
                        </div>

                        <!-- COUNTDOWN TIMER DISPLAY -->
                        <div class="pt-3 border-t border-slate-200/60 dark:border-zinc-700 flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400">Batas Waktu Bayar:</span>
                            <span class="text-sm font-black font-mono px-3 py-1 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-lg border border-red-200 dark:border-red-900/50">
                                ⏱️ {{ formattedCountdown }}
                            </span>
                        </div>
                    </div>

                    <!-- QR Code Container -->
                    <div class="p-5 sm:p-6 bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.1)] border-2 border-slate-100 dark:border-zinc-700 mb-8 relative">
                        <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-blue-600 text-white px-5 py-1.5 rounded-full text-xs sm:text-sm font-black tracking-widest uppercase shadow-lg border-2 border-white dark:border-zinc-900 whitespace-nowrap">
                            Scan Untuk Bayar
                        </div>
                        <qrcode-vue 
                            :value="qrisData.qrContent" 
                            :size="360"
                            level="H"
                            foreground="#0f172a" 
                            class="w-full h-auto max-w-90 aspect-square object-contain"
                        />
                    </div>
                    
                    <!-- Polling Status Indicator -->
                    <div class="flex items-center gap-3 justify-center py-2.5 px-6 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 rounded-full text-sm font-bold animate-pulse border border-amber-200 dark:border-amber-800/50 shadow-sm">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        Menunggu Pembayaran Pelanggan...
                    </div>
                </div>

                <!-- STATE 2: SUCCESS -->
                <div v-else-if="qrisPaymentStatus === 'SUCCESS'" class="py-16 px-8 space-y-6 flex flex-col items-center text-center">
                    <div class="w-28 h-28 bg-emerald-100 dark:bg-emerald-950/50 rounded-full flex items-center justify-center text-emerald-500 text-6xl shadow-inner border-8 border-emerald-50 dark:border-emerald-900/30">
                        ✓
                    </div>
                    <div class="space-y-2">
                        <h3 class="font-black text-3xl text-slate-900 dark:text-zinc-50">Pembayaran Sukses!</h3>
                        <p class="text-base text-slate-500 dark:text-zinc-400 leading-relaxed max-w-md mx-auto">
                            Tagihan sebesar <b class="text-slate-800 dark:text-zinc-200">Rp {{ Number(selectedTransaction?.final_total || 0).toLocaleString('id-ID') }}</b> telah lunas.
                        </p>
                    </div>
                    
                    <button @click="closeQrisModal" class="mt-8 w-full sm:w-2/3 py-4 bg-slate-900 hover:bg-slate-800 dark:bg-zinc-100 dark:hover:bg-white dark:text-zinc-900 text-white font-black rounded-2xl text-base shadow-xl transition-all">
                        Tutup & Refresh Data
                    </button>
                </div>

                <!-- STATE 3: FAILED -->
                <div v-else class="py-16 px-8 space-y-6 flex flex-col items-center text-center">
                    <div class="w-28 h-28 bg-red-100 dark:bg-red-950/50 rounded-full flex items-center justify-center text-red-500 text-6xl shadow-inner border-8 border-red-50 dark:border-red-900/30">
                        ✕
                    </div>
                    <div class="space-y-2">
                        <h3 class="font-black text-3xl text-slate-900 dark:text-zinc-50">Transaksi Gagal</h3>
                        <p class="text-base text-slate-500 dark:text-zinc-400 max-w-md mx-auto">Waktu pembayaran untuk tagihan ini telah habis atau dibatalkan oleh sistem.</p>
                    </div>
                    
                    <button @click="closeQrisModal" class="mt-8 w-full sm:w-2/3 py-4 bg-white border-2 border-slate-200 text-slate-700 hover:bg-slate-50 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300 font-black rounded-2xl text-base shadow-sm transition-all">
                        Tutup Jendela
                    </button>
                </div>

            </div>
        </div>

    </div>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar { display: none; }
.scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
</style>