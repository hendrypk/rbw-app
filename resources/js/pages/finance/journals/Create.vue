<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Plus, Trash2, ArrowLeft, Save } from '@lucide/vue';
import { toast } from 'vue-sonner';

interface AccountOption {
    id: string;
    code: string;
    name: string;
}

interface JournalItemRow {
    account_id: string;
    type: 'debit' | 'credit';
    amount: number;
}

const accounts = ref<AccountOption[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);

const form = ref({
    entry_date: new Date().toISOString().split('T')[0],
    description: '',
    items: [
        { account_id: '', type: 'debit', amount: 0 },
        { account_id: '', type: 'credit', amount: 0 }
    ] as JournalItemRow[]
});

// Ambil daftar akun master untuk dropdown pilihan rekening
const fetchAccounts = async () => {
    try {
        const response = await axios.get('/api/finance/accounts'); // Sesuaikan endpoint master akun Anda
        accounts.value = response.data.data || response.data;
    } catch (error) {
        console.error("Gagal memuat master akun:", error);
        toast.error("Gagal memuat daftar akun rekening.");
    }
};

onMounted(() => {
    fetchAccounts();
});

const addRow = () => {
    form.value.items.push({ account_id: '', type: 'debit', amount: 0 });
};

const removeRow = (index: number) => {
    if (form.value.items.length <= 2) {
        toast.error("Jurnal berpasangan minimal memerlukan 2 baris akun.");
        return;
    }
    form.value.items.splice(index, 1);
};

// Hitung total debet dan kredit secara real-time
const totalDebit = computed(() => {
    return form.value.items
        .filter(item => item.type === 'debit')
        .reduce((acc, curr) => acc + (Number(curr.amount) || 0), 0);
});

const totalCredit = computed(() => {
    return form.value.items
        .filter(item => item.type === 'credit')
        .reduce((acc, curr) => acc + (Number(curr.amount) || 0), 0);
});

const isBalanced = computed(() => {
    return Math.abs(totalDebit.value - totalCredit.value) < 0.01 && totalDebit.value > 0;
});

const submitJournal = async () => {
    if (!form.value.description) {
        toast.error("Keterangan jurnal wajib diisi.");
        return;
    }
    if (!isBalanced.value) {
        toast.error("Total Debet dan Kredit harus seimbang (balance)!");
        return;
    }

    isSubmitting.value = true;
    try {
        const response = await axios.post('/api/finance/journal-entry', form.value);
        if (response.data.success) {
            toast.success("Jurnal umum berhasil ditambahkan!");
            router.visit('/finance/journals'); // Sesuaikan rute halaman index jurnal Anda
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || "Terjadi kesalahan saat menyimpan jurnal.");
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div class="p-4 sm:p-6 max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between border-b pb-4 border-border/60">
            <div class="flex items-center gap-3">
                <button @click="router.visit('/finance/journals')" class="p-2 bg-muted rounded-xl text-foreground hover:bg-muted/80 transition-colors">
                    <ArrowLeft class="h-4 w-4" />
                </button>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-foreground">Tambah Jurnal Umum Baru</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">Catat transaksi finansial berpasangan secara manual.</p>
                </div>
            </div>
        </div>

        <!-- Form Input Utama -->
        <div class="bg-card border border-border/60 rounded-2xl p-6 shadow-sm space-y-5 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="font-bold text-muted-foreground uppercase text-[10px]">Tanggal Buku</label>
                    <input
                        v-model="form.entry_date"
                        type="date"
                        class="w-full bg-background border border-border rounded-xl px-3 py-2.5 focus:outline-none font-medium"
                    />
                </div>
                <div class="space-y-1.5 sm:col-span-2">
                    <label class="font-bold text-muted-foreground uppercase text-[10px]">Keterangan Transaksi</label>
                    <input
                        v-model="form.description"
                        type="text"
                        placeholder="Contoh: Pembayaran Biaya Listrik Bulan Ini"
                        class="w-full bg-background border border-border rounded-xl px-3 py-2.5 focus:outline-none font-medium"
                    />
                </div>
            </div>

            <!-- Tabel Baris Akun Debet / Kredit -->
            <div class="space-y-3 pt-3 border-t border-border/60">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-foreground text-sm">Rincian Akun Jurnal</h3>
                    <button @click="addRow" type="button" class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary/20 rounded-lg font-bold transition-all">
                        <Plus class="h-3.5 w-3.5" /> Tambah Baris
                    </button>
                </div>

                <div class="space-y-2">
                    <div v-for="(item, index) in form.items" :key="index" class="flex flex-col sm:flex-row items-center gap-2.5 bg-muted/20 p-3 rounded-xl border border-border/40">
                        <!-- Pilihan Akun -->
                        <div class="flex-1 w-full">
                            <select v-model="item.account_id" class="w-full bg-background border border-border rounded-lg px-3 py-2 focus:outline-none font-medium">
                                <option value="" disabled>-- Pilih Rekening Akun --</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                    [{{ acc.code }}] {{ acc.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Pilihan Posisi (Debit / Kredit) -->
                        <div class="w-full sm:w-36">
                            <select v-model="item.type" class="w-full bg-background border border-border rounded-lg px-3 py-2 focus:outline-none font-bold uppercase">
                                <option value="debit">Debet</option>
                                <option value="credit">Kredit</option>
                            </select>
                        </div>

                        <!-- Nominal Jumlah -->
                        <div class="w-full sm:w-44">
                            <input
                                v-model.number="item.amount"
                                type="number"
                                min="0"
                                step="any"
                                placeholder="Nominal Rp"
                                class="w-full bg-background border border-border rounded-lg px-3 py-2 focus:outline-none font-mono font-bold text-right"
                            />
                        </div>

                        <!-- Hapus Baris -->
                        <button @click="removeRow(index)" type="button" class="p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-colors">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ringkasan Total & Status Keseimbangan -->
            <div class="p-4 rounded-xl border flex flex-col sm:flex-row items-center justify-between gap-4 mt-4" :class="isBalanced ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300'">
                <div class="space-y-0.5 text-center sm:text-left">
                    <p class="font-bold">Total Debet: Rp {{ totalDebit.toLocaleString('id-ID') }} | Total Kredit: Rp {{ totalCredit.toLocaleString('id-ID') }}</p>
                    <p class="text-[11px] font-medium opacity-90">
                        {{ isBalanced ? '✓ Jurnal seimbang dan siap disimpan.' : '⚠ Total Debet dan Kredit belum seimbang (Unbalanced).' }}
                    </p>
                </div>

                <button
                    @click="submitJournal"
                    :disabled="!isBalanced || isSubmitting"
                    class="w-full sm:w-auto px-6 py-2.5 bg-primary text-primary-foreground font-black rounded-xl shadow-sm transition-all hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer"
                >
                    <Save class="h-4 w-4" /> {{ isSubmitting ? 'Menyimpan...' : 'Simpan Jurnal' }}
                </button>
            </div>
        </div>
    </div>
</template>
