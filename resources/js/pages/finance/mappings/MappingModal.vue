<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Modal from '@/components/ui/Modal.vue';
import { useAccount } from '@/composables/useAccount';
import { type Mapping } from '@/composables/useMapping';
import { useOutlet } from '@/composables/useOutlet';

import Select from '@/components/ui/select/Select.vue';
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue';
import SelectValue from '@/components/ui/select/SelectValue.vue';
import SelectContent from '@/components/ui/select/SelectContent.vue';
import SelectItem from '@/components/ui/select/SelectItem.vue';

const props = defineProps<{
    show: boolean;
    mapping: Mapping | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', value: Mapping): void;
}>();

const { activeOutletId } = useOutlet();
const { accounts, fetchAccounts } = useAccount(activeOutletId);

onMounted(() => {
    fetchAccounts();
});

watch(activeOutletId, () => {
    fetchAccounts();
});

const form = ref<Mapping>({
    id: '',
    transaction_type: '',
    debit_account_id: '',
    credit_account_id: '',
    description_template: '',
});

watch(
    () => props.mapping,
    (value) => {
        if (value) {
            form.value = {
                id: value.id,
                transaction_type: value.transaction_type,
                debit_account_id: value.debit_account_id || '',
                credit_account_id: value.credit_account_id || '',
                description_template: value.description_template || '',
            };
        }
    },
    { immediate: true }
);

const submit = () => {
    emit('save', form.value);
    emit('close');
};
</script>

<template>
    <Modal :show="show" title="Konfigurasi Jurnal Otomatis" maxWidth="max-w-xl" @close="emit('close')">
        <form @submit.prevent="submit" class="space-y-4 text-xs">
            <p class="text-muted-foreground">
                Petakan akun debet & kredit default untuk otomasi pembukuan double-entry.
            </p>

            <div class="space-y-1.5">
                <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">
                    Transaction Event / Trigger
                </label>
                <Input
                    v-model="form.transaction_type"
                    disabled
                    class="h-9 bg-muted text-muted-foreground font-semibold select-none capitalize"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider text-[10px]">
                        Akun Debet (J1 Debit) <span class="text-destructive">*</span>
                    </label>
                    <Select v-model="form.debit_account_id">
                        <SelectTrigger class="w-full h-9 rounded-xl border border-input bg-background px-3 text-xs font-semibold text-foreground shadow-2xs">
                            <SelectValue placeholder="-- Pilih Akun Debet --" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl">
                            <SelectItem v-for="acc in accounts" :key="acc.id" :value="acc.id!" class="text-xs font-medium">
                                [{{ acc.code }}] {{ acc.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-1.5">
                    <label class="font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider text-[10px]">
                        Akun Kredit (J1 Kredit) <span class="text-destructive">*</span>
                    </label>
                    <Select v-model="form.credit_account_id">
                        <SelectTrigger class="w-full h-9 rounded-xl border border-input bg-background px-3 text-xs font-semibold text-foreground shadow-2xs">
                            <SelectValue placeholder="-- Pilih Akun Kredit --" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl">
                            <SelectItem v-for="acc in accounts" :key="acc.id" :value="acc.id!" class="text-xs font-medium">
                                [{{ acc.code }}] {{ acc.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">
                    Template Keterangan Jurnal Naratif
                </label>
                <textarea
                    v-model="form.description_template"
                    rows="3"
                    placeholder="Contoh: Penerimaan bahan baku otomatis untuk PO #{{po_number}}"
                    class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus-visible:outline-none"
                />
            </div>

            <div class="flex justify-end gap-2 border-t border-border/60 pt-4">
                <Button variant="outline" type="button" size="sm" class="h-9 px-4 rounded-xl text-xs font-semibold cursor-pointer" @click="emit('close')">
                    Batal
                </Button>
                <Button type="submit" size="sm" class="h-9 px-5 rounded-xl text-xs font-semibold cursor-pointer">
                    Simpan Pemetaan
                </Button>
            </div>
        </form>
    </Modal>
</template>