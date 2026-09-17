<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Plus, X, Save } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { toast } from 'vue-sonner';

const props = defineProps<{
    isOpen: boolean;
    mode: 'create' | 'edit' | 'view';
    journalData?: any;
    accounts: Array<{ id: string; code: string; name: string }>;
    formatCurrency: (val: number) => string;
}>();

const emit = defineEmits(['update:isOpen', 'save', 'edit']);

const closeModal = () => {
    emit('update:isOpen', false);
};

const form = ref({
    entry_date: new Date().toISOString().split('T')[0],
    description: '',
    items: [
        { account_id: '', debit: 0, credit: 0 },
        { account_id: '', debit: 0, credit: 0 }
    ] as Array<{ account_id: string; debit: number; credit: number }>
});

const isSubmitting = ref(false);

// Sinkronisasi data saat modal dibuka
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        if (props.mode === 'create') {
            form.value = {
                entry_date: new Date().toISOString().split('T')[0],
                description: '',
                items: [
                    { account_id: '', debit: 0, credit: 0 },
                    { account_id: '', debit: 0, credit: 0 }
                ]
            };
        } else if ((props.mode === 'edit' || props.mode === 'view') && props.journalData) {
            const mappedItems = (props.journalData.items || []).map((item: any) => {
                let matchedAccountUuid = item.account_id;

                // Jika backend belum mengirim account_id, cocokkan lewat account_code dengan master accounts
                if (!matchedAccountUuid && item.account_code && props.accounts.length > 0) {
                    const foundAcc = props.accounts.find((acc: any) => acc.code === item.account_code);
                    if (foundAcc) {
                        matchedAccountUuid = foundAcc.id;
                    }
                }

                return {
                    account_id: String(matchedAccountUuid || ''),
                    debit: item.type === 'debit' ? Number(item.amount) : 0,
                    credit: item.type === 'credit' ? Number(item.amount) : 0
                };
            });

            form.value = {
                entry_date: props.journalData.entry_date || new Date().toISOString().split('T')[0],
                description: props.journalData.description || '',
                items: mappedItems.length > 0 ? mappedItems : [
                    { account_id: '', debit: 0, credit: 0 },
                    { account_id: '', debit: 0, credit: 0 }
                ]
            };
        }
    }
});

const addRow = () => {
    form.value.items.push({ account_id: '', debit: 0, credit: 0 });
};

const removeRow = (index: number) => {
    if (form.value.items.length <= 2) {
        toast.error("Jurnal berpasangan minimal memerlukan 2 baris akun.");
        return;
    }
    form.value.items.splice(index, 1);
};

const totalDebit = computed(() => {
    return form.value.items.reduce((acc, curr) => acc + (Number(curr.debit) || 0), 0);
});

const totalCredit = computed(() => {
    return form.value.items.reduce((acc, curr) => acc + (Number(curr.credit) || 0), 0);
});

const isBalanced = computed(() => {
    return Math.abs(totalDebit.value - totalCredit.value) < 0.01 && totalDebit.value > 0;
});

const handleFormSubmit = () => {
    if (!form.value.description) {
        toast.error("Keterangan jurnal wajib diisi.");
        return;
    }
    if (!isBalanced.value) {
        toast.error("Total Debet dan Kredit harus seimbang (balance)!");
        return;
    }

    const payloadItems: any[] = [];
    form.value.items.forEach(item => {
        if (!item.account_id) return;
        if (Number(item.debit) > 0) {
            payloadItems.push({ account_id: item.account_id, type: 'debit', amount: Number(item.debit) });
        }
        if (Number(item.credit) > 0) {
            payloadItems.push({ account_id: item.account_id, type: 'credit', amount: Number(item.credit) });
        }
    });

    emit('save', {
        id: props.journalData?.id,
        entry_date: form.value.entry_date,
        description: form.value.description,
        items: payloadItems
    });
};

const modalTitle = computed(() => {
    if (props.mode === 'create') return 'Tambah Jurnal Umum Baru';
    if (props.mode === 'edit') return 'Edit Jurnal Manual';
    return 'Detail Jurnal Transaksi';
});
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs">
        <div class="bg-white dark:bg-zinc-950 w-full max-w-4xl rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden flex flex-col max-h-[95vh]">

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4.5 border-b border-slate-100 dark:border-zinc-900">
                <div>
                    <h3 class="font-extrabold text-sm text-slate-900 dark:text-zinc-50">{{ modalTitle }}</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        {{ mode === 'view' ? 'Rincian data transaksi pembukuan.' : 'Catat transaksi finansial berpasangan dengan nominal bebas.' }}
                    </p>
                </div>
                <button @click="closeModal" class="size-8 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-900 flex items-center justify-center transition-colors cursor-pointer">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 overflow-y-auto flex-1 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="font-bold text-slate-400 uppercase text-[10px]">Tanggal Buku</label>
                        <Input
                            v-model="form.entry_date"
                            type="date"
                            :disabled="mode === 'view'"
                            class="h-10 rounded-xl border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 px-3 text-xs font-medium shadow-2xs"
                        />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="font-bold text-slate-400 uppercase text-[10px]">Keterangan Transaksi</label>
                        <Input
                            v-model="form.description"
                            type="text"
                            :disabled="mode === 'view'"
                            placeholder="Contoh: Pembayaran Biaya Listrik & Air"
                            class="h-10 rounded-xl border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 px-3 text-xs font-medium shadow-2xs"
                        />
                    </div>
                </div>

                <!-- Daftar Akun & Input Nominal -->
                <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-zinc-900">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-slate-800 dark:text-zinc-200 text-xs uppercase tracking-wider">Rincian Akun & Nominal</h4>
                        <Button
                            v-if="mode !== 'view'"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addRow"
                            class="h-8 px-3 rounded-xl border-slate-200 dark:border-zinc-800 text-xs font-bold text-slate-700 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-900 cursor-pointer shadow-2xs"
                        >
                            <Plus class="h-3.5 w-3.5 mr-1" /> Tambah Baris
                        </Button>
                    </div>

                    <div class="space-y-2 max-h-64 overflow-y-auto pr-1 custom-scrollbar">
                        <div v-for="(item, index) in form.items" :key="index" class="flex flex-col sm:flex-row items-center gap-2.5 bg-slate-50/60 dark:bg-zinc-900/40 p-2.5 rounded-2xl border border-slate-200/80 dark:border-zinc-800 shadow-2xs">
                            <!-- Pilih Akun -->
                            <div class="flex-1 w-full">
                                <Select v-model="item.account_id" :disabled="mode === 'view'">
                                    <SelectTrigger class="h-9 w-full rounded-xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-3 text-xs font-bold shadow-2xs">
                                        <SelectValue placeholder="-- Pilih Rekening Akun --" class="truncate" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="acc in accounts" :key="acc.id" :value="String(acc.id)">
                                            [{{ acc.code }}] {{ acc.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Input Debet -->
                            <div class="w-full sm:w-40">
                                <Input
                                    v-model.number="item.debit"
                                    type="number"
                                    min="0"
                                    step="any"
                                    :disabled="mode === 'view'"
                                    placeholder="Debet (Rp)"
                                    class="h-9 rounded-xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-3 text-xs font-mono font-bold text-right shadow-2xs text-blue-600 dark:text-blue-400"
                                />
                            </div>

                            <!-- Input Kredit -->
                            <div class="w-full sm:w-40">
                                <Input
                                    v-model.number="item.credit"
                                    type="number"
                                    min="0"
                                    step="any"
                                    :disabled="mode === 'view'"
                                    placeholder="Kredit (Rp)"
                                    class="h-9 rounded-xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-3 text-xs font-mono font-bold text-right shadow-2xs text-purple-600 dark:text-purple-400"
                                />
                            </div>

                            <button
                                v-if="mode !== 'view'"
                                type="button"
                                class="size-7 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center justify-center font-bold text-sm transition-all cursor-pointer shrink-0"
                                @click="removeRow(index)"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50/80 dark:bg-zinc-900/60 border-t border-slate-100 dark:border-zinc-900 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="space-y-0.5 text-center sm:text-left">
                    <p class="font-bold text-slate-800 dark:text-zinc-200">
                        Total Debet: Rp {{ totalDebit.toLocaleString('id-ID') }} | Total Kredit: Rp {{ totalCredit.toLocaleString('id-ID') }}
                    </p>
                    <p v-if="mode !== 'view'" class="text-[11px] font-bold" :class="isBalanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                        {{ isBalanced ? '✓ Jurnal seimbang dan siap disimpan' : '⚠ Total Debet dan Kredit belum seimbang (Unbalanced)' }}
                    </p>
                </div>

<div class="flex items-center gap-2.5 w-full sm:w-auto">
        <!-- Tombol Hapus (Hanya muncul di mode view untuk jurnal manual) -->
        <Button
            v-if="mode === 'view' && journalData?.is_manual_journal"
            type="button"
            @click="handleDelete"
            class="h-10 px-4 rounded-2xl text-xs font-bold bg-rose-500 hover:bg-rose-600 text-white shadow-sm cursor-pointer transition-all"
        >
            Hapus
        </Button>

        <!-- Tombol Edit -->
        <Button
            v-if="mode === 'view' && journalData?.is_manual_journal"
            type="button"
            @click="$emit('edit', journalData)"
            class="h-10 px-5 rounded-2xl text-xs font-bold bg-amber-500 hover:bg-amber-600 text-white shadow-sm cursor-pointer transition-all"
        >
            Edit Jurnal
        </Button>

        <Button
            variant="outline"
            type="button"
            @click="closeModal"
            class="h-10 px-5 rounded-2xl text-xs font-bold border-slate-200 dark:border-zinc-800 cursor-pointer shadow-2xs"
        >
            {{ mode === 'view' ? 'Tutup' : 'Batal' }}
        </Button>
    </div>
            </div>

        </div>
    </div>
</template>
