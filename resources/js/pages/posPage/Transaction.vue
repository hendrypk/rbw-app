<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Search,
    ClipboardList,
    Receipt,
    ArrowLeft,
    CheckCircle2,
    Clock,
    Printer,
    FileText,
    DollarSign,
    SearchXIcon
} from '@lucide/vue';
import pos from '@/routes/pos';
import { toast } from 'vue-sonner';
import PosLayout from '@/layouts/PosLayout.vue';

// Import komponen modal pembayaran
import PaymentModal from '@/components/pos/PaymentModal.vue';

// Import composables thermal printer & receipt builder
import { useThermalPrinter } from '@/composables/useThermalPrinter';
import { mapTransactionToReceiptData } from '@/composables/useReceiptFormatter';
import { formatCashierReceipt, formatCopyReceipt, formatKitchenReceipt } from '@/composables/useReceiptBuilder';

defineOptions({
    layout: PosLayout
});

// Inisialisasi Composable Printer & Builder
const { print } = useThermalPrinter();

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
    customer_id?: string | null;
    status: string;
    final_total: number;
    total?: number;
    subtotal?: number;
    discount?: number;
    created_at?: string;
    payment_method?: string;
    is_self_order?: string;
    amount_paid?: number;
    notes?: string;
    items?: TransactionItem[];
    outlet?: {
        name?: string;
    };
}

// State Utama dengan Type Safety
const transactions = ref<Transaction[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');
const activeFilter = ref('all');
const selectedTransaction = ref<Transaction | null>(null);

// State Modal Pembayaran & QRIS
const isPaymentModalOpen = ref(false);
const isSuccessModalOpen = ref(false);
const paymentMethod = ref('cash');
const amountPaidInput = ref(0);
const isGeneratingQris = ref(false);
const modalCustomerName = ref('');

const isQrisModalOpen = ref(false);
const qrisData = ref({
    invoiceNo: '',
    referenceNo: '',
    qrContent: ''
});
const qrisPaymentStatus = ref('PENDING'); // PENDING, SUCCESS, FAILED
const paymentStatus = ref('PENDING');
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
        const activeOutletId = localStorage.getItem('active_outlet_id');
        const response = await axios.get('/api/pos/orders', {
            headers: {
                'X-Outlet-ID': activeOutletId
            }
        });
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
const handlePrintCashierReceipt = async (transactionObj: any) => {
    if (!transactionObj) return;
    const formattedData = mapTransactionToReceiptData(transactionObj);
    const textStruk = formatCashierReceipt(formattedData);
    await print(textStruk);
    toast.success("Struk kasir dicetak.");
};

const handlePrintCopyReceipt = async () => {
    if (!selectedTransaction.value) {
        toast.error("Tidak ada transaksi yang dipilih.");
        return;
    }
    const formattedData = mapTransactionToReceiptData(selectedTransaction.value);
    const textCopyStruk = formatCopyReceipt(formattedData);
    await print(textCopyStruk);
    toast.success("Salinan struk (Copy) dicetak.");
};

// =========================================================
// HANDLER MODAL & PEMBAYARAN
// =========================================================
const openPaymentModal = (trx: Transaction) => {
    selectedTransaction.value = trx;
    amountPaidInput.value = Number(trx.final_total);
    modalCustomerName.value = trx.customer_name || 'Pelanggan Umum';
    paymentMethod.value = 'cash';
    paymentStatus.value = 'PENDING';
    isPaymentModalOpen.value = true;
};

const closePaymentModal = () => {
    isPaymentModalOpen.value = false;
};

const closeSuccessModal = () => {
    isSuccessModalOpen.value = false;
    fetchTransactions();
};

const processPayment = async () => {
    if (!selectedTransaction.value) return;

    try {
        // Melalui modal, event submit-cash dipastikan pembayarannya tunai
        const response = await axios.post(`/api/pos/orders/${selectedTransaction.value.id}/pay`, {
            payment_method: 'cash',
            amount_paid: amountPaidInput.value,
            customer_id: selectedTransaction.value.customer_id || null
        });

        if (response.data.success || response.status === 200) {
            closePaymentModal();
            paymentStatus.value = 'SUCCESS';
            isSuccessModalOpen.value = true;
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
        const qrisResponse = await axios.post('/api/payment/qris/generate', {
            order_number: selectedTransaction.value.order_number,
            amount: Number(selectedTransaction.value.final_total)
        });

        if (qrisResponse.data.status === 'success') {
            qrisData.value.invoiceNo = selectedTransaction.value.order_number;
            qrisData.value.referenceNo = qrisResponse.data.data.reference_no;
            qrisData.value.qrContent = qrisResponse.data.data.qr_content;

            closePaymentModal(); // Tutup setelah sukses men-generate
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

                isQrisModalOpen.value = false;
                paymentStatus.value = 'SUCCESS';
                isSuccessModalOpen.value = true;

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

    <!-- GANTI h-screen menjadi h-full agar beradaptasi dengan sisa tinggi PosLayout -->
    <div class="flex h-full w-full bg-[#F2F2F7] dark:bg-black text-slate-900 dark:text-white overflow-hidden font-sans selection:bg-orange-200">

        <!-- ========================================================= -->
        <!-- KOLOM KIRI: LIST TRANSAKSI (Master View)                  -->
        <!-- ========================================================= -->
        <div :class="['w-full md:w-[380px] lg:w-[420px] flex flex-col h-full bg-[#F2F2F7] dark:bg-black border-r border-slate-200 dark:border-[#2C2C2E] shrink-0 z-10', selectedTransaction ? 'hidden md:flex' : 'flex']">

            <!-- Header List (Shrink-0 agar tetap di atas) -->
            <div class="px-4 pt-6 pb-3 bg-[#F2F2F7]/95 dark:bg-black/95 backdrop-blur-xl z-20 shrink-0">
                <Link :href="pos.index()" class="text-orange-500 hover:text-orange-600 flex items-center gap-1 text-[17px] font-medium mb-2 transition-colors active:opacity-70 w-fit">
                    <ArrowLeft class="h-5 w-5" /> POS
                </Link>

                <!-- Search Bar -->
                <div class="relative flex items-center">
                    <Search class="absolute left-3.5 h-[18px] w-[18px] text-slate-400 dark:text-zinc-500" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari transaksi..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-200/60 dark:bg-[#1C1C1E] rounded-[10px] text-[16px] focus:outline-none focus:ring-2 focus:ring-orange-500/50 text-black dark:text-white placeholder:text-slate-500 dark:placeholder:text-zinc-500 transition-all"
                    />
                </div>
            </div>

            <!-- Area Scrollable List (Flex-1 mengambil sisa tinggi) -->
            <div class="flex-1 overflow-y-auto px-4 py-2 custom-scrollbar">

                <!-- Segmented Control Filter -->
                <div class="bg-slate-200/60 dark:bg-[#1C1C1E] p-0.5 rounded-[9px] flex mb-6">
                    <button @click="activeFilter = 'all'" :class="['flex-1 py-1.5 text-[13px] font-semibold rounded-[7px] transition-all', activeFilter === 'all' ? 'bg-white dark:bg-[#2C2C2E] shadow-sm text-black dark:text-white' : 'text-slate-500 dark:text-zinc-400']">Semua</button>
                    <button @click="activeFilter = 'unpaid'" :class="['flex-1 py-1.5 text-[13px] font-semibold rounded-[7px] transition-all', activeFilter === 'unpaid' ? 'bg-white dark:bg-[#2C2C2E] shadow-sm text-black dark:text-white' : 'text-slate-500 dark:text-zinc-400']">Belum Bayar</button>
                    <button @click="activeFilter = 'paid'" :class="['flex-1 py-1.5 text-[13px] font-semibold rounded-[7px] transition-all', activeFilter === 'paid' ? 'bg-white dark:bg-[#2C2C2E] shadow-sm text-black dark:text-white' : 'text-slate-500 dark:text-zinc-400']">Lunas</button>
                </div>

                <!-- Empty State -->
                <div v-if="isLoading" class="text-center mt-12 text-[15px] text-slate-500 animate-pulse">Memuat...</div>
                <div v-else-if="filteredTransactions.length === 0" class="text-center mt-12 text-[15px] text-slate-500">Tidak ada transaksi.</div>

                <!-- Grouped List -->
                <div v-else class="bg-white dark:bg-[#1C1C1E] rounded-[10px] overflow-hidden mb-8 shadow-sm">
                    <div
                        v-for="(trx, index) in filteredTransactions"
                        :key="trx.id"
                        @click="selectTransaction(trx)"
                        :class="[
                            'flex items-center gap-3 p-3.5 cursor-pointer active:bg-slate-100 dark:active:bg-zinc-800 transition-colors relative',
                            index !== filteredTransactions.length - 1 ? 'border-b border-slate-100 dark:border-[#2C2C2E] ml-12' : '',
                            selectedTransaction?.id === trx.id ? 'bg-orange-50/50 dark:bg-orange-900/10' : ''
                        ]"
                    >
                        <!-- Active Indicator (Orange Line) -->
                        <div v-if="selectedTransaction?.id === trx.id" class="absolute left-0 top-0 bottom-0 w-1 bg-orange-500 rounded-r-full -ml-12"></div>

                        <!-- Icon -->
                        <div :class="['w-9 h-9 rounded-full flex items-center justify-center shrink-0 -ml-12', trx.status === 'paid' ? 'bg-green-100 text-green-600 dark:bg-green-950/40 dark:text-green-500' : 'bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-500']">
                            <Receipt v-if="trx.status === 'paid'" class="w-[18px] h-[18px]" />
                            <Clock v-else class="w-[18px] h-[18px]" />
                        </div>

                        <!-- Data text -->
                        <div class="flex-1 min-w-0 pr-1">
                            <div class="flex justify-between items-center mb-0.5">
                                <span class="font-semibold text-[16px] text-black dark:text-white truncate">{{ trx.customer_name || 'Pelanggan POS' }}</span>
                                <span class="text-[14px] text-slate-500 dark:text-zinc-400 shrink-0 tabular-nums">Rp {{ Number(trx.final_total).toLocaleString('id-ID') }}</span>
                            </div>
                            <div class="flex justify-between items-center text-[13px]">
                                <span class="text-slate-500 dark:text-zinc-400 truncate">{{ trx.order_number }}</span>
                                <span class="text-slate-400 dark:text-zinc-500">{{ formatDate(trx.created_at ?? '') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- KOLOM KANAN: DETAIL STRUK & SCROLLABLE CONTAINER         -->
        <!-- ========================================================= -->
        <div :class="['flex-1 flex-col h-full bg-slate-100 dark:bg-zinc-950 overflow-hidden', selectedTransaction ? 'flex' : 'hidden md:flex']">

            <div class="md:hidden p-3 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800 flex items-center gap-2 shrink-0">
                <button @click="selectedTransaction = null" class="p-2 bg-slate-100 dark:bg-zinc-800 rounded-xl text-slate-700 dark:text-zinc-200 flex items-center gap-1.5 text-xs font-bold">
                    <ArrowLeft class="h-4 w-4" /> Kembali ke Daftar
                </button>
            </div>

            <div v-if="!selectedTransaction" class="flex-1 flex flex-col items-center justify-center text-slate-400 p-8 space-y-2">
                <FileText class="h-12 w-12 text-slate-300 dark:text-zinc-700" />
                <p class="text-xs font-medium text-center">Pilih transaksi di sebelah kiri untuk melihat rincian.</p>
            </div>

            <div v-else class="flex-1 h-full overflow-y-auto p-4 sm:p-6 md:p-8 custom-scrollbar">
                <div class="w-full max-w-2xl mx-auto bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200/80 dark:border-zinc-800 shadow-xl overflow-hidden flex flex-col mb-12">

                    <!-- TOMBOL AKSI UTAMA DI ATAS -->
                    <div class="p-4 sm:p-5 bg-slate-50 dark:bg-zinc-900/90 border-b border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row items-center gap-3 shrink-0 shadow-2xs">
                        <button
                            @click="handlePrintCopyReceipt"
                            class="w-full sm:flex-1 py-3 bg-white dark:bg-zinc-800 hover:bg-slate-100 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold rounded-xl shadow-2xs transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <Printer class="h-4 w-4 text-primary" /> Cetak Salinan Struk
                        </button>

                        <button
                            v-if="selectedTransaction && selectedTransaction.status !== 'paid'"
                            @click="openPaymentModal(selectedTransaction)"
                            class="w-full sm:flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 active:scale-95 cursor-pointer"
                        >
                            <DollarSign class="h-4 w-4" /> Bayar Sekarang
                        </button>
                    </div>

                    <div class="p-6 sm:p-8 md:p-10 space-y-6 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 bg-slate-50 dark:bg-zinc-800/40 p-4 rounded-2xl border border-slate-100 dark:border-zinc-800 text-xs">

                            <div class="grid grid-cols-[80px_1fr] items-center">
                                <span class="text-slate-400 font-semibold uppercase text-[10px]">Outlet</span>
                                <span class="font-bold text-slate-800 dark:text-zinc-200 truncate">: {{ selectedTransaction.outlet?.name || '-' }}</span>
                            </div>

                            <div class="grid grid-cols-[80px_1fr] items-center">
                                <span class="text-slate-400 font-semibold uppercase text-[10px]">Pelanggan</span>
                                <span class="font-bold text-slate-800 dark:text-zinc-200 truncate">: {{ selectedTransaction.customer_name || 'Pelanggan Umum' }}</span>
                            </div>

                            <div class="grid grid-cols-[80px_1fr] items-center">
                                <span class="text-slate-400 font-semibold uppercase text-[10px]">Invoice</span>
                                <span class="font-extrabold text-slate-800 dark:text-zinc-200 font-mono truncate">: {{ selectedTransaction.order_number }}</span>
                            </div>

                            <div class="grid grid-cols-[80px_1fr] items-center">
                                <span class="text-slate-400 font-semibold uppercase text-[10px]">Waktu</span>
                                <span class="font-medium text-slate-800 dark:text-zinc-200 truncate">: {{ formatDate(selectedTransaction.created_at ?? '') }}</span>
                            </div>

                            <div class="grid grid-cols-[80px_1fr] items-center">
                                <span class="text-slate-400 font-semibold uppercase text-[10px]">Metode</span>
                                <span class="font-bold text-slate-800 dark:text-zinc-200 uppercase truncate">: {{ selectedTransaction.payment_method }}</span>
                            </div>

                            <div class="grid grid-cols-[80px_1fr] items-center">
                                <span class="text-slate-400 font-semibold uppercase text-[10px]">Tipe</span>
                                <span class="font-bold text-slate-800 dark:text-zinc-200 truncate">
                                    : <span :class="selectedTransaction.is_self_order ? 'text-blue-600 dark:text-blue-400' : ''">
                                        {{ selectedTransaction.is_self_order ? 'Self Order' : 'Kasir POS' }}
                                    </span>
                                </span>
                            </div>

                            <div class="sm:col-span-2 pt-2.5 mt-1 border-t border-slate-200/60 dark:border-zinc-700 flex items-center justify-between">
                                <span class="text-slate-400 font-semibold uppercase text-[10px]">Status Pembayaran</span>
                                <span :class="['px-3 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase', selectedTransaction.status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200']">
                                    {{ selectedTransaction.status === 'paid' ? '✓ LUNAS' : '⏳ BELUM DIBAYAR' }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <span class="text-[11px] font-black tracking-wider text-slate-400 uppercase block">Rincian Item Pesanan</span>
                            <div class="divide-y divide-slate-100 dark:divide-zinc-800/60 border-y border-slate-100 dark:border-zinc-800">
                                <div v-for="item in selectedTransaction.items" :key="item.id" class="py-3 flex items-center justify-between">
                                    <div class="space-y-0.5 pr-4">
                                        <h4 class="font-bold text-slate-800 dark:text-zinc-200 text-xs">{{ item.menu?.name || item.name || 'Item POS' }}</h4>
                                        <span class="text-[11px] text-slate-400 font-medium">Rp {{ Number(item.price).toLocaleString('id-ID') }} × {{ item.quantity || item.qty || 1 }}</span>
                                    </div>
                                    <span class="font-extrabold font-mono text-slate-900 dark:text-zinc-50 text-xs">Rp {{ Number(item.subtotal || (item.price * (item.quantity || item.qty || 1))).toLocaleString('id-ID') }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedTransaction.notes" class="bg-amber-50/60 dark:bg-amber-950/20 p-3.5 rounded-2xl border border-amber-200/60">
                            <span class="font-bold text-amber-700 dark:text-amber-400 block mb-0.5 text-[11px]">📝 Catatan:</span>
                            <p class="text-slate-600 dark:text-zinc-300 font-medium">{{ selectedTransaction.notes }}</p>
                        </div>

                        <div class="space-y-2 pt-2 border-t border-dashed border-slate-200 dark:border-zinc-800 pb-4">
                            <div class="flex justify-between text-slate-500 font-medium"><span>Subtotal</span><span>Rp {{ Number(selectedTransaction.subtotal || selectedTransaction.final_total).toLocaleString('id-ID') }}</span></div>
                            <div v-if="selectedTransaction.discount && selectedTransaction.discount > 0" class="flex justify-between text-red-500 font-medium"><span>Diskon</span><span>-Rp {{ Number(selectedTransaction.discount).toLocaleString('id-ID') }}</span></div>
                            <div class="pt-3 flex justify-between items-center text-sm font-bold border-t border-slate-200 dark:border-zinc-800">
                                <span class="text-slate-900 dark:text-zinc-50 font-black text-sm">Total Tagihan Bersih</span>
                                <span class="text-base sm:text-lg font-black text-primary font-mono">Rp {{ Number(selectedTransaction.final_total).toLocaleString('id-ID') }}</span>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- PAYMENT MODAL COMPONENT (Reused from POS)                 -->
        <!-- ========================================================= -->
        <PaymentModal
            :is-payment-modal-open="isPaymentModalOpen"
            :is-qris-modal-open="isQrisModalOpen"
            :is-success-modal-open="isSuccessModalOpen"
            :final-total="Number(selectedTransaction?.final_total || 0)"
            :is-generating-qris="isGeneratingQris"
            v-model:customer-name="modalCustomerName"
            :discount-input="0"
            v-model:amount-paid-input="amountPaidInput"
            :qris-data="qrisData"
            :qris-payment-status="qrisPaymentStatus"
            :payment-status="paymentStatus"
            :formatted-countdown="formattedCountdown"
            @close-payment-modal="closePaymentModal"
            @close-qris-modal="closeQrisModal"
            @close-success-modal="closeSuccessModal"
            @submit-cash="processPayment"
            @handle-qris-checkout="handleQrisCheckout"
            @handle-print-receipt="handlePrintCashierReceipt(selectedTransaction)"
        />

    </div>
</template>
