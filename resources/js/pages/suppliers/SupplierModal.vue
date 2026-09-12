<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useOutlet } from '@/composables/useOutlet';
import { Truck } from '@lucide/vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Modal from '@/components/ui/Modal.vue';
import { useSwal } from '@/composables/useSwal';

const props = defineProps<{ 
    show: boolean, 
    supplier?: any | null 
}>();

const emit = defineEmits(['close', 'saved']);

const processing = ref(false);
const errors = ref<Record<string, string>>({});
const { getOutletParam } = useOutlet();
const { success, error } = useSwal();

const isEdit = computed(() => !!props.supplier);

const form = ref({
    name: '',
    phone: '',
    email: '',
    address: '',
    is_active: true,
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.supplier) {
            form.value = { 
                name: props.supplier.name || '',
                phone: props.supplier.phone || '',
                email: props.supplier.email || '',
                address: props.supplier.address || '',
                is_active: Boolean(props.supplier.is_active)
            };
        } else {
            form.value = { name: '', phone: '', email: '', address: '', is_active: true };
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
            const res = await axios.put(`/api/suppliers/${props.supplier.id}`, payload);
            success('Berhasil', res.data.message || 'Supplier berhasil diperbarui.');
        } else {
            const res = await axios.post('/api/suppliers', payload);
            success('Berhasil', 'Supplier baru berhasil ditambahkan.');
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
    <Modal :show="show" :title="isEdit ? 'Edit Supplier' : 'Tambah Supplier Baru'" maxWidth="max-w-md" @close="$emit('close')">
        <template #icon>
            <Truck class="w-5 h-5 text-primary" />
        </template>

        <form @submit.prevent="submit" class="space-y-4 text-xs">
            <div class="grid gap-1.5">
                <Label for="name" class="font-semibold text-slate-700 dark:text-zinc-300">Nama Supplier</Label>
                <Input id="name" v-model="form.name" placeholder="Contoh: PT. Maju Jaya" class="h-9 rounded-xl text-xs" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="phone" class="font-semibold text-slate-700 dark:text-zinc-300">Telepon</Label>
                    <Input id="phone" v-model="form.phone" class="h-9 rounded-xl text-xs" />
                    <InputError :message="errors.phone" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="email" class="font-semibold text-slate-700 dark:text-zinc-300">Email</Label>
                    <Input id="email" v-model="form.email" type="email" class="h-9 rounded-xl text-xs" />
                    <InputError :message="errors.email" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="address" class="font-semibold text-slate-700 dark:text-zinc-300">Alamat</Label>
                <Input id="address" v-model="form.address" class="h-9 rounded-xl text-xs" />
                <InputError :message="errors.address" />
            </div>

            <div class="flex items-center gap-2.5 pt-1">
                <Checkbox id="active" v-model:checked="form.is_active" />
                <Label for="active" class="cursor-pointer font-medium select-none text-slate-700 dark:text-zinc-300">Supplier Aktif</Label>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-zinc-800 mt-6">
                <Button type="button" variant="outline" size="sm" class="h-9 px-4 rounded-xl text-xs font-semibold" @click="$emit('close')">Batal</Button>
                <Button type="submit" size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm" :disabled="processing">
                    {{ processing ? 'Menyimpan...' : (isEdit ? 'Update Supplier' : 'Simpan Supplier') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>