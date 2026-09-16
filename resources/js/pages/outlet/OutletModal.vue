<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import InputError from '@/components/InputError.vue';
import { Store } from '@lucide/vue';
import Modal from '@/components/ui/Modal.vue';

const props = defineProps<{
    show: boolean;
    outlet?: any | null;
}>();

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    id: null as string | null,
    name: '',
    code: '',
    address: '',
    phone: '',
    is_active: true,
});

watch(() => props.outlet, (newVal) => {
    if (newVal) {
        form.id = newVal.id;
        form.name = newVal.name || '';
        form.code = newVal.code || '';
        form.address = newVal.address || '';
        form.phone = newVal.phone || '';
        form.is_active = Boolean(newVal.is_active);
    } else {
        form.reset();
        form.id = null;
        form.is_active = true;
    }
    form.clearErrors();
}, { immediate: true });

const submitForm = () => {
    if (form.id) {
        form.put(`/api/outlets/${form.id}`, {
            onSuccess: () => {
                emit('saved', 'ubah');
                emit('close');
            },
        });
    } else {
        form.post('/api/outlets', {
            onSuccess: () => {
                emit('saved', 'simpan');
                emit('close');
            },
        });
    }
};
</script>

<template>
    <Modal :show="show" :title="form.id ? 'Edit Outlet' : 'Tambah Outlet Baru'" maxWidth="max-w-md" @close="$emit('close')">
        <template #icon>
            <Store class="w-5 h-5 text-primary" />
        </template>

        <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div class="grid gap-1.5">
                <Label for="name" class="font-semibold text-slate-700 dark:text-zinc-300">Nama Outlet</Label>
                <Input id="name" v-model="form.name" placeholder="Contoh: Cabang Yogyakarta" class="h-9 rounded-xl text-xs" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="code" class="font-semibold text-slate-700 dark:text-zinc-300">Kode Outlet</Label>
                    <Input id="code" v-model="form.code" placeholder="Contoh: JOG-01" class="h-9 rounded-xl text-xs font-mono" />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phone" class="font-semibold text-slate-700 dark:text-zinc-300">Nomor Telepon</Label>
                    <Input id="phone" v-model="form.phone" placeholder="Contoh: 08123456789" class="h-9 rounded-xl text-xs" />
                    <InputError :message="form.errors.phone" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="address" class="font-semibold text-slate-700 dark:text-zinc-300">Alamat</Label>
                <textarea
                    id="address"
                    v-model="form.address"
                    placeholder="Alamat lengkap outlet..."
                    rows="3"
                    class="w-full text-xs p-2.5 rounded-xl border border-input bg-transparent shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                ></textarea>
                <InputError :message="form.errors.address" />
            </div>

            <div class="flex items-center gap-2.5 pt-1">
                <Checkbox id="is_active" v-model:checked="form.is_active" />
                <Label for="is_active" class="cursor-pointer font-medium select-none text-slate-700 dark:text-zinc-300">Outlet Aktif</Label>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-zinc-800 mt-6">
                <Button type="button" variant="outline" size="sm" class="h-9 px-4 rounded-xl text-xs font-semibold" @click="$emit('close')">
                    Batal
                </Button>
                <Button type="submit" size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm" :disabled="form.processing">
                    {{ form.processing ? 'Menyimpan...' : (form.id ? 'Update Outlet' : 'Simpan Outlet') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
