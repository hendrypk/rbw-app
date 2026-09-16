<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { useSwal } from '@/composables/useSwal';
import Modal from '@/components/ui/Modal.vue';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Receipt } from '@lucide/vue';

// Import komponen Select
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import Button from '@/components/ui/button/Button.vue';

interface Overhead {
    id?: string;
    outlet_id: string;
    name: string;
    amount: number;
    type: string;
    started_at?: string | null;
    is_active: boolean;
}

interface OverheadForm {
    outlet_id: string;
    name: string;
    amount: number;
    type: string;
    started_at?: string | null;
    is_active: boolean;
}

const props = defineProps<{
    show: boolean;
    overhead: Overhead | null;
    outletId?: string;
}>();

const emit = defineEmits(['close', 'saved']);
const { success, error: swalError } = useSwal();

const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref<OverheadForm>({
    outlet_id: '',
    name: '',
    amount: 0,
    type: 'per_porsi',
    started_at: null,
    is_active: true,
});

const isEdit = computed(() => !!props.overhead?.id);

watch(() => props.show, (newVal) => {
    if (newVal) {
        errors.value = {};
        if (props.overhead) {
            form.value = {
                ...props.overhead,
                started_at: props.overhead.started_at ? props.overhead.started_at.split('T')[0] : null
            };
        } else {
            form.value = {
                outlet_id: props.outletId || '',
                name: '',
                amount: 0,
                type: 'per_porsi',
                started_at: null,
                is_active: true
            };
        }
    }
});

watch(() => form.value.type, (newType) => {
    if (newType === 'per_porsi') {
        form.value.started_at = null;
    }
});

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const payload = { ...form.value };
        if (payload.type === 'per_porsi') {
            payload.started_at = null;
        }

        if (isEdit.value) {
            await axios.put(`/api/overhead-costs/${props.overhead!.id}`, payload);
            success('Berhasil', 'Overhead cost berhasil diperbarui.');
        } else {
            await axios.post('/api/overhead-costs', payload);
            success('Berhasil', 'Overhead cost baru berhasil ditambahkan.');
        }

        emit('saved');
        emit('close');
    } catch (err: any) {
        if (err.response?.status === 422) {
            const validationErrors = err.response.data.errors;
            for (const key in validationErrors) {
                errors.value[key] = validationErrors[key][0];
            }
        } else {
            swalError('Gagal', err.response?.data?.message || 'Gagal menyimpan data.');
        }
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Modal :show="show" :title="isEdit ? 'Edit Overhead Cost' : 'Tambah Overhead Cost'" maxWidth="max-w-lg" @close="$emit('close')">
        <template #icon>
            <Receipt class="w-5 h-5 text-primary" />
        </template>

        <form @submit.prevent="submit" class="space-y-4 text-xs">
            <div class="grid gap-1.5">
                <Label for="name" class="font-semibold text-slate-700 dark:text-zinc-300">Nama Komponen Biaya</Label>
                <Input id="name" v-model="form.name" placeholder="Misal: Dus Kotak Box" class="h-9 rounded-xl text-xs" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="amount" class="font-semibold text-slate-700 dark:text-zinc-300">Nominal (Rp)</Label>
                    <Input id="amount" type="number" min="0" v-model.number="form.amount" placeholder="0" class="h-9 rounded-xl text-xs font-semibold text-red-600 dark:text-red-400" />
                    <InputError :message="errors.amount" />
                </div>

                <div class="grid gap-1.5">
                    <Label class="font-semibold text-slate-700 dark:text-zinc-300">Tipe Pembebanan</Label>

                    <!-- Menggunakan Komponen Select -->
                    <Select v-model="form.type">
                        <SelectTrigger class="h-9 rounded-xl text-xs">
                            <SelectValue placeholder="Pilih tipe pembebanan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="per_porsi" class="text-xs">Per Porsi / Unit Menu</SelectItem>
                                <SelectItem value="harian" class="text-xs">Harian</SelectItem>
                                <SelectItem value="mingguan" class="text-xs">Mingguan</SelectItem>
                                <SelectItem value="bulanan" class="text-xs">Bulanan</SelectItem>
                                <SelectItem value="tahunan" class="text-xs">Tahunan</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <InputError :message="errors.type" />
                </div>
            </div>

            <div v-if="form.type !== 'per_porsi'" class="grid gap-1.5 animate-in fade-in slide-in-from-top-2 duration-300">
                <Label for="started_at" class="font-semibold text-slate-700 dark:text-zinc-300">Tanggal Mulai Berlaku</Label>
                <Input id="started_at" type="date" v-model="form.started_at" class="h-9 rounded-xl text-xs" />
                <p class="text-[11px] text-muted-foreground">
                    Sistem akan menghitung modulus/tagihan overhead berdasarkan tanggal mulai ini.
                </p>
                <InputError :message="errors.started_at" />
            </div>

            <div class="flex items-center gap-2.5 pt-1">
                <Checkbox id="active" v-model:checked="form.is_active" />
                <Label for="active" class="cursor-pointer font-medium select-none text-slate-700 dark:text-zinc-300">Aktifkan Overhead Ini</Label>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-zinc-800 mt-6">
                <Button type="button" variant="outline" size="sm" class="h-9 px-4 rounded-xl text-xs font-semibold" @click="$emit('close')">Batal</Button>
                <Button type="submit" size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm" :disabled="processing">
                    {{ processing ? 'Menyimpan...' : (isEdit ? 'Update Overhead' : 'Simpan Overhead') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
