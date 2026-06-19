<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

const props = defineProps<{ 
    show: boolean, 
    supplier?: any | null // Supplier bersifat opsional
}>();

const emit = defineEmits(['close', 'saved']);

const processing = ref(false);
const errors = ref<Record<string, string>>({});

// Deteksi mode: Jika ada supplier, berarti Edit. Jika tidak, berarti Create.
const isEdit = computed(() => !!props.supplier);

const form = ref({
    name: '',
    phone: '',
    email: '',
    address: '',
    is_active: true,
});

// Watch: Otomatis isi form saat modal dibuka atau data supplier berubah
watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.supplier) {
            form.value = { ...props.supplier };
        } else {
            form.value = { name: '', phone: '', email: '', address: '', is_active: true };
        }
        errors.value = {}; // Reset error saat buka
    }
});

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        if (isEdit.value) {
            await axios.put(`/api/suppliers/${props.supplier.id}`, form.value);
        } else {
            await axios.post('/api/suppliers', form.value);
        }
        
        emit('saved'); // Beritahu parent data sudah disimpan
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
            <h2 class="text-lg font-semibold mb-6">
                {{ isEdit ? 'Edit Supplier' : 'Tambah Supplier Baru' }}
            </h2>
            
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="name">Nama Supplier</Label>
                    <Input id="name" v-model="form.name" placeholder="Contoh: PT. Maju Jaya" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="phone">Telepon</Label>
                        <Input id="phone" v-model="form.phone" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="errors.email" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Alamat</Label>
                    <Input id="address" v-model="form.address" />
                    <InputError :message="errors.address" />
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.is_active" id="active" class="rounded border-border text-primary focus:ring-primary" />
                    <Label for="active">Supplier Aktif</Label>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <Button type="button" variant="ghost" @click="$emit('close')">Batal</Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : (isEdit ? 'Update Supplier' : 'Simpan Supplier') }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>