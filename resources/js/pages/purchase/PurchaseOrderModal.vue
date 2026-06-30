<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useSuppliers } from '@/composables/useSuppliers';
import { useMaterials } from '@/composables/useMaterials';

const props = defineProps<{ show: boolean, po?: any }>();
const emit = defineEmits(['close', 'saved']);

const { suppliers, fetchSuppliers } = useSuppliers();
const { materialOptions, fetchMaterialOptions } = useMaterials(); // Pastikan composable ini mengembalikan array

const processing = ref(false);
const errors = ref<Record<string, string>>({});

// Inisialisasi Form
const form = ref({
    supplier_id: '',
    order_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [{ raw_material_id: '', qty: 1, unit_price: 0 }]
});

// Computed: Kalkulasi Rekapan
const totalItems = computed(() => form.value.items.length);
const totalQty = computed(() => form.value.items.reduce((sum, item) => sum + (parseFloat(item.qty as any) || 0), 0));
const totalAmount = computed(() => form.value.items.reduce((sum, item) => sum + (Number(item.qty) * Number(item.unit_price)), 0));

const addItem = () => form.value.items.push({ raw_material_id: '', qty: 1, unit_price: 0 });
const removeItem = (index: number) => {
    if (form.value.items.length > 1) form.value.items.splice(index, 1);
};

// Reset saat modal buka
watch(() => props.show, async (newVal) => {
    if (newVal) {
        // Ambil data terbaru
        await Promise.all([
            fetchSuppliers({ active: true }),
            fetchMaterialOptions()
        ]);

        if (props.po) {
            form.value = {
                supplier_id: props.po.supplier_id || '',
                order_date: props.po.order_date ? props.po.order_date.split('T')[0] : new Date().toISOString().split('T')[0],
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
                notes: '', 
                items: [{ raw_material_id: '', qty: 1, unit_price: 0 }] 
            };
        }
    }
});

// FUNGSI SUBMIT DIPERBAIKI: Menerima status
const submit = async (status: string) => {
    processing.value = true;
    errors.value = {}; // Reset error
    
    // Gabungkan form dengan status
    const payload = { ...form.value, status };

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
        <div class="w-full max-w-4xl bg-card rounded-xl shadow-xl p-6 border border-border max-h-[90vh] overflow-y-auto">
            <h2 class="text-lg font-semibold mb-6">{{ po ? 'Edit PO' : 'Tambah PO Baru' }}</h2>
            
            <form @submit.prevent class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label>Supplier</Label>
                        <select v-model="form.supplier_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                            <option value="">Pilih Supplier</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Tanggal Pembelian</Label>
                        <Input type="date" v-model="form.order_date" />
                    </div>
                </div>

                <div class="border rounded-lg p-4 bg-muted/20">
                    <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 gap-4 mb-2 items-center">
                        <div class="col-span-5">
                            <select v-model="item.raw_material_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">Pilih Material</option>
                                <option v-for="m in materialOptions" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <Input type="number" step="0.01" v-model="item.qty" />
                        </div>
                        <div class="col-span-2">
                            <Input type="number" v-model="item.unit_price" />
                        </div>
                        <div class="col-span-2 text-right">
                            {{ (Number(item.qty) * Number(item.unit_price)).toLocaleString() }}
                        </div>
                        <div class="col-span-1 text-right">
                            <Button variant="ghost" size="sm" @click="removeItem(index)" class="text-destructive">x</Button>
                        </div>
                    </div>
                    <Button type="button" variant="outline" size="sm" class="mt-2" @click="addItem">+ Tambah Item</Button>
                </div>
                <div class="flex justify-end">
                    <div class="w-64 space-y-2 text-sm">
                        <div class="flex justify-between"><span>Total Item:</span> <span class="font-bold">{{ totalItems }}</span></div>
                        <div class="flex justify-between"><span>Total Qty:</span> <span class="font-bold">{{ totalQty.toFixed(2) }}</span></div>
                        <div class="flex justify-between border-t pt-2 text-base"><span>Total Pembelian:</span> <span class="font-bold text-primary">Rp {{ totalAmount.toLocaleString() }}</span></div>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Deskripsi / Catatan</Label>
                    <textarea v-model="form.notes" class="w-full rounded-md border border-input p-2 text-sm" rows="3"></textarea>
                </div>

                
                <div class="flex justify-end gap-3 mt-6">
                    <Button type="button" variant="outline" @click="$emit('close')">Batal</Button>
                    <Button type="button" variant="secondary" :disabled="processing" @click="submit('draft')">Save as Draft</Button>
                    <Button type="button" variant="default" :disabled="processing" @click="submit('received')">Save and Receive</Button>
                </div>
            </form>
        </div>
    </div>
</template>