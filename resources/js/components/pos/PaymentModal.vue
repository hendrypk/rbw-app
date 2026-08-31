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
        <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl border border-slate-100 dark:border-zinc-800 shadow-2xl overflow-hidden">
                
                <!-- Header -->
                <div class="p-4 px-6 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center">
                    <h4 class="font-extrabold text-sm text-slate-900 dark:text-zinc-100 uppercase tracking-wider">Pilih Pembayaran</h4>
                    <button @click="emit('closePaymentModal')" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">
                    <!-- Total Tagihan -->
                    <div class="p-4 bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase tracking-wider opacity-75 font-bold block">Total Tagihan</span>
                            <span v-if="appliedVoucher" class="text-[10px] text-emerald-400 font-bold">✨ {{ appliedVoucher.code }}</span>
                        </div>
                        <span class="text-lg font-black font-mono">Rp {{ finalTotal.toLocaleString('id-ID') }}</span>
                    </div>

                    <!-- Pilihan Metode (Tabs) -->
                    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 dark:bg-zinc-800 rounded-2xl">
                        <button 
                            @click="paymentMethod = 'cash'"
                            type="button"
                            :class="['py-2.5 text-xs font-black rounded-xl uppercase transition-all cursor-pointer', paymentMethod === 'cash' ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-400']"
                        >
                            Tunai
                        </button>
                        <button 
                            @click="paymentMethod = 'qris'"
                            type="button"
                            :class="['py-2.5 text-xs font-black rounded-xl uppercase transition-all cursor-pointer', paymentMethod === 'qris' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-400']"
                        >
                            QRIS
                        </button>
                    </div>

                    <!-- Panel Tunai -->
                    <div v-if="paymentMethod === 'cash'" class="space-y-3 pt-1">
                        <div class="grid grid-cols-4 gap-1.5 text-[11px]">
                            <button @click="emit('update:amountPaidInput', finalTotal)" type="button" class="py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold cursor-pointer">Pas</button>
                            <button @click="emit('update:amountPaidInput', 25000)" type="button" class="py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold cursor-pointer">25k</button>
                            <button @click="emit('update:amountPaidInput', 50000)" type="button" class="py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold cursor-pointer">50k</button>
                            <button @click="emit('update:amountPaidInput', 100000)" type="button" class="py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold cursor-pointer">100k</button>
                        </div>
                        
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="text-slate-500 font-semibold">Bayar:</span>
                            <input 
                                :value="amountPaidInput"
                                @input="emit('update:amountPaidInput', Number(($event.target as HTMLInputElement).value))"
                                type="number" 
                                class="w-36 text-right font-black px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-mono text-xs" 
                            />
                        </div>
                        
                        <div v-if="amountPaidInput >= finalTotal" class="flex justify-between items-center text-xs text-emerald-600 font-bold bg-emerald-50 dark:bg-emerald-950/30 p-2.5 rounded-xl">
                            <span>Kembalian:</span>
                            <span class="font-mono font-black">Rp {{ (amountPaidInput - finalTotal).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/50 flex gap-2">
                    <button @click="emit('closePaymentModal')" type="button" class="flex-1 py-2.5 bg-white border border-slate-200 text-slate-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300 font-bold rounded-xl text-xs cursor-pointer">
                        Batal
                    </button>
                    
                    <button 
                        v-if="paymentMethod === 'qris'"
                        @click="emit('handleQrisCheckout')" 
                        :disabled="isGeneratingQris"
                        type="button"
                        class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-xs shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                        <span v-if="isGeneratingQris" class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                        {{ isGeneratingQris ? 'Proses...' : 'Generate QRIS' }}
                    </button>

                    <button 
                        v-else
                        @click="emit('submitCash')" 
                        type="button"
                        class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-md cursor-pointer"
                    >
                        Bayar Tunai
                    </button>
                </div>

            </div>
        </div>

        <!-- ========================================================= -->
        <!-- 2. QRIS MODAL                                             -->
        <!-- ========================================================= -->
        <div v-if="isQrisModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-sm rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 text-center space-y-4">
                <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-zinc-800">
                    <h4 class="font-black text-xs text-slate-800 dark:text-zinc-100 uppercase tracking-widest">Scan QRIS</h4>
                    <button @click="emit('closeQrisModal')" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                
                <div class="space-y-1">
                    <span class="text-xs text-slate-400">Total: <strong class="text-slate-900 dark:text-white font-mono">Rp {{ finalTotal.toLocaleString('id-ID') }}</strong></span>
                    <div class="text-[10px] font-mono text-red-500 font-bold">Batas Waktu: {{ formattedCountdown }}</div>
                </div>

                <div class="p-3 bg-white inline-block rounded-2xl shadow-sm border border-slate-100">
                    <qrcode-vue :value="qrisData.qrContent" :size="220" level="H" />
                </div>

                <div class="py-2 px-4 bg-amber-50 text-amber-600 rounded-xl text-[11px] font-bold animate-pulse">
                    Menunggu Pembayaran...
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- 3. SUCCESS MODAL                                          -->
        <!-- ========================================================= -->
        <div v-if="isSuccessModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 w-full max-w-sm rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-500 text-3xl mx-auto">✓</div>
                <div class="space-y-1">
                    <h3 class="font-black text-base text-slate-900 dark:text-zinc-50">Pembayaran Sukses!</h3>
                    <p class="text-xs text-slate-500">Rp {{ finalTotal.toLocaleString('id-ID') }} berhasil diproses.</p>
                </div>
                <div class="flex gap-2 pt-2">
                    <button @click="emit('handlePrintReceipt')" class="flex-1 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs shadow-md cursor-pointer">
                        🖨️ Cetak Struk
                    </button>
                    <button @click="emit('closeSuccessModal')" class="flex-1 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl text-xs cursor-pointer">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>