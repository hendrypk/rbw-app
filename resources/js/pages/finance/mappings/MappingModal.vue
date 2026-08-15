<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useAccount } from '@/composables/useAccount';
import { type Mapping } from '@/composables/useMapping';

const props = defineProps<{
    show: boolean;
    mapping: Mapping | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', value: Mapping): void;
}>();

const { accounts, fetchAccounts } = useAccount();

onMounted(() => {
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
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 dark:bg-zinc-950/60 backdrop-blur-sm p-4"
    >
        <div class="w-full max-w-xl overflow-hidden rounded-xl border border-border/80 bg-card text-card-foreground shadow-xl">

            <div class="flex items-center justify-between border-b border-border/60 p-5">
                <div>
                    <h2 class="text-sm font-bold tracking-tight text-foreground">
                        Konfigurasi Jurnal Otomatis
                    </h2>
                    <p class="text-[11px] text-muted-foreground mt-0.5">
                        Petakan akun debet & kredit default untuk otomasi pembukuan double-entry.
                    </p>
                </div>

                <button
                    type="button"
                    @click="emit('close')"
                    class="text-muted-foreground hover:text-foreground text-sm font-semibold p-1"
                >
                    ✕
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-4 p-5 text-xs">
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
                        <select
                            v-model="form.debit_account_id"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-semibold text-foreground focus-visible:outline-none"
                        >
                            <option value="">-- Pilih Akun Debet --</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                [{{ acc.code }}] {{ acc.name }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider text-[10px]">
                            Akun Kredit (J1 Kredit) <span class="text-destructive">*</span>
                        </label>
                        <select
                            v-model="form.credit_account_id"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-semibold text-foreground focus-visible:outline-none"
                        >
                            <option value="">-- Pilih Akun Kredit --</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                [{{ acc.code }}] {{ acc.name }}
                            </option>
                        </select>
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
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus-visible:outline-none"
                    />
                </div>

                <div class="flex justify-end gap-2 border-t border-border/60 pt-4">
                    <Button variant="outline" type="button" size="sm" class="h-8 text-xs font-semibold" @click="emit('close')">
                        Batal
                    </Button>
                    <Button type="submit" size="sm" class="h-8 text-xs font-semibold">
                        Simpan Pemetaan
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>