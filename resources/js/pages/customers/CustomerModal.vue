<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import InputError from '@/components/InputError.vue';
import Button from '@/components/ui/button/Button.vue';
import Modal from '@/components/ui/Modal.vue';

const props = defineProps<{
    show: boolean;
    customer: any | null;
}>();

const emit = defineEmits(['close', 'saved']);

const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    name: '',
    phone: '',
    email: '',
    shipping_address: '',
});

const isEdit = computed(() => !!props.customer?.id);

watch(() => props.show, (newVal) => {
    if (newVal) {
        errors.value = {};
        if (props.customer) {
            form.value = {
                name: props.customer.name || '',
                phone: props.customer.phone || '',
                email: props.customer.email || '',
                shipping_address: props.customer.shipping_address || '',
            };
        } else {
            form.value = { name: '', phone: '', email: '', shipping_address: '' };
        }
    }
});

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        if (isEdit.value) {
            await axios.put(`/api/customers/${props.customer.id}`, form.value);
        } else {
            await axios.post('/api/customers', form.value);
        }
        emit('saved', isEdit.value ? 'ubah' : 'simpan');
        emit('close');
    } catch (err: any) {
        if (err.response?.status === 422) {
            const validationErrors = err.response.data.errors;
            for (const key in validationErrors) {
                errors.value[key] = validationErrors[key][0];
            }
        }
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Modal :show="show" :title="isEdit ? 'Edit Pelanggan' : 'Tambah Pelanggan Baru'" maxWidth="max-w-md" @close="$emit('close')">
        <form @submit.prevent="submit" class="space-y-4 text-xs">
            <div class="grid gap-1.5">
                <Label for="name" class="font-semibold text-slate-700 dark:text-zinc-300">Nama Pelanggan <span class="text-red-500">*</span></Label>
                <Input id="name" v-model="form.name" placeholder="Misal: John Doe" class="h-9 rounded-xl text-xs" required />
                <InputError :message="errors.name" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="grid gap-1.5">
                    <Label for="phone" class="font-semibold text-slate-700 dark:text-zinc-300">No. WhatsApp/Telepon</Label>
                    <Input id="phone" v-model="form.phone" placeholder="0812xxxxxx" class="h-9 rounded-xl text-xs" />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="email" class="font-semibold text-slate-700 dark:text-zinc-300">Email</Label>
                    <Input id="email" type="email" v-model="form.email" placeholder="john@example.com" class="h-9 rounded-xl text-xs" />
                    <InputError :message="errors.email" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="address" class="font-semibold text-slate-700 dark:text-zinc-300">Alamat Pengiriman / Domisili</Label>
                <textarea
                    id="address"
                    v-model="form.shipping_address"
                    rows="3"
                    placeholder="Masukkan alamat lengkap..."
                    class="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 text-xs placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                ></textarea>
                <InputError :message="errors.shipping_address" />
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-zinc-800 mt-6">
                <Button type="button" variant="outline" size="sm" class="h-9 px-4 rounded-xl text-xs font-semibold" @click="$emit('close')">Batal</Button>
                <Button type="submit" size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm" :disabled="processing">
                    {{ processing ? 'Menyimpan...' : (isEdit ? 'Update Pelanggan' : 'Simpan Pelanggan') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
