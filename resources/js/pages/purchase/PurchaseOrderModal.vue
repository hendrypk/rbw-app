<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import InputError from '@/components/InputError.vue';
import { useSuppliers } from '@/composables/useSuppliers';
import { useMaterials } from '@/composables/useMaterials';
import { useAccount } from '@/composables/useAccount';
import Select from '@/components/ui/select/Select.vue';
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue';
import SelectValue from '@/components/ui/select/SelectValue.vue';
import SelectContent from '@/components/ui/select/SelectContent.vue';
import SelectItem from '@/components/ui/select/SelectItem.vue';
import DatePicker from '@/components/pos/DatePicker.vue';

const props = defineProps<{ 
    show: boolean; 
    po?: any;
    activeOutletId?: string | null;
}>();

const emit = defineEmits(['close', 'saved']);

const currentOutletId = ref<string | null>(localStorage.getItem('active_outlet_id') || props.activeOutletId || null);

const { suppliers, fetchSuppliers } = useSuppliers();
const { materialOptions, fetchMaterialOptions } = useMaterials(currentOutletId);
const { accounts, fetchAccounts } = useAccount();

const processing = ref(false);
const errors = ref<Record<string, any>>({});

const getTodayLocal = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const form = ref({
    supplier_id: '',
    order_date: getTodayLocal(),
    payment_account_id: 'NO_ACCOUNT',
    amount_paid: 0,
    notes: '',
    items: [{ raw_material_id: '', qty: 1, unit_price: 0 }]
});

const cashBankAccounts = computed(() => accounts.value.filter((acc: any) => acc.category === '1' || acc.category === 1));
const totalItems = computed(() => form.value.items.length);
const totalQty = computed(() => form.value.items.reduce((sum, item) => sum + (parseFloat(item.qty as any) || 0), 0));
const totalAmount = computed(() => form.value.items.reduce((sum, item) => sum + (Number(item.qty) * Number(item.unit_price)), 0));
const remainingBill = computed(() => Math.max(0, totalAmount.value - (Number(form.value.amount_paid) || 0)));

const automaticStatusLabel = computed(() => {
    const paid = Number(form.value.amount_paid) || 0;
    if (form.value.payment_account_id === 'NO_ACCOUNT' || paid <= 0) return { text: 'Hutang Penuh (Unpaid)', color: 'bg-red-500/10 text-red-600 border-red-500/20' };
    if (paid >= totalAmount.value) return { text: 'Lunas Langsung (Paid)', color: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
    return { text: 'Bayar Sebagian (Partial)', color: 'bg-amber-500/10 text-amber-600 border-amber-500/20' };
});

watch(() => form.value.payment_account_id, (newAccount) => {
    if (newAccount === 'NO_ACCOUNT') form.value.amount_paid = 0;
});

watch(() => form.value.amount_paid, (newPaid) => {
    if (newPaid > totalAmount.value) form.value.amount_paid = totalAmount.value;
});

const addItem = () => form.value.items.push({ raw_material_id: '', qty: 1, unit_price: 0 });
const removeItem = (index: number) => {
    if (form.value.items.length > 1) form.value.items.splice(index, 1);
};

// Eksekusi setiap kali modal dibuka
watch(() => props.show, (newVal) => {
    if (!newVal) return;

    errors.value = {};
    currentOutletId.value = localStorage.getItem('active_outlet_id') || props.activeOutletId || null;

    // 1. ISI FORM SECARA SINKRON (Mencegah jeda/flicker)
    if (props.po) {
        form.value = {
            supplier_id: props.po.supplier_id || '',
            order_date: props.po.order_date ? props.po.order_date.split('T')[0] : getTodayLocal(),
            payment_account_id: props.po.payment_account_id || 'NO_ACCOUNT',
            amount_paid: parseFloat(props.po.total_payment || 0),
            notes: props.po.notes || '',
            items: Array.isArray(props.po.items) && props.po.items.length > 0
                ? props.po.items.map((item: any) => ({
                    raw_material_id: item.raw_material_id || '',
                    qty: parseFloat(item.qty || 1),
                    unit_price: parseFloat(item.unit_price || 0)
                }))
                : [{ raw_material_id: '', qty: 1, unit_price: 0 }]
        };
    } else {
        form.value = { 
            supplier_id: '', 
            order_date: getTodayLocal(), 
            payment_account_id: 'NO_ACCOUNT',
            amount_paid: 0,
            notes: '', 
            items: [{ raw_material_id: '', qty: 1, unit_price: 0 }] 
        };
    }

    // 2. FETCH DATA BACKGROUND (Tanpa memblokir render UI)
    const headers = currentOutletId.value && currentOutletId.value !== 'all' 
        ? { headers: { 'X-Outlet-ID': currentOutletId.value } } 
        : {};

    fetchSuppliers({ active: true }).catch(console.error);
    fetchMaterialOptions().catch(console.error);
    fetchAccounts(headers).catch(console.error);
});

const submit = async (status: string) => {
    processing.value = true;
    errors.value = {}; 
    
    const activeOutletId = localStorage.getItem('active_outlet_id');
    const payload = { 
        ...form.value, 
        payment_account_id: form.value.payment_account_id === 'NO_ACCOUNT' ? null : form.value.payment_account_id,
        outlet_id: activeOutletId && activeOutletId !== 'all' ? activeOutletId : null,
        status,
        final_total: totalAmount.value 
    };

    try {
        const config = payload.outlet_id ? { headers: { 'X-Outlet-ID': payload.outlet_id } } : {};
        if (props.po) {
            await axios.put(`/api/purchase-orders/${props.po.id}`, payload, config);
        } else {
            await axios.post('/api/purchase-orders', payload, config);
        }
        emit('saved');
        emit('close');
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
            
            <div class="px-6 py-5 border-b border-slate-100 dark:border-zinc-900 flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-base sm:text-lg font-bold tracking-tight">{{ po ? 'Edit Purchase Order' : 'Buat Purchase Order' }}</h2>
                    <p class="text-[11px] text-slate-400">Kelola pengadaan stok bahan baku dengan sistem pencatatan terintegrasi.</p>
                </div>
                <button type="button" @click="$emit('close')" class="size-8 rounded-full bg-slate-100 dark:bg-zinc-900 flex items-center justify-center text-slate-500 hover:bg-slate-200 transition-colors cursor-pointer">
                    ✕
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6">
                <form @submit.prevent class="space-y-6">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label>Supplier</Label>
                            <Select v-model="form.supplier_id">
                                <SelectTrigger class="w-full h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900 px-4 text-xs font-semibold">
                                    <SelectValue placeholder="Pilih Supplier" />
                                </SelectTrigger>
                                <SelectContent class="rounded-2xl">
                                    <SelectItem v-for="s in suppliers" :key="s.id" :value="s.id" class="text-xs font-medium">
                                        {{ s.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.supplier_id?.[0]" />
                        </div>
                        
                        <div class="space-y-1.5">
                            <Label>Tanggal Pembelian</Label>
                            <DatePicker v-model="form.order_date" />
                            <!-- <Input type="date" v-model="form.order_date" class="h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900 px-4 text-xs font-semibold [color-scheme:dark]" /> -->
                            <InputError :message="errors.order_date?.[0]" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 sm:p-5 rounded-3xl bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/60 dark:border-zinc-800/80 items-end">
                        <div class="space-y-1.5">
                            <Label>Metode Bayar (Kas/Bank)</Label>
                            <Select v-model="form.payment_account_id">
                                <SelectTrigger class="w-full h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 text-xs font-semibold">
                                    <SelectValue placeholder="Beli Tempo (Tanpa Kas)" />
                                </SelectTrigger>
                                <SelectContent class="rounded-2xl">
                                    <SelectItem value="NO_ACCOUNT" class="text-xs font-medium text-slate-400">Beli Tempo (Tanpa Kas)</SelectItem>
                                    <SelectItem v-for="acc in cashBankAccounts" :key="acc.id" :value="acc.id" class="text-xs font-medium">
                                        {{ acc.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.payment_account_id?.[0]" />
                        </div>

                        <div class="space-y-1.5" v-if="form.payment_account_id !== 'NO_ACCOUNT'">
                            <div class="flex justify-between items-center">
                                <Label>Nominal Bayar</Label>
                                <button type="button" @click="form.amount_paid = totalAmount" class="text-[10px] text-primary hover:underline font-bold cursor-pointer">Lunas</button>
                            </div>
                            <Input type="number" v-model="form.amount_paid" :max="totalAmount" placeholder="Jumlah DP/Bayar" class="h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 text-xs font-semibold" />
                            <InputError :message="errors.amount_paid?.[0]" />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-[11px] uppercase tracking-wider">Status Pembayaran</Label>
                            <div :class="['h-11 flex items-center px-4 rounded-2xl border text-xs font-bold uppercase tracking-wider', automaticStatusLabel.color]">
                                {{ automaticStatusLabel.text }}
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label class="text-xs font-bold">Daftar Bahan Baku / Material</Label>
                            <button type="button" @click="addItem" class="text-xs font-bold text-primary hover:underline flex items-center gap-1 cursor-pointer">+ Tambah Baris</button>
                        </div>

                        <div class="space-y-2">
                            <div v-for="(item, index) in form.items" :key="index" class="p-3 sm:p-4 rounded-3xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/40 grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                                <div class="sm:col-span-5">
                                    <Select v-model="item.raw_material_id">
                                        <SelectTrigger class="w-full h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 px-4 text-xs font-semibold">
                                            <SelectValue placeholder="Pilih Material" />
                                        </SelectTrigger>
                                        <SelectContent class="rounded-2xl">
                                            <SelectItem v-for="m in materialOptions" :key="m.id" :value="m.id" class="text-xs font-medium">
                                                {{ m.name }} <span v-if="m.purchase_unit" class="text-slate-400">({{ m.purchase_unit }})</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="errors[`items.${index}.raw_material_id`]?.[0]" />
                                </div>

                                <div class="sm:col-span-2">
                                    <Input type="number" step="0.01" v-model="item.qty" placeholder="Qty" class="h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 px-4 text-xs font-semibold" />
                                    <InputError :message="errors[`items.${index}.qty`]?.[0]" />
                                </div>

                                <div class="sm:col-span-2">
                                    <Input type="number" v-model="item.unit_price" placeholder="Harga" class="h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 px-4 text-xs font-semibold" />
                                    <InputError :message="errors[`items.${index}.unit_price`]?.[0]" />
                                </div>

                                <div class="sm:col-span-2 text-right font-mono font-bold text-xs text-slate-700 dark:text-zinc-300">
                                    Rp {{ (Number(item.qty) * Number(item.unit_price)).toLocaleString('id-ID') }}
                                </div>

                                <div class="sm:col-span-1 flex justify-end sm:justify-center">
                                    <button type="button" @click="removeItem(index)" class="size-9 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition-colors cursor-pointer">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        <div class="space-y-1.5">
                            <Label>Catatan Internal PO</Label>
                            <textarea v-model="form.notes" class="w-full rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900 p-3 text-xs focus:outline-none focus:ring-1 focus:ring-primary/20" rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                        </div>

                        <div class="p-4 sm:p-5 rounded-3xl bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/60 dark:border-zinc-800 space-y-2 text-xs">
                            <div class="flex justify-between text-slate-500"><span>Total Unik Item:</span> <span class="font-bold font-mono text-slate-800 dark:text-zinc-200">{{ totalItems }}</span></div>
                            <div class="flex justify-between text-slate-500"><span>Total Kuantitas:</span> <span class="font-bold font-mono text-slate-800 dark:text-zinc-200">{{ totalQty.toFixed(2) }}</span></div>
                            <div class="flex justify-between border-t border-slate-200 dark:border-zinc-800 pt-2.5 text-sm">
                                <span class="font-bold text-slate-700 dark:text-zinc-300">Total Bruto:</span> 
                                <span class="font-black font-mono text-primary">Rp {{ totalAmount.toLocaleString('id-ID') }}</span>
                            </div>
                            <div v-if="remainingBill > 0" class="flex justify-between text-rose-600 bg-rose-500/10 p-2 rounded-xl font-medium mt-1">
                                <span>Sisa Utang Dagang:</span>
                                <span class="font-bold font-mono">Rp {{ remainingBill.toLocaleString('id-ID') }}</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 dark:border-zinc-900 bg-slate-50/50 dark:bg-zinc-900/40 flex items-center justify-end gap-3 shrink-0">
                <Button type="button" variant="ghost" @click="$emit('close')" class="rounded-xl h-11 px-5 text-xs font-bold cursor-pointer">Batal</Button>
                <Button type="button" variant="outline" :disabled="processing" @click="submit('draft')" class="rounded-xl h-11 px-5 text-xs font-bold cursor-pointer">Simpan Draft</Button>
                <Button type="button" :disabled="processing" @click="submit('received')" class="rounded-xl h-11 px-6 text-xs font-bold cursor-pointer shadow-lg shadow-primary/20">Simpan & Terima Stok</Button>
            </div>
        </div>
    </div>
</template>