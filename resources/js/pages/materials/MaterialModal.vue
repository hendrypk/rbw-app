<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useOutlet } from '@/composables/useOutlet';
import { Package } from '@lucide/vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Modal from '@/components/ui/Modal.vue';
import { useSwal } from '@/composables/useSwal';

const props = defineProps<{ show: boolean, material?: any | null }>();
const emit = defineEmits(['close', 'saved']);

const processing = ref(false);
const errors = ref<Record<string, string>>({});
const { getOutletParam } = useOutlet();
const { success, error } = useSwal();
const isEdit = computed(() => !!props.material);

const formatNumber = (value: number | string) => {
    const num = Number(value); 
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};

const form = ref({
    name: '',
    base_unit: '',
    purchase_unit: '',
    conversion_factor: 1,
    stock_qty: 0,
    min_stock: 0,
    is_active: true,
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.material) {
            form.value = { 
                name: props.material.name || '',
                base_unit: props.material.base_unit || '',
                purchase_unit: props.material.purchase_unit || '',
                conversion_factor: Number(parseFloat(props.material.conversion_factor || 1).toFixed(2)),
                stock_qty: Number(parseFloat(props.material.stock_qty || 0).toFixed(2)),
                min_stock: Number(parseFloat(props.material.min_stock || 0).toFixed(2)),
                is_active: Boolean(props.material.is_active)
            };
        } else {
            form.value = { 
                name: '', 
                base_unit: 'ml', 
                purchase_unit: 'L', 
                conversion_factor: 1.00, 
                stock_qty: 0,
                min_stock: 0.00, 
                is_active: true
            };
        }
        errors.value = {};
    }
});

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const outletParam = getOutletParam();
        const payload = { ...form.value, ...outletParam };

        if (isEdit.value) {
            const res = await axios.put(`/api/raw-materials/${props.material.id}`, payload);
            success('Berhasil', res.data.message || 'Material berhasil diperbarui.');
        } else {
            const res = await axios.post('/api/raw-materials', payload);
            success('Berhasil', 'Material baru berhasil ditambahkan.');
        }
        
        emit('saved');
        emit('close');
    } catch (e: any) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors;
            error('Validasi Gagal', 'Mohon periksa kembali form input Anda.');
        } else {
            const errMessage = e.response?.data?.message || 'Terjadi kesalahan saat menyimpan data.';
            error('Gagal', errMessage);
        }
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Modal :show="show" :title="isEdit ? 'Edit Material' : 'Tambah Material'" maxWidth="max-w-lg" @close="$emit('close')">
        <template #icon>
            <Package class="w-5 h-5 text-primary" />
        </template>
        
        <form @submit.prevent="submit" class="space-y-4 text-xs">
            <div class="grid gap-1.5">
                <Label for="name" class="font-semibold text-slate-700 dark:text-zinc-300">Nama Material</Label>
                <Input id="name" v-model="form.name" placeholder="Contoh: Mayones" class="h-9 rounded-xl text-xs" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="base_unit" class="font-semibold text-slate-700 dark:text-zinc-300">Satuan Resep (Base Unit)</Label>
                    <Input id="base_unit" v-model="form.base_unit" placeholder="ml" class="h-9 rounded-xl text-xs" />
                    <InputError :message="errors.base_unit" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="purchase_unit" class="font-semibold text-slate-700 dark:text-zinc-300">Satuan Beli (Purchase Unit)</Label>
                    <Input id="purchase_unit" v-model="form.purchase_unit" placeholder="Liter" class="h-9 rounded-xl text-xs" />
                    <InputError :message="errors.purchase_unit" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="conversion_factor" class="font-semibold text-slate-700 dark:text-zinc-300">Faktor Konversi</Label>
                    <Input id="conversion_factor" type="text" step="0.01" v-model="form.conversion_factor" class="h-9 rounded-xl text-xs" />
                    <p class="text-[11px] text-muted-foreground">
                        1 {{ form.purchase_unit }} = {{ formatNumber(form.conversion_factor) }} {{ form.base_unit }}
                    </p>
                    <InputError :message="errors.conversion_factor" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="stock_qty" class="font-semibold text-slate-700 dark:text-zinc-300">Stok Minimum</Label>
                    <Input id="stock_qty" type="text" step="0.01" v-model="form.min_stock" class="h-9 rounded-xl text-xs" />
                    <InputError :message="errors.min_stock" />
                </div>
            </div>

            <div class="flex items-center gap-2.5 pt-1">
                <Checkbox id="active" v-model:checked="form.is_active" />
                <Label for="active" class="cursor-pointer font-medium select-none text-slate-700 dark:text-zinc-300">Material Aktif</Label>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-zinc-800 mt-6">
                <Button type="button" variant="outline" size="sm" class="h-9 px-4 rounded-xl text-xs font-semibold" @click="$emit('close')">Batal</Button>
                <Button type="submit" size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm" :disabled="processing">
                    {{ processing ? 'Menyimpan...' : (isEdit ? 'Update Material' : 'Simpan Material') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>