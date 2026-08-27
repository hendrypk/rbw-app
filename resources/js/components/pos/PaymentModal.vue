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
    'handlePrintReceipt'
]);

// State lokal khusus untuk pilihan tab metode pembayaran di dalam modal (Tunai / QRIS)
const paymentMethod = ref<'cash' | 'qris'>('cash');
</script>

<template>
    <div>
        <!-- ========================================================= -->
        <!-- 1. MODAL PEMBAYARAN UTAMA                                 -->
        <!-- ========================================================= -->
        <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/85 backdrop-blur-sm transition-opacity">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col">
                
                <!-- Header -->
                <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/40">
                    <div>
                        <h4 class="font-black text-base text-slate-900 dark:text-zinc-100 tracking-tight">Penyelesaian Transaksi</h4>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">Pilih metode pembayaran yang digunakan</p>
                    </div>
                    <button @click="emit('closePaymentModal')" class="p-1.5 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-5 overflow-y-auto max-h-[75vh] custom-scrollbar">
                    
                    <!-- Total Tagihan -->
                    <div class="p-4 bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 rounded-2xl flex items-center justify-between shadow-sm">
                        <div class="space-y-0.5">
                            <span class="text-xs font-semibold opacity-80 uppercase tracking-wider block">Total Tagihan Bersih:</span>
                            <span v-if="appliedVoucher" class="text-[10px] text-emerald-400 dark:text-emerald-600 font-bold block">
                                ✨ Promo {{ appliedVoucher.code }} diterapkan
                            </span>
                        </div>
                        <span class="text-xl font-black tracking-tight font-mono">Rp {{ finalTotal.toLocaleString('id-ID') }}</span>
                    </div>

                    <!-- Pilihan Metode -->
                    <div class="space-y-2">
                        <span class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider block">Metode Pembayaran</span>
                        <div class="grid grid-cols-2 gap-3">
                            <button 
                                @click="paymentMethod = 'cash'"
                                type="button"
                                :class="[
                                    'py-3.5 px-4 text-xs font-black rounded-2xl border uppercase transition-all flex items-center justify-center gap-2 cursor-pointer', 
                                    paymentMethod === 'cash' 
                                        ? 'bg-slate-900 border-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-md scale-[1.02]' 
                                        : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400'
                                ]"
                            >
                                Tunai
                            </button>
                            <button 
                                @click="paymentMethod = 'qris'"
                                type="button"
                                :class="[
                                    'py-3.5 px-4 text-xs font-black rounded-2xl border uppercase transition-all flex items-center justify-center gap-2 cursor-pointer', 
                                    paymentMethod === 'qris' 
                                        ? 'bg-blue-600 border-blue-600 text-white shadow-md scale-[1.02]' 
                                        : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400'
                                ]"
                            >
                                QRIS
                            </button>
                        </div>
                    </div>

                    <!-- Kalkulator Tunai -->
                    <div v-if="paymentMethod === 'cash'" class="p-4 bg-slate-50 dark:bg-zinc-800/50 rounded-2xl space-y-3 border border-slate-200/60 dark:border-zinc-700">
                        <div class="grid grid-cols-4 gap-2 text-xs">
                            <button @click="emit('update:amountPaidInput', finalTotal)" type="button" class="py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold text-slate-700 dark:text-zinc-200 cursor-pointer">Uang Pas</button>
                            <button @click="emit('update:amountPaidInput', 25000)" type="button" class="py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold text-slate-700 dark:text-zinc-200 cursor-pointer">25k</button>
                            <button @click="emit('update:amountPaidInput', 50000)" type="button" class="py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold text-slate-700 dark:text-zinc-200 cursor-pointer">50k</button>
                            <button @click="emit('update:amountPaidInput', 100000)" type="button" class="py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold text-slate-700 dark:text-zinc-200 cursor-pointer">100k</button>
                        </div>
                        
                        <div class="flex items-center justify-between gap-3 text-xs pt-1">
                            <span class="font-semibold text-slate-500 dark:text-zinc-400">Nominal Diterima:</span>
                            <input 
                                :value="amountPaidInput"
                                @input="emit('update:amountPaidInput', Number(($event.target as HTMLInputElement).value))"
                                type="number" 
                                class="w-40 text-right font-black px-3 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white font-mono text-sm" 
                            />
                        </div>
                        
                        <div v-if="amountPaidInput >= finalTotal" class="flex justify-between items-center text-xs text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-950/30 p-3 rounded-xl border border-emerald-100">
                            <span>Kembalian:</span>
                            <span class="font-mono font-black text-sm">Rp {{ (amountPaidInput - finalTotal).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>

                    <!-- Input Nama & Diskon -->
                    <div class="space-y-3 pt-2 border-t border-slate-100 dark:border-zinc-800">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Nama Pelanggan</span>
                                <input 
                                    :value="customerName"
                                    @input="emit('update:customerName', ($event.target as HTMLInputElement).value)"
                                    type="text" 
                                    placeholder="Umum" 
                                    class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white" 
                                />
                            </div>
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Diskon (Rp)</span>
                                <input 
                                    :value="discountInput"
                                    @input="emit('update:discountInput', Number(($event.target as HTMLInputElement).value))"
                                    type="number" 
                                    placeholder="0" 
                                    class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-red-500 font-bold" 
                                />
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="p-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 grid grid-cols-2 gap-3">
                    <button @click="emit('closePaymentModal')" type="button" class="py-3 bg-white border border-slate-200 text-slate-700 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300 font-bold rounded-2xl text-xs cursor-pointer">
                        Batal
                    </button>
                    
                    <button 
                        v-if="paymentMethod === 'qris'"
                        @click="emit('handleQrisCheckout')" 
                        :disabled="isGeneratingQris"
                        type="button"
                        class="py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-2xl text-xs shadow-md flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
                    >
                        <span v-if="isGeneratingQris" class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                        {{ isGeneratingQris ? 'Memproses QR...' : 'Generate QRIS' }}
                    </button>

                    <button 
                        v-else
                        @click="emit('submitCash')" 
                        type="button"
                        class="py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-2xl text-xs shadow-md cursor-pointer"
                    >
                        Bayar Tunai
                    </button>
                </div>

            </div>
        </div>

        <!-- ========================================================= -->
        <!-- 2. QRIS MODAL                                             -->
        <!-- ========================================================= -->
        <div v-if="isQrisModalOpen" class="fixed inset-0 z-100 flex items-center justify-center p-4 bg-slate-900/90 backdrop-blur-md">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-4xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden flex flex-col">
                <div class="bg-slate-50 dark:bg-zinc-800 p-5 px-8 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                    <h4 class="font-extrabold text-lg text-slate-800 dark:text-zinc-100 uppercase tracking-widest">Scan QRIS</h4>
                    <button @click="emit('closeQrisModal')" class="p-2 bg-white dark:bg-zinc-700 rounded-full text-slate-400 border border-slate-200 dark:border-zinc-600 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-8 flex flex-col items-center">
                    <div class="w-full bg-slate-50 dark:bg-zinc-800 rounded-3xl p-6 mb-6 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-bold text-slate-500">Total Tagihan</span>
                            <span class="text-xl font-black text-slate-900 dark:text-zinc-50">Rp {{ finalTotal.toLocaleString('id-ID') }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Invoice: {{ qrisData.invoiceNo }}</span>
                            <span class="font-mono text-red-500 font-bold">⏱️ {{ formattedCountdown }}</span>
                        </div>
                    </div>
                    <div class="p-4 bg-white rounded-3xl shadow-md border-2 border-slate-100 mb-6">
                        <qrcode-vue :value="qrisData.qrContent" :size="300" level="H" />
                    </div>
                    <div class="py-2 px-5 bg-amber-50 text-amber-600 rounded-full text-xs font-bold animate-pulse">
                        Menunggu Pembayaran...
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- 3. SUCCESS MODAL                                          -->
        <!-- ========================================================= -->
        <div v-if="isSuccessModalOpen" class="fixed inset-0 z-100 flex items-center justify-center p-4 bg-slate-900/90 backdrop-blur-md">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-4xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-8 text-center space-y-6">
                <div class="w-24 h-24 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-500 text-5xl mx-auto">✓</div>
                <div class="space-y-2">
                    <h3 class="font-black text-2xl text-slate-900 dark:text-zinc-50">Pembayaran Sukses!</h3>
                    <p class="text-sm text-slate-500">Transaksi sebesar Rp {{ finalTotal.toLocaleString('id-ID') }} telah berhasil diproses.</p>
                </div>
                <div class="flex gap-3 pt-4">
                    <button @click="emit('handlePrintReceipt')" class="flex-1 py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-2xl text-sm shadow-lg cursor-pointer">
                        🖨️ Cetak Struk
                    </button>
                    <button @click="emit('closeSuccessModal')" class="flex-1 py-3.5 bg-white border border-slate-200 text-slate-700 font-black rounded-2xl text-sm cursor-pointer">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>