<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useAccount } from '@/composables/useAccount';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import DatePicker from '@/components/pos/DatePicker.vue';

const props = defineProps<{ 
    show: boolean, 
    po: any 
}>();

const emit = defineEmits(['close', 'saved']);

const { accounts, fetchAccounts } = useAccount();

const processing = ref(false);
const showPaymentForm = ref(false);
const errors = ref<Record<string, any>>({});
const activeTab = ref<'items' | 'payments'>('items');

const currentPo = ref<any>(null);

watch(() => props.po, (newVal) => {
    if (newVal) {
        currentPo.value = JSON.parse(JSON.stringify(newVal));
    }
}, { immediate: true, deep: true });

const getTodayLocal = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const form = ref({
    payment_account_id: '',
    amount: 0,
    payment_date: getTodayLocal(),
});

const cashBankAccounts = computed(() => {
    return accounts.value.filter((acc: any) => acc.category === '1' || acc.category === 1);
});

const remainingDebt = computed(() => {
    if (!currentPo.value) return 0;
    return Math.max(0, Number(currentPo.value.total_amount) - Number(currentPo.value.total_payment));
});

const getPaymentStatusBadge = (status: string) => {
    if (status === 'paid') return 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400';
    if (status === 'partial') return 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400';
    return 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400';
};

const getStatusBadge = (status: string) => {
    return status === 'received' 
        ? 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400' 
        : 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400';
};

watch(() => showPaymentForm.value, (isOpen) => {
    if (isOpen) {
        fetchAccounts();
        form.value.amount = remainingDebt.value;
        form.value.payment_account_id = '';
        form.value.payment_date = getTodayLocal();
        errors.value = {};
    }
});

const submitPayment = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const response = await axios.post(`/api/purchase-orders/${currentPo.value.id}/pay-order`, form.value);
        currentPo.value = response.data.data;
        emit('saved', currentPo.value); 
        showPaymentForm.value = false;
        activeTab.value = 'payments';
    } catch (e: any) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors;
        }
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/60 backdrop-blur-md">
        <div class="w-full max-w-4xl bg-white dark:bg-zinc-950 rounded-[2.5rem] shadow-2xl border border-slate-200/80 dark:border-zinc-800 flex flex-col max-h-[92vh] overflow-hidden text-slate-900 dark:text-zinc-50 animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Header -->
            <div class="px-6 py-5 border-b border-slate-100 dark:border-zinc-900 flex items-center justify-between shrink-0">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base sm:text-lg font-bold tracking-tight">Detail PO: {{ currentPo?.po_number }}</h2>
                        <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-medium tracking-wide uppercase', getStatusBadge(currentPo?.status)]">
                            Logistik: {{ currentPo?.status }}
                        </span>
                        <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-medium tracking-wide uppercase', getPaymentStatusBadge(currentPo?.payment_status)]">
                            Keuangan: {{ currentPo?.payment_status }}
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Tanggal Order: {{ currentPo?.order_date }}</p>
                </div>
                <button type="button" @click="$emit('close')" class="size-8 rounded-full bg-slate-100 dark:bg-zinc-900 flex items-center justify-center text-slate-500 hover:bg-slate-200 transition-colors cursor-pointer">
                    ✕
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="p-6 overflow-y-auto space-y-6 flex-1">
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="bg-slate-50/50 dark:bg-zinc-900/50 p-4 rounded-2xl border border-slate-100 dark:border-zinc-800/60">
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Informasi Supplier</span>
                        <p class="font-bold text-sm text-slate-900 dark:text-zinc-100">{{ currentPo?.supplier?.name }}</p>
                    </div>
                    <div class="bg-slate-50/50 dark:bg-zinc-900/50 p-4 rounded-2xl border border-slate-100 dark:border-zinc-800/60 text-right">
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Ringkasan Keuangan</span>
                        <div class="space-y-0.5 font-medium">
                            <p class="text-slate-500">Terbayar: <span class="text-slate-900 dark:text-zinc-100 font-mono font-bold">Rp {{ Number(currentPo?.total_payment || 0).toLocaleString('id-ID') }}</span></p>
                            <p v-if="remainingDebt > 0" class="text-rose-500 font-semibold">Sisa Utang: <span class="font-mono">Rp {{ remainingDebt.toLocaleString('id-ID') }}</span></p>
                            <p v-else class="text-emerald-500 font-semibold font-mono">Lunas</p>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="rounded-2xl border border-slate-100 dark:border-zinc-800 overflow-hidden text-xs bg-card">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/70 dark:bg-zinc-900/70 text-slate-400 font-semibold border-b border-slate-100 dark:border-zinc-800">
                            <tr>
                                <th class="px-4 py-3">Nama Material / Bahan Baku</th>
                                <th class="px-4 py-3 text-right w-24">Qty</th>
                                <th class="px-4 py-3 text-right w-36">Harga Satuan</th>
                                <th class="px-4 py-3 text-right w-36">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60">
                            <tr v-for="item in currentPo?.items" :key="item.id" class="hover:bg-slate-50/50 dark:hover:bg-zinc-900/30 transition-colors">
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-zinc-100">{{ item.raw_material?.name }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-zinc-400">{{ Number(item.qty).toLocaleString('id-ID') }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-zinc-400">Rp {{ Number(item.unit_price).toLocaleString('id-ID') }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-zinc-100">Rp {{ Number(item.subtotal).toLocaleString('id-ID') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Segmented Tabs -->
                <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-zinc-900 rounded-2xl w-fit text-xs font-semibold">
                    <button 
                        type="button" 
                        @click="activeTab = 'items'" 
                        :class="['px-4 py-2 rounded-xl transition-all cursor-pointer', activeTab === 'items' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-zinc-100 shadow-sm' : 'text-slate-500 hover:text-slate-900']"
                    >
                        Catatan & Ringkasan
                    </button>
                    <button 
                        type="button" 
                        @click="activeTab = 'payments'" 
                        :class="['px-4 py-2 rounded-xl transition-all flex items-center gap-1.5 cursor-pointer', activeTab === 'payments' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-zinc-100 shadow-sm' : 'text-slate-500 hover:text-slate-900']"
                    >
                        Riwayat Pembayaran Jurnal
                        <span class="px-1.5 py-0.2 rounded-full bg-slate-200/60 dark:bg-zinc-700 text-[10px] font-mono">
                            {{ currentPo?.journal_entries?.length || 0 }}
                        </span>
                    </button>
                </div>

                <!-- Tab Content 1 -->
                <div v-show="activeTab === 'items'" class="flex justify-between items-start bg-slate-50/50 dark:bg-zinc-900/30 p-4 rounded-2xl border border-slate-100 dark:border-zinc-800/60 text-xs">
                    <div class="max-w-md">
                        <span class="font-semibold text-slate-400 uppercase tracking-wider block mb-1">Catatan Internal</span>
                        <p class="text-slate-600 dark:text-zinc-400 italic">{{ currentPo?.notes || 'Tidak ada catatan tambahan.' }}</p>
                    </div>
                    <div class="text-right space-y-0.5">
                        <span class="font-semibold text-slate-400 uppercase tracking-wider block">Total Tagihan Bruto</span>
                        <span class="text-lg font-black text-slate-900 dark:text-zinc-100 font-mono">Rp {{ Number(currentPo?.total_amount || 0).toLocaleString('id-ID') }}</span>
                    </div>
                </div>

                <!-- Tab Content 2 -->
                <div v-show="activeTab === 'payments'">
                    <div v-if="currentPo?.journal_entries?.length > 0" class="rounded-2xl border border-slate-100 dark:border-zinc-800 overflow-hidden text-xs bg-card">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50/70 dark:bg-zinc-900/70 text-slate-400 font-semibold border-b border-slate-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Keterangan Jurnal</th>
                                    <th class="px-4 py-3 text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60">
                                <tr v-for="entry in currentPo.journal_entries" :key="entry.id" class="hover:bg-slate-50/50 dark:hover:bg-zinc-900/30 transition-colors">
                                    <td class="px-4 py-3 font-mono text-slate-600 dark:text-zinc-400">{{ entry.entry_date }}</td>
                                    <td class="px-4 py-3 text-slate-900 dark:text-zinc-100 font-medium">{{ entry.description }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-zinc-100">Rp {{ Number(entry.total_amount).toLocaleString('id-ID') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 rounded-2xl border border-dashed border-slate-200 dark:border-zinc-800 text-xs text-slate-400 font-medium">
                        Belum ada riwayat transaksi jurnal pembayaran yang tercatat.
                    </div>
                </div>

                <!-- Payment Form Card -->
                <div v-if="showPaymentForm" class="p-5 rounded-3xl bg-slate-50 dark:bg-zinc-900/80 border border-slate-200/60 dark:border-zinc-800 space-y-4">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-zinc-100 tracking-wider">Form Pengeluaran Kas (Bayar Utang)</h4>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                        <div class="space-y-1.5">
                            <Label class="font-semibold text-slate-500">Tanggal Bayar</Label>
                            <DatePicker v-model="form.payment_date" />
                            <InputError :message="errors.payment_date?.[0]" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="font-semibold text-slate-500">Sumber Uang Keluar (Kredit)</Label>
                            <Select v-model="form.payment_account_id">
                                <SelectTrigger class="w-full h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 text-xs font-semibold">
                                    <SelectValue placeholder="Pilih Kas / Bank" />
                                </SelectTrigger>
                                <SelectContent class="rounded-2xl">
                                    <SelectItem v-for="acc in cashBankAccounts" :key="acc.id" :value="acc.id" class="text-xs font-medium">
                                        [{{ acc.code }}] {{ acc.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.payment_account_id?.[0]" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="font-semibold text-slate-500">Nominal Pembayaran (Rp)</Label>
                            <Input type="number" v-model="form.amount" class="h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 font-mono font-bold" :max="remainingDebt" />
                            <InputError :message="errors.amount?.[0]" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" size="sm" variant="ghost" @click="showPaymentForm = false" class="h-10 px-4 rounded-2xl text-xs font-semibold text-slate-500 hover:text-slate-900 cursor-pointer">Batal</Button>
                        <Button type="button" size="sm" :disabled="processing" @click="submitPayment" class="h-10 px-5 rounded-2xl text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white dark:bg-zinc-100 dark:hover:bg-zinc-200 dark:text-zinc-900 shadow-sm cursor-pointer">
                            {{ processing ? 'Memproses...' : 'Konfirmasi Pembayaran Jurnal' }}
                        </Button>
                    </div>
                </div>

            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 border-t border-slate-100 dark:border-zinc-900 flex justify-between items-center shrink-0 bg-white dark:bg-zinc-950">
                <div>
                    <Button 
                        v-if="currentPo?.status === 'received' && remainingDebt > 0 && !showPaymentForm" 
                        type="button" 
                        variant="outline" 
                        size="sm"
                        @click="showPaymentForm = true"
                        class="h-10 px-4 text-xs font-bold rounded-2xl border-slate-200 dark:border-zinc-800 cursor-pointer"
                    >
                        + Tambah Pembayaran Utang
                    </Button>
                </div>
                <div>
                    <Button variant="outline" size="sm" @click="$emit('close')" class="h-10 px-5 text-xs font-bold rounded-2xl border-slate-200 dark:border-zinc-800 cursor-pointer">Tutup</Button>
                </div>
            </div>

        </div>
    </div>
</template>