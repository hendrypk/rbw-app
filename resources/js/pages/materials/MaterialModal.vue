<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

const props = defineProps<{ show: boolean, material?: any | null }>();
const emit = defineEmits(['close', 'saved']);

const processing = ref(false);
const errors = ref<Record<string, string>>({});
const isEdit = computed(() => !!props.material);

// Fungsi untuk memformat angka dengan 2 desimal
const formatNumber = (value: number | string) => {
    // Memastikan angka yang masuk adalah number
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
                ...props.material,
                conversion_factor: Number(parseFloat(props.material.conversion_factor).toFixed(2)),
                min_stock: Number(parseFloat(props.material.min_stock).toFixed(2))
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
        if (isEdit.value) {
            await axios.put(`/api/raw-materials/${props.material.id}`, form.value);
        } else {
            await axios.post('/api/raw-materials', form.value);
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
        <div class="w-full max-w-lg bg-card rounded-xl shadow-xl p-6 border border-border">
            <h2 class="text-lg font-semibold mb-6">{{ isEdit ? 'Edit Material' : 'Tambah Material' }}</h2>
            
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="name">Nama Material</Label>
                    <Input id="name" v-model="form.name" placeholder="Contoh: Mayones" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="base_unit">Satuan Resep (Base Unit)</Label>
                        <Input id="base_unit" v-model="form.base_unit" placeholder="ml" />
                        <InputError :message="errors.base_unit" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="purchase_unit">Satuan Beli (Purchase Unit)</Label>
                        <Input id="purchase_unit" v-model="form.purchase_unit" placeholder="Liter" />
                        <InputError :message="errors.purchase_unit" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="conversion_factor">Faktor Konversi</Label>
                        <Input id="conversion_factor" type="text" step="0.01" v-model="form.conversion_factor" />
                        <p class="text-xs text-muted-foreground">
                            1 {{ form.purchase_unit }} = {{ formatNumber(form.conversion_factor) }} {{ form.base_unit }}
                        </p>
                        <InputError :message="errors.conversion_factor" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="stock_qty">Stok Minimum</Label>
                        <Input id="stock_qty" type="text" step="0.01" v-model="form.stock_qty" />
                        <InputError :message="errors.stock_qty" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.is_active" id="active" class="rounded border-border text-primary" />
                    <Label for="active">Material Aktif</Label>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <Button type="button" variant="ghost" @click="$emit('close')">Batal</Button>
                    <Button type="submit" :disabled="processing">Simpan</Button>
                </div>
            </form>
        </div>
    </div>
</template>