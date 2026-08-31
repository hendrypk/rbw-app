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
        <!-- 1. MODAL PEMBAYARAN UTAMA (Simple Clean Layout)           -->
        <!-- ========================================================= -->
        <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-xl overflow-hidden flex flex-col">
                
                <!-- Header -->
                <div class="px-5 py-4 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center">
                    <h4 class="font-bold text-sm text-slate-900 dark:text-zinc-100">Pembayaran</h4>
                    <button @click="emit('closePaymentModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 cursor-pointer">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 space-y-4">
                    
                    <!-- Total Tagihan -->
                    <div class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-zinc-800/50 rounded-xl">
                        <div>
                            <span class="text-[11px] font-medium text-slate-500 block">Total Tagihan</span>
                            <span v-if="appliedVoucher" class="text-[10px] text-emerald-600 font-semibold">
                                Voch: {{ appliedVoucher.code }}
                            </span>
                        </div>
                        <span class="text-base font-black text-slate-900 dark:text-white font-mono">Rp {{ finalTotal.toLocaleString('id-ID') }}</span>
                    </div>

                    <!-- Pilihan Metode (Segmented Control) -->
                    <div class="grid grid-cols-2 gap-1 bg-slate-100 dark:bg-zinc-800 p-1 rounded-xl">
                        <button 
                            @click="paymentMethod = 'cash'"
                            type="button"
                            :class="[
                                'py-2 text-xs font-bold rounded-lg transition-all cursor-pointer', 
                                paymentMethod === 'cash' 
                                    ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' 
                                    : 'text-slate-500 dark:text-zinc-400 hover:text-slate-900'
                            ]"
                        >
                            Tunai
                        </button>
                        <button 
                            @click="paymentMethod = 'qris'"
                            type="button"
                            :class="[
                                'py-2 text-xs font-bold rounded-lg transition-all cursor-pointer', 
                                paymentMethod === 'qris' 
                                    ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs' 
                                    : 'text-slate-500 dark:text-zinc-400 hover:text-slate-900'
                            ]"
                        >
                            QRIS
                        </button>
                    </div>

                    <!-- Kalkulator Tunai Ringkas -->
                    <div v-if="paymentMethod === 'cash'" class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-slate-500">Bayar:</span>
                            <input 
                                :value="amountPaidInput"
                                @input="emit('update:amountPaidInput', Number(($event.target as HTMLInputElement).value))"
                                type="number" 
                                placeholder="0"
                                class="flex-1 text-right font-bold px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white font-mono text-sm focus:outline-none focus:border-emerald-500" 
                            />
                        </div>

                        <!-- Shortcut Nominal -->
                        <div class="grid grid-cols-4 gap-1.5 text-xs">
                            <button @click="emit('update:amountPaidInput', finalTotal)" type="button" class="py-1.5 bg-slate-50 dark:bg-zinc-800 hover:bg-slate-100 rounded-lg font-medium text-slate-700 dark:text-zinc-300 border border-slate-200 dark:border-zinc-700 cursor-pointer">Pas</button>
                            <button @click="emit('update:amountPaidInput', 25000)" type="button" class="py-1.5 bg-slate-50 dark:bg-zinc-800 hover:bg-slate-100 rounded-lg font-medium text-slate-700 dark:text-zinc-300 border border-slate-200 dark:border-zinc-700 cursor-pointer">25k</button>
                            <button @click="emit('update:amountPaidInput', 50000)" type="button" class="py-1.5 bg-slate-50 dark:bg-zinc-800 hover:bg-slate-100 rounded-lg font-medium text-slate-700 dark:text-zinc-300 border border-slate-200 dark:border-zinc-700 cursor-pointer">50k</button>
                            <button @click="emit('update:amountPaidInput', 100000)" type="button" class="py-1.5 bg-slate-50 dark:bg-zinc-800 hover:bg-slate-100 rounded-lg font-medium text-slate-700 dark:text-zinc-300 border border-slate-200 dark:border-zinc-700 cursor-pointer">100k</button>
                        </div>
                        
                        <!-- Kembalian -->
                        <div v-if="amountPaidInput >= finalTotal" class="flex justify-between items-center text-xs text-emerald-600 dark:text-emerald-400 font-semibold px-1">
                            <span>Kembalian:</span>
                            <span class="font-mono font-bold">Rp {{ (amountPaidInput - finalTotal).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="px-5 py-3 border-t border-slate-100 dark:border-zinc-800 flex gap-2">
                    <button @click="emit('closePaymentModal')" type="button" class="px-4 py-2.5 border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-300 font-bold rounded-xl text-xs cursor-pointer hover:bg-slate-50">
                        Batal
                    </button>
                    
                    <button 
                        v-if="paymentMethod === 'qris'"
                        @click="emit('handleQrisCheckout')" 
                        :disabled="isGeneratingQris"
                        type="button"
                        class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer shadow-xs"
                    >
                        <span v-if="isGeneratingQris" class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                        {{ isGeneratingQris ? 'Memproses...' : 'Generate QRIS' }}
                    </button>

                    <button 
                        v-else
                        @click="emit('submitCash')" 
                        type="button"
                        class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs cursor-pointer shadow-xs"
                    >
                        Konfirmasi Tunai
                    </button>
                </div>

            </div>
        </div>

        <!-- ========================================================= -->
        <!-- 2. QRIS MODAL (Simple Clean Layout)                       -->
        <!-- ========================================================= -->
        <div v-if="isQrisModalOpen" class="fixed inset-0 z-100 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-sm rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-xl overflow-hidden p-6 text-center space-y-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-bold text-sm text-slate-800 dark:text-zinc-100">Scan QRIS</h4>
                    <button @click="emit('closeQrisModal')" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                
                <div class="space-y-1">
                    <span class="text-xs text-slate-400">Total Pembayaran</span>
                    <div class="text-lg font-black text-slate-900 dark:text-white font-mono">Rp {{ finalTotal.toLocaleString('id-ID') }}</div>
                </div>

                <div class="p-3 bg-white rounded-2xl shadow-xs border border-slate-100 inline-block">
                    <qrcode-vue :value="qrisData.qrContent" :size="220" level="H" />
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-500 px-2">
                    <span>Inv: {{ qrisData.invoiceNo }}</span>
                    <span class="font-mono text-amber-600 font-semibold">⏱️ {{ formattedCountdown }}</span>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- 3. SUCCESS MODAL (Simple Clean Layout)                    -->
        <!-- ========================================================= -->
        <div v-if="isSuccessModalOpen" class="fixed inset-0 z-100 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-sm rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-xl overflow-hidden p-6 text-center space-y-4">
                <div class="w-14 h-14 bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 rounded-full flex items-center justify-center text-xl font-black mx-auto">✓</div>
                
                <div class="space-y-1">
                    <h3 class="font-bold text-base text-slate-900 dark:text-zinc-50">Berhasil!</h3>
                    <p class="text-xs text-slate-500">Transaksi telah disimpan & diproses.</p>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-2">
                    <button @click="emit('handlePrintReceipt')" class="py-2.5 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 text-slate-800 dark:text-zinc-200 font-bold rounded-xl text-xs cursor-pointer">
                        🖨️ Cetak Struk
                    </button>
                    <button @click="emit('closeSuccessModal')" class="py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs cursor-pointer shadow-xs">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>