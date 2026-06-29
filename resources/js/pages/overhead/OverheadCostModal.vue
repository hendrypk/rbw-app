<script setup lang="ts">
import { ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal';
import axios from 'axios';


interface Overhead {
    id?: number;
    name: string;
    amount: number;
    type: string;
    is_active: boolean;
}

interface OverheadForm {
    name: string;
    amount: number;
    type: string;
    is_active: boolean;
}

const props = defineProps<{
    show: boolean;
    overhead: Overhead | null;
}>();

const emit = defineEmits(['close', 'saved']);
const { success, error } = useSwal();
const isSubmitting = ref(false);

const form = ref<OverheadForm>({
    name: '',
    amount: 0,
    type: '',
    is_active: true,
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.overhead) {
            form.value = { ...props.overhead };
        } else {
            form.value = { name: '', amount: 0, type: 'per_porsi', is_active: true };
        }
    }
});

const submit = async () => {
    isSubmitting.value = true;
    try {
        if (props.overhead?.id) {
            await axios.put(`/api/overhead-costs/${props.overhead.id}`, form.value);
            success('Berhasil', 'Overhead cost berhasil diperbarui.');
        } else {
            await axios.post('/api/overhead-costs', form.value);
            success('Berhasil', 'Overhead cost baru berhasil ditambahkan.');
        }
        emit('saved');
        emit('close');
    } catch (err: any) {
        error('Gagal', err.response?.data?.message || 'Gagal menyimpan data.');
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-in fade-in duration-200">
        <div class="bg-card border border-border w-full max-w-md rounded-xl shadow-lg overflow-hidden animate-in zoom-in-95 duration-200 text-foreground">
            <div class="px-6 py-4 border-b border-border flex items-center justify-between">
                <h3 class="text-lg font-semibold">{{ overhead ? 'Edit' : 'Tambah' }} Overhead Cost</h3>
                <button @click="emit('close')" class="text-muted-foreground hover:text-foreground text-xl">&times;</button>
            </div>
            
            <form @submit.prevent="submit">
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nama Komponen Biaya</label>
                        <Input v-model="form.name" placeholder="Misal: Dus Kotak Box" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Nominal (Rp)</label>
                        <Input v-model.number="form.amount" type="number" min="0" placeholder="0" required class="font-semibold text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipe Pembebanan</label>
                        <select v-model="form.type" class="w-full flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                            <option value="per_porsi">Per Porsi / Unit Menu</option>
                            <option value="per_bulan">Flat Bulanan (Operasional)</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" v-model="form.is_active" id="modal_is_active" class="rounded border-input text-primary focus:ring-ring" />
                        <label for="modal_is_active" class="text-sm font-medium select-none cursor-pointer">Set sebagai komponen Aktif</label>
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-muted/30 border-t border-border flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="emit('close')">Batal</Button>
                    <Button type="submit" size="sm" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : 'Simpan Data' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>