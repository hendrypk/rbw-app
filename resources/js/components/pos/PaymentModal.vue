<script setup lang="ts">
import { ref } from 'vue';
import { X } from '@lucide/vue';
import QrcodeVue from 'qrcode.vue';

// Menerima data dari Index.vue (Tambahkan properti pendukung diskon & voucher jika diperlukan)
defineProps<{
    isPaymentModalOpen: boolean;
    isQrisModalOpen: boolean;
    isSuccessModalOpen: boolean;
    finalTotal: number;
    isGeneratingQris: boolean;
    customerName: string;
    discountInput: number;
    amountPaidInput: number;
    qrisData: {
        invoiceNo: string;
        referenceNo: string;
        qrContent: string;
    };
    qrisPaymentStatus: string;
    paymentStatus: string;
    formattedCountdown: string;
    appliedVoucher?: any; // ⬅️ Tambahkan props opsional untuk cek voucher aktif
}>();

// Mengirimkan event kembali ke Index.vue
const emit = defineEmits([
    'update:customerName',
    'update:discountInput',
    'update:amountPaidInput',
    'closePaymentModal',
    'closeQrisModal',
    'closeSuccessModal',
    'submitCash',
    'handleQrisCheckout',
    'handlePrintReceipt',
    'handlePrintAll',
]);

// State lokal khusus untuk pilihan tab metode pembayaran di dalam modal (Tunai / QRIS)
const paymentMethod = ref<'cash' | 'qris'>('cash');
</script>

<template>
    <div>
        <!-- ========================================================= -->
        <!-- 1. MODAL PEMBAYARAN UTAMA                                 -->
        <!-- ========================================================= -->
        <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-md">
            <div class="bg-white dark:bg-zinc-950 w-full max-w-xl rounded-[2.5rem] border border-slate-200/80 dark:border-zinc-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                
                <!-- Header -->
                <div class="p-6 pb-4 flex justify-between items-center border-b border-slate-100 dark:border-zinc-900">
                    <div>
                        <h4 class="font-extrabold text-base text-slate-900 dark:text-zinc-100">Konfirmasi Pembayaran</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Pilih metode dan pastikan nominal tepat</p>
                    </div>
                    <button @click="emit('closePaymentModal')" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-zinc-900 flex items-center justify-center text-slate-500 hover:bg-slate-200 dark:hover:bg-zinc-800 transition-colors cursor-pointer">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-5">
                    <!-- Total Tagihan -->
                    <div class="p-5 bg-slate-50 dark:bg-zinc-900/60 rounded-2xl flex items-center justify-between border border-slate-100 dark:border-zinc-800">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Total Tagihan</span>
                            <span v-if="appliedVoucher" class="text-xs text-emerald-600 dark:text-emerald-400 font-bold mt-1 block">✨ Diskon Voucher ({{ appliedVoucher.code }})</span>
                        </div>
                        <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">Rp {{ finalTotal.toLocaleString('id-ID') }}</span>
                    </div>

                    <!-- Pilihan Metode -->
                    <div class="grid grid-cols-2 gap-2 p-1.5 bg-slate-100 dark:bg-zinc-900 rounded-2xl">
                        <button 
                            @click="paymentMethod = 'cash'; emit('update:amountPaidInput', finalTotal)"
                            type="button"
                            :class="['py-3.5 text-sm font-extrabold rounded-xl transition-all cursor-pointer', paymentMethod === 'cash' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-400 hover:text-slate-700']"
                        >
                            Tunai (Cash)
                        </button>
                        <button 
                            @click="paymentMethod = 'qris'"
                            type="button"
                            :class="['py-3.5 text-sm font-extrabold rounded-xl transition-all cursor-pointer', paymentMethod === 'qris' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-400 hover:text-slate-700']"
                        >
                            QRIS
                        </button>
                    </div>

                    <!-- Panel Tunai -->
                    <div v-if="paymentMethod === 'cash'" class="space-y-4">
                        <!-- Quick Cash Buttons (Pas aktif secara default) -->
                        <div class="grid grid-cols-4 gap-2 text-sm font-bold">
                            <button 
                                @click="emit('update:amountPaidInput', finalTotal)" 
                                type="button" 
                                :class="[
                                    'py-3 border rounded-xl transition-all cursor-pointer active:scale-95',
                                    amountPaidInput === finalTotal 
                                        ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 border-transparent shadow-sm' 
                                        : 'bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 border-slate-200/60 dark:border-zinc-800'
                                ]"
                            >
                                Pas
                            </button>
                            <button @click="emit('update:amountPaidInput', 25000)" type="button" class="py-3 bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 border border-slate-200/60 dark:border-zinc-800 rounded-xl transition-all cursor-pointer active:scale-95">25k</button>
                            <button @click="emit('update:amountPaidInput', 50000)" type="button" class="py-3 bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 border border-slate-200/60 dark:border-zinc-800 rounded-xl transition-all cursor-pointer active:scale-95">50k</button>
                            <button @click="emit('update:amountPaidInput', 100000)" type="button" class="py-3 bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 border border-slate-200/60 dark:border-zinc-800 rounded-xl transition-all cursor-pointer active:scale-95">100k</button>
                        </div>
                        
                        <!-- Input Nominal Bayar -->
                        <div class="flex items-center justify-between gap-4 bg-slate-50/50 dark:bg-zinc-900/30 p-3 rounded-2xl border border-slate-100 dark:border-zinc-800">
                            <span class="text-sm font-bold text-slate-600 dark:text-zinc-300">Nominal Diterima</span>
                            <input 
                                :value="amountPaidInput"
                                @input="emit('update:amountPaidInput', Number(($event.target as HTMLInputElement).value))"
                                type="number" 
                                class="w-48 text-right font-black px-4 py-3 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl font-mono text-lg focus:outline-none focus:border-primary" 
                            />
                        </div>
                        
                        <!-- Kembalian -->
                        <div v-if="amountPaidInput >= finalTotal" class="flex justify-between items-center text-sm font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3.5 rounded-2xl border border-emerald-100 dark:border-emerald-900/50">
                            <span>Kembalian</span>
                            <span class="font-mono text-lg font-black">Rp {{ (amountPaidInput - finalTotal).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-6 pt-0 flex gap-3">
                    <button 
                        v-if="paymentMethod === 'qris'"
                        @click="emit('handleQrisCheckout')" 
                        :disabled="isGeneratingQris"
                        type="button"
                        class="w-full py-4 bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-zinc-200 dark:text-slate-900 text-white font-black rounded-2xl text-base shadow-lg flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 transition-all active:scale-[0.99]"
                    >
                        <span v-if="isGeneratingQris" class="w-5 h-5 border-2 border-current/30 border-t-current rounded-full animate-spin"></span>
                        {{ isGeneratingQris ? 'Memproses QRIS...' : 'Generate QRIS' }}
                    </button>

                    <button 
                        v-else
                        @click="emit('submitCash')" 
                        type="button"
                        class="w-full py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-2xl text-base shadow-lg cursor-pointer transition-all active:scale-[0.99]"
                    >
                        Proses Pembayaran Tunai
                    </button>
                </div>

            </div>
        </div>

        <!-- ========================================================= -->
        <!-- 2. QRIS MODAL                                             -->
        <!-- ========================================================= -->
        <div v-if="isQrisModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-md">
            <div class="bg-white dark:bg-zinc-950 w-full max-w-xl rounded-[2.5rem] border border-slate-200/80 dark:border-zinc-800 shadow-2xl overflow-hidden p-8 text-center space-y-4 animate-in fade-in zoom-in-95 duration-200">
                
                <div class="space-y-1">
                    <div class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Total Tagihan</div>
                    <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">Rp {{ finalTotal.toLocaleString('id-ID') }}</div>
                    <div class="text-[11px] font-mono text-rose-500 font-extrabold">Batas Waktu: {{ formattedCountdown }}</div>
                </div>

                <!-- QRIS Diperbesar Maksimal -->
                <div class="p-6 bg-white inline-block rounded-[2.5rem] shadow-md border border-slate-100">
                    <qrcode-vue :value="qrisData.qrContent" :size="380" level="H" />
                </div>

                <!-- Tulisan Menunggu Lebih Kecil & Ramping -->
                <div class="py-2 px-3 bg-slate-50 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 rounded-xl text-xs font-semibold inline-block animate-pulse">
                    Menunggu pembayaran...
                </div>

                <!-- Tombol Lebih Kecil -->
                <div class="pt-1">
                    <button 
                        @click="emit('closeQrisModal')" 
                        class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-900 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs cursor-pointer transition-all active:scale-95"
                    >
                        Simpan & Bayar Nanti
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- 3. SUCCESS MODAL                                          -->
        <!-- ========================================================= -->
        <div v-if="isSuccessModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-md">
            <div class="bg-white dark:bg-zinc-950 w-full max-w-md rounded-[2.5rem] border border-slate-200/80 dark:border-zinc-800 shadow-2xl overflow-hidden p-8 text-center space-y-5 animate-in fade-in zoom-in-95 duration-200">
                <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/40 rounded-full flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-2xl mx-auto border border-emerald-100 dark:border-emerald-900/50">✓</div>
                <div class="space-y-1.5">
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-zinc-50">Pembayaran Berhasil</h3>
                    <p class="text-sm text-slate-400 font-mono font-bold">Rp {{ finalTotal.toLocaleString('id-ID') }}</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button @click="emit('handlePrintReceipt')" class="flex-1 py-3.5 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-900 dark:hover:bg-zinc-800 text-slate-800 dark:text-zinc-200 font-bold rounded-2xl text-sm cursor-pointer transition-colors">
                        Cetak Struk
                    </button>
                    <button @click="emit('closeSuccessModal')" class="flex-1 py-3.5 bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-zinc-200 dark:text-slate-900 text-white font-extrabold rounded-2xl text-sm cursor-pointer transition-all shadow-md">
                        Transaksi Baru
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>