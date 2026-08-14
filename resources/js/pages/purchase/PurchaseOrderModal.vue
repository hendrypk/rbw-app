<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useSuppliers } from '@/composables/useSuppliers';
import { useMaterials } from '@/composables/useMaterials';
import { useAccount } from '@/composables/useAccount';

const props = defineProps<{ show: boolean, po?: any }>();
const emit = defineEmits(['close', 'saved']);

const { suppliers, fetchSuppliers } = useSuppliers();
const { materialOptions, fetchMaterialOptions } = useMaterials();
const { accounts, fetchAccounts } = useAccount();

const processing = ref(false);
const errors = ref<Record<string, any>>({});

// Inisialisasi Form (payment_status dihapus karena dihitung otomatis oleh sistem)
const form = ref({
    supplier_id: '',
    order_date: new Date().toISOString().split('T')[0],
    payment_account_id: '',       // Pilihan akun kas/bank penarik uang (Nullable)
    amount_paid: 0,               // Nominal yang dibayarkan user
    notes: '',
    items: [{ raw_material_id: '', qty: 1, unit_price: 0 }]
});

// Filter Akun hanya yang berkategori Kas & Bank (Category: '1')
const cashBankAccounts = computed(() => {
    return accounts.value.filter((acc: any) => acc.category === '1' || acc.category === 1);
});

// Computed: Kalkulasi Rekapan Bruto
const totalItems = computed(() => form.value.items.length);
const totalQty = computed(() => form.value.items.reduce((sum, item) => sum + (parseFloat(item.qty as any) || 0), 0));
const totalAmount = computed(() => form.value.items.reduce((sum, item) => sum + (Number(item.qty) * Number(item.unit_price)), 0));

// Sisa tagihan / nominal yang masuk ke buku hutang dagang supplier
const remainingBill = computed(() => {
    return Math.max(0, totalAmount.value - (Number(form.value.amount_paid) || 0));
});

// Logika Penentuan Status Otomatis untuk Visual Label Preview di Frontend
const automaticStatusLabel = computed(() => {
    const paid = Number(form.value.amount_paid) || 0;
    
    if (!form.value.payment_account_id || paid <= 0) {
        return { text: 'Hutang Penuh (Unpaid)', color: 'bg-red-500/10 text-red-600 border-red-500/20' };
    }
    if (paid >= totalAmount.value) {
        return { text: 'Lunas Langsung (Paid)', color: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
    }
    return { text: 'Bayar Sebagian (Partial)', color: 'bg-amber-500/10 text-amber-600 border-amber-500/20' };
});

// Watcher Pintar: Jika metode pembayaran di-clear (dikembalikan ke Beli Tempo), paksa nominal bayar ke 0
watch(() => form.value.payment_account_id, (newAccount) => {
    if (!newAccount) {
        form.value.amount_paid = 0;
    }
});

// Watcher Safety Lock: Pastikan input nominal bayar tidak sengaja melebihi total belanja bruto
watch(() => form.value.amount_paid, (newPaid) => {
    if (newPaid > totalAmount.value) {
        form.value.amount_paid = totalAmount.value;
    }
});

const addItem = () => form.value.items.push({ raw_material_id: '', qty: 1, unit_price: 0 });
const removeItem = (index: number) => {
    if (form.value.items.length > 1) form.value.items.splice(index, 1);
};

// Reset & Fetch master data saat modal terbuka
watch(() => props.show, async (newVal) => {
    if (newVal) {
        errors.value = {};
        await Promise.all([
            fetchSuppliers({ active: true }),
            fetchMaterialOptions(),
            fetchAccounts()
        ]);

        if (props.po) {
            form.value = {
                supplier_id: props.po.supplier_id || '',
                order_date: props.po.order_date ? props.po.order_date.split('T')[0] : new Date().toISOString().split('T')[0],
                payment_account_id: props.po.payment_account_id || '',
                amount_paid: parseFloat(props.po.amount_paid || 0),
                notes: props.po.notes || '',
                items: Array.isArray(props.po.items) 
                    ? props.po.items.map((item: any) => ({
                        raw_material_id: item.raw_material_id,
                        qty: parseFloat(item.qty),
                        unit_price: parseFloat(item.unit_price)
                    }))
                    : [{ raw_material_id: '', qty: 1, unit_price: 0 }]
            };
        } else {
            form.value = { 
                supplier_id: '', 
                order_date: new Date().toISOString().split('T')[0], 
                payment_account_id: '',
                amount_paid: 0,
                notes: '', 
                items: [{ raw_material_id: '', qty: 1, unit_price: 0 }] 
            };
        }
    }
});

const submit = async (status: string) => {
    processing.value = true;
    errors.value = {}; 
    
    const payload = { 
        ...form.value, 
        status,
        final_total: totalAmount.value 
    };

    try {
        if (props.po) {
            await axios.put(`/api/purchase-orders/${props.po.id}`, payload);
        } else {
            await axios.post('/api/purchase-orders', payload);
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
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-4xl bg-card rounded-xl shadow-xl p-6 border border-border max-h-[90vh] overflow-y-auto text-foreground">
            <h2 class="text-lg font-semibold mb-6">{{ po ? 'Edit PO' : 'Tambah PO Baru' }}</h2>
            
            <form @submit.prevent class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label>Supplier</Label>
                        <select v-model="form.supplier_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring">
                            <option value="">Pilih Supplier</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <InputError :message="errors.supplier_id?.[0]" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Tanggal Pembelian</Label>
                        <Input type="date" v-model="form.order_date" />
                        <InputError :message="errors.order_date?.[0]" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 p-4 border rounded-xl bg-muted/10 items-end">
                    <div class="grid gap-2">
                        <Label>Metode Pembayaran (Akun Kredit)</Label>
                        <select v-model="form.payment_account_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring">
                            <option value="">Beli Tempo (Tanpa Kas/Bank)</option>
                            <option v-for="acc in cashBankAccounts" :key="acc.id" :value="acc.id">
                                {{ acc.code }} - {{ acc.name }}
                            </option>
                        </select>
                        <InputError :message="errors.payment_account_id?.[0]" />
                    </div>

                    <div class="grid gap-2" v-if="form.payment_account_id">
                        <div class="flex justify-between items-center">
                            <Label>Jumlah yang Dibayarkan</Label>
                            <button type="button" @click="form.amount_paid = totalAmount" class="text-[10px] text-blue-600 hover:underline font-semibold">Bayar Lunas</button>
                        </div>
                        <Input type="number" v-model="form.amount_paid" :max="totalAmount" placeholder="Masukkan nominal DP/Pelunasan" />
                        <InputError :message="errors.amount_paid?.[0]" />
                    </div>

                    <div class="grid gap-2">
                        <Label class="text-muted-foreground">Status Pembayaran Terdeteksi</Label>
                        <div :class="['h-10 flex items-center px-3 rounded-md border text-xs font-bold uppercase tracking-wider', automaticStatusLabel.color]">
                            {{ automaticStatusLabel.text }}
                        </div>
                    </div>
                </div>

                <div class="border rounded-lg p-4 bg-muted/20">
                    <div class="grid grid-cols-12 gap-4 mb-2 font-semibold text-muted-foreground text-[11px] uppercase tracking-wider px-1">
                        <div class="col-span-5">Nama Bahan Baku / Material</div>
                        <div class="col-span-2">Quantity</div>
                        <div class="col-span-2">Harga Satuan</div>
                        <div class="col-span-2 text-right">Subtotal</div>
                        <div class="col-span-1"></div>
                    </div>
                    
                    <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 gap-4 mb-2 items-center">
                        <div class="col-span-5">
                            <select v-model="item.raw_material_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none">
                                <option value="">Pilih Material</option>
                                <option v-for="m in materialOptions" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                            <InputError :message="errors[`items.${index}.raw_material_id`]?.[0]" />
                        </div>
                        <div class="col-span-2">
                            <Input type="number" step="0.01" v-model="item.qty" />
                            <InputError :message="errors[`items.${index}.qty`]?.[0]" />
                        </div>
                        <div class="col-span-2">
                            <Input type="number" v-model="item.unit_price" />
                            <InputError :message="errors[`items.${index}.unit_price`]?.[0]" />
                        </div>
                        <div class="col-span-2 text-right font-mono font-medium">
                            Rp {{ (Number(item.qty) * Number(item.unit_price)).toLocaleString('id-ID') }}
                        </div>
                        <div class="col-span-1 text-center">
                            <Button type="button" variant="ghost" size="sm" @click="removeItem(index)" class="text-destructive hover:bg-destructive/10">×</Button>
                        </div>
                    </div>
                    <Button type="button" variant="outline" size="sm" class="mt-2 h-8" @click="addItem">+ Tambah Item</Button>
                </div>

                <div class="flex justify-end">
                    <div class="w-72 space-y-2 text-xs border p-4 rounded-xl bg-muted/5">
                        <div class="flex justify-between"><span>Total Unik Item:</span> <span class="font-semibold font-mono">{{ totalItems }}</span></div>
                        <div class="flex justify-between"><span>Total Kuantitas Volume:</span> <span class="font-semibold font-mono">{{ totalQty.toFixed(2) }}</span></div>
                        <div class="flex justify-between border-t pt-2 text-sm"><span>Total Bruto Pembelian:</span> <span class="font-bold font-mono">Rp {{ totalAmount.toLocaleString('id-ID') }}</span></div>
                        
                        <div v-if="form.payment_status !== 'paid'" class="flex justify-between text-destructive bg-destructive/5 p-1.5 rounded mt-1 font-medium">
                            <span>Sisa Masuk Utang Dagang:</span>
                            <span class="font-bold font-mono">Rp {{ remainingBill.toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Deskripsi / Catatan Internal PO</Label>
                    <textarea v-model="form.notes" class="w-full rounded-md border border-input p-2 text-sm focus:outline-none" rows="2" placeholder="Catatan termin, bukti bayar dp, dll..."></textarea>
                </div>
                
                <div class="flex justify-end gap-3 mt-6 border-t pt-4">
                    <Button type="button" variant="outline" @click="$emit('close')">Batal</Button>
                    <Button type="button" variant="secondary" :disabled="processing" @click="submit('draft')">Save as Draft</Button>
                    <Button type="button" variant="default" :disabled="processing" @click="submit('received')">Save and Receive</Button>
                </div>
            </form>
        </div>
    </div>
</template>