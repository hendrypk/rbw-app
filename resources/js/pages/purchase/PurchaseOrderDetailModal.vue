<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useAccount } from '@/composables/useAccount';

const props = defineProps<{ 
    show: boolean, 
    po: any 
}>();

const emit = defineEmits(['close', 'saved']);

const { accounts, fetchAccounts } = useAccount();

const processing = ref(false);
const showPaymentForm = ref(false);
const errors = ref<Record<string, any>>({});

const form = ref({
    payment_account_id: '',
    amount: 0
});

// Filter Rekening Kas & Bank
const cashBankAccounts = computed(() => {
    return accounts.value.filter((acc: any) => acc.category === '1' || acc.category === 1);
});

// Hitung Sisa Utang Aktual Berjalan
const remainingDebt = computed(() => {
    if (!props.po) return 0;
    return Math.max(0, Number(props.po.total_amount) - Number(props.po.total_payment));
});

// Format Status Bayar Warna
const getPaymentStatusColor = (status: string) => {
    if (status === 'paid') return 'bg-emerald-100 text-emerald-700';
    if (status === 'partial') return 'bg-amber-100 text-amber-700';
    return 'bg-rose-100 text-rose-700';
};

const getStatusColor = (status: string) => {
    return status === 'received' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
};

// Reset Form saat modal diakses atau tombol pembayaran ditekan
watch(() => showPaymentForm.value, (isOpen) => {
    if (isOpen) {
        fetchAccounts();
        form.value.amount = remainingDebt.value; // Default-kan isi full sisa utang
        form.value.payment_account_id = '';
        errors.value = {};
    }
});

const submitPayment = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const response = await axios.post(`/api/purchase-orders/${props.po.id}/payments`, form.value);
        emit('saved', response.data.data); // Emit data PO ter-update ke parent component
        showPaymentForm.value = false;
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
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm text-foreground">
        <div class="w-full max-w-3xl bg-card rounded-xl shadow-xl p-6 border border-border max-h-[90vh] overflow-y-auto">
            
            <div class="flex justify-between items-start mb-6 border-b pb-4">
                <div>
                    <h2 class="text-xl font-bold">Detail PO: {{ po?.po_number }}</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Tanggal Order: {{ po?.order_date }}</p>
                </div>
                <div class="flex gap-2">
                    <span :class="['px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider', getStatusColor(po?.status)]">
                        Logistik: {{ po?.status }}
                    </span>
                    <span :class="['px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider', getPaymentStatusColor(po?.payment_status)]">
                        Keuangan: {{ po?.payment_status }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6 mb-6 text-xs bg-muted/20 p-4 rounded-xl border">
                <div>
                    <h3 class="font-bold text-muted-foreground uppercase tracking-wider">Informasi Supplier</h3>
                    <p class="font-semibold text-sm mt-1 text-primary">{{ po?.supplier?.name }}</p>
                </div>
                <div class="text-right">
                    <h3 class="font-bold text-muted-foreground uppercase tracking-wider">Riwayat Pembayaran</h3>
                    <p class="mt-1 font-medium text-muted-foreground">
                        Terbayar: <span class="font-bold text-foreground font-mono">Rp {{ Number(po?.total_payment || 0).toLocaleString('id-ID') }}</span>
                    </p>
                    <p v-if="remainingDebt > 0" class="text-destructive font-semibold">
                        Sisa Utang: <span class="font-bold font-mono">Rp {{ remainingDebt.toLocaleString('id-ID') }}</span>
                    </p>
                </div>
            </div>

            <div class="border rounded-lg overflow-hidden mb-6 text-xs bg-background">
                <table class="w-full">
                    <thead class="bg-muted/50 font-semibold text-muted-foreground border-b">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Nama Material / Bahan Baku</th>
                            <th class="px-4 py-2.5 text-right w-24">Qty</th>
                            <th class="px-4 py-2.5 text-right w-36">Harga Satuan</th>
                            <th class="px-4 py-2.5 text-right w-36">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50 font-medium">
                        <tr v-for="item in po?.items" :key="item.id" class="hover:bg-muted/5">
                            <td class="px-4 py-3 font-semibold">{{ item.raw_material?.name }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ Number(item.qty).toLocaleString('id-ID') }}</td>
                            <td class="px-4 py-3 text-right font-mono">Rp {{ Number(item.unit_price).toLocaleString('id-ID') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-foreground/90">Rp {{ Number(item.subtotal).toLocaleString('id-ID') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-between items-start bg-muted/10 p-4 rounded-xl border mb-6 text-xs">
                <div class="max-w-md">
                    <span class="font-bold text-muted-foreground uppercase block mb-1">Catatan Internal:</span>
                    <p class="text-muted-foreground italic font-medium">{{ po?.notes || 'Tidak ada catatan tambahan.' }}</p>
                </div>
                <div class="text-right space-y-1">
                    <span class="font-bold text-muted-foreground uppercase block">Total Nilai Tagihan Bruto:</span>
                    <span class="text-xl font-black text-primary font-mono block">Rp {{ Number(po?.total_amount).toLocaleString('id-ID') }}</span>
                </div>
            </div>

            <div v-if="showPaymentForm" class="border-2 border-dashed border-blue-500/30 p-4 rounded-xl bg-blue-500/5 space-y-4 mb-6">
                <h4 class="text-xs font-bold uppercase text-blue-600 tracking-wider">Form Pengeluaran Kas (Bayar Utang)</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold">Sumber Uang Keluar (Kredit)</Label>
                        <select v-model="form.payment_account_id" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs focus:outline-none focus:ring-1">
                            <option value="">Pilih Kas / Bank</option>
                            <option v-for="acc in cashBankAccounts" :key="acc.id" :value="acc.id">
                                {{ acc.code }} - {{ acc.name }}
                            </option>
                        </select>
                        <InputError :message="errors.payment_account_id?.[0]" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold">Nominal Pembayaran (Rp)</Label>
                        <Input type="number" v-model="form.amount" class="h-9 text-xs font-mono font-bold" :max="remainingDebt" />
                        <InputError :message="errors.amount?.[0]" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" size="sm" variant="ghost" @click="showPaymentForm = false" class="h-8 text-xs">Batal</Button>
                    <Button type="button" size="sm" :disabled="processing" @click="submitPayment" class="h-8 text-xs bg-blue-600 hover:bg-blue-700 text-white">
                        {{ processing ? 'Memproses...' : 'Konfirmasi Pembayaran Jurnal' }}
                    </Button>
                </div>
            </div>

            <div class="flex justify-between items-center border-t pt-4">
                <div>
                    <Button 
                        v-if="po?.status === 'received' && remainingDebt > 0 && !showPaymentForm" 
                        type="button" 
                        variant="default" 
                        size="sm"
                        @click="showPaymentForm = true"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs h-8"
                    >
                        + Tambah Pembayaran Utang
                    </Button>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="$emit('close')" class="h-8 text-xs">Tutup</Button>
                </div>
            </div>

        </div>
    </div>
</template>