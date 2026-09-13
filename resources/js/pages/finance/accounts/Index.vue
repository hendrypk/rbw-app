<script setup lang="ts">
import { ref, computed, onMounted, watch, reactive } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useAccount, type Account } from '@/composables/useAccount';
import axios from 'axios';
import { toast } from 'vue-sonner';

import Select from '@/components/ui/select/Select.vue';
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue';
import SelectValue from '@/components/ui/select/SelectValue.vue';
import SelectContent from '@/components/ui/select/SelectContent.vue';
import SelectItem from '@/components/ui/select/SelectItem.vue';
import Modal from '@/components/ui/Modal.vue';

// ==========================================
// COMPOSABLES & STATE MANAGEMENT
// ==========================================
const { accounts, loading, fetchAccounts, updateOpeningBalances } = useAccount();

const searchQuery = ref('');
const selectedCategoryFilter = ref('');

onMounted(() => {
    fetchAccounts();
});

watch([searchQuery, selectedCategoryFilter], () => {
    fetchAccounts({
        search: searchQuery.value,
        category: selectedCategoryFilter.value,
    });
});

const categoryLabels: Record<string, string> = {
    '1': 'Kas & Bank',
    '2': 'Pendapatan',
    '3': 'Harga Pokok Pendapatan',
    '4': 'Kewajiban',
    '5': 'Ekuitas',
    '6': 'Biaya',
};

const getCategoryLabel = (category: string) => categoryLabels[category] ?? '-';

// Filter data di sisi client
const filteredAccounts = computed(() => {
    return accounts.value.filter(acc => {
        const matchesSearch = acc.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             (acc.code && acc.code.includes(searchQuery.value));
        const matchesCategory = selectedCategoryFilter.value === '' || acc.category === selectedCategoryFilter.value;
        return matchesSearch && matchesCategory;
    });
});

// ==========================================
// MODAL AKUN (CREATE / EDIT)
// ==========================================
const isModalOpen = ref(false);
const isEditMode = ref(false);
const submitLoading = ref(false);

const defaultForm: Account = {
    category: '1',
    account_number: '',
    name: '',
    normal_balance: 'debit',
    balance: 0,
    is_active: true
};

const form = reactive<Account>({ ...defaultForm });

watch(() => form.category, (newCategory) => {
    if (!isEditMode.value) {
        form.normal_balance = ['1', '5'].includes(newCategory) ? 'debit' : 'credit';
    }
});

const openAddModal = () => {
    isEditMode.value = false;
    Object.assign(form, defaultForm);
    isModalOpen.value = true;
};

const openEditModal = (account: Account) => {
    isEditMode.value = true;
    let rawNumber = account.account_number;
    if (!rawNumber && account.code) {
        rawNumber = account.code.split('-')[1] || '';
    }

    Object.assign(form, {
        id: account.id,
        category: account.category,
        account_number: rawNumber,
        name: account.name,
        normal_balance: account.normal_balance,
        balance: account.balance ?? 0,
        is_active: account.is_active
    });
    isModalOpen.value = true;
};

const handleSubmit = async () => {
    if (!form.account_number || !form.name) {
        toast.error('Mohon lengkapi seluruh field yang wajib diisi!');
        return;
    }

    try {
        submitLoading.value = true;
        if (isEditMode.value && form.id) {
            await axios.put(`/api/finance/accounts/${form.id}`, form);
            toast.success('Rekening Akun berhasil diperbarui!');
        } else {
            await axios.post('/api/finance/accounts', form);
            toast.success('Rekening Akun baru berhasil didaftarkan!');
        }
        
        isModalOpen.value = false;
        fetchAccounts();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Gagal menyimpan perubahan struktur COA.');
    } finally {
        submitLoading.value = false;
    }
};

// ==========================================
// MODAL SALDO AWAL (OPENING BALANCE)
// ==========================================
const isOpeningModalOpen = ref(false);
const openingProcessing = ref(false);
const openingDate = ref('2026-01-01');
const openingBalancesForm = ref<{ id: string; code: string; name: string; opening_balance: number; normal_balance: string }[]>([]);

const openOpeningModal = () => {
    openingBalancesForm.value = accounts.value.map(acc => ({
        id: acc.id!,
        code: acc.code || '',
        name: acc.name,
        opening_balance: Number(acc.opening_balance || 0),
        normal_balance: acc.normal_balance
    }));
    isOpeningModalOpen.value = true;
};

const totalOpeningDebit = computed(() => {
    return openingBalancesForm.value
        .filter(item => item.normal_balance === 'debit')
        .reduce((sum, item) => sum + Number(item.opening_balance || 0), 0);
});

const totalOpeningCredit = computed(() => {
    return openingBalancesForm.value
        .filter(item => item.normal_balance === 'credit')
        .reduce((sum, item) => sum + Number(item.opening_balance || 0), 0);
});

const isOpeningBalanced = computed(() => {
    return Math.abs(totalOpeningDebit.value - totalOpeningCredit.value) < 0.01;
});

const submitOpeningBalances = async () => {
    openingProcessing.value = true;
    try {
        await updateOpeningBalances(
            openingDate.value, 
            openingBalancesForm.value.map(item => ({ id: item.id, opening_balance: item.opening_balance }))
        );
        toast.success('Saldo awal berhasil disimpan dan disinkronkan!');
        isOpeningModalOpen.value = false;
        fetchAccounts();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Gagal menyimpan saldo awal.');
    } finally {
        openingProcessing.value = false;
    }
};

// ==========================================
// REKAPITULASI TABEL UTAMA
// ==========================================
const totalDebitBalance = computed(() => {
    return filteredAccounts.value
        .filter(acc => acc.normal_balance === 'debit')
        .reduce((sum, acc) => sum + Number(acc.balance || 0), 0);
});

const totalCreditBalance = computed(() => {
    return filteredAccounts.value
        .filter(acc => acc.normal_balance === 'credit')
        .reduce((sum, acc) => sum + Number(acc.balance || 0), 0);
});
</script>

<template>
    <div class="p-6 sm:p-10 max-w-7xl mx-auto space-y-8 animate-in fade-in duration-300">
        <!-- Header Bergaya Apple -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-foreground">Chart of Accounts</h1>
                <p class="text-xs sm:text-sm text-muted-foreground mt-1">
                    Standarisasi rekening dan rekapitulasi saldo berjalan sesuai struktur PSAK.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button @click="openOpeningModal" variant="outline" class="h-10 px-4 rounded-xl text-xs font-semibold border-border/80 shadow-2xs cursor-pointer">
                    Atur Saldo Awal
                </Button>
                <Button @click="openAddModal" class="h-10 px-4 rounded-xl text-xs font-semibold bg-primary text-primary-foreground shadow-sm hover:opacity-95 transition-all cursor-pointer">
                    + Tambah Akun Baru
                </Button>
            </div>
        </div>

        <!-- Filter & Search Bar Modern -->
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="relative w-full sm:flex-1">
                <Input v-model="searchQuery" placeholder="Cari berdasarkan nama atau kode akun..." class="w-full h-11 pl-4 rounded-2xl border-border/80 bg-card text-xs font-medium shadow-2xs focus:ring-2 focus:ring-primary/20" />
            </div>
            <div class="w-full sm:w-64 shrink-0">
                <Select v-model="form.category" :disabled="isEditMode">
                        <SelectTrigger class="w-full h-11 rounded-2xl border-border/80 bg-background px-4 text-xs font-medium text-foreground shadow-2xs disabled:opacity-60">
                            <SelectValue placeholder="Pilih Kategori" />
                        </SelectTrigger>
                        <SelectContent class="rounded-2xl">
                            <SelectItem v-for="(label, val) in categoryLabels" :key="val" :value="val" class="text-xs font-medium">
                                ({{ val }}) {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="py-24 text-center text-xs text-muted-foreground font-medium animate-pulse">
            Memuat data keuangan...
        </div>

        <!-- Tabel Clean & Clear Bergaya Apple -->
        <div v-else class="rounded-3xl border border-border/60 bg-card shadow-xs overflow-hidden">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-secondary/40 text-muted-foreground font-semibold uppercase tracking-wider border-b border-border/60">
                        <th class="px-6 py-4 w-36">Kode Akun</th>
                        <th class="px-6 py-4">Nama Rekening / Transaksi</th>
                        <th class="px-6 py-4 w-48">Kelompok Kategori</th>
                        <th class="px-6 py-4 w-44 text-right">Saldo Debit</th>
                        <th class="px-6 py-4 w-44 text-right">Saldo Kredit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    <tr v-for="acc in filteredAccounts" :key="acc.id" class="hover:bg-secondary/20 transition-colors group">
                        <td class="px-6 py-4 font-mono font-bold text-foreground">{{ acc.code }}</td>
                        <td class="px-6 py-4 font-medium text-foreground">
                            <button @click="openEditModal(acc)" class="font-semibold text-primary hover:underline text-left cursor-pointer">
                                {{ acc.name }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-muted-foreground font-medium">
                            {{ getCategoryLabel(acc.category) }}
                        </td>
                        <td class="px-6 py-4 font-mono text-right font-semibold text-foreground">
                            {{ acc.normal_balance === 'debit' ? `Rp ${Number(acc.balance || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '-' }}
                        </td>
                        <td class="px-6 py-4 font-mono text-right font-semibold text-rose-600 dark:text-rose-400">
                            {{ acc.normal_balance === 'credit' ? `Rp ${Number(acc.balance || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '-' }}
                        </td>
                    </tr>
                    <tr v-if="filteredAccounts.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-muted-foreground italic font-medium">Data rekening tidak ditemukan.</td>
                    </tr>
                </tbody>
                <!-- Footer Rekapitulasi Berimbang -->
                <tfoot class="bg-secondary/50 border-t border-border/80 font-bold">
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-right text-foreground uppercase tracking-wider text-[11px]">Total Keseluruhan:</td>
                        <td class="px-6 py-4 font-mono text-right text-foreground">
                            Rp {{ totalDebitBalance.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </td>
                        <td class="px-6 py-4 font-mono text-right text-rose-600 dark:text-rose-400">
                            Rp {{ totalCreditBalance.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Modal Form Clean Modern -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-200">
            <div class="w-full max-w-md overflow-hidden rounded-3xl border border-border bg-card text-card-foreground shadow-2xl">
                
                <div class="px-6 py-5 border-b border-border/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold tracking-tight text-foreground">
                            {{ isEditMode ? 'Edit Struktur Rekening' : 'Tambah Rekening Baru' }}
                        </h3>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Konfigurasi parameter rekening buku besar akuntansi.</p>
                    </div>
                    <button @click="isModalOpen = false" class="size-8 rounded-full bg-secondary flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors cursor-pointer">✕</button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="space-y-1.5">
                        <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Kategori Akun <span class="text-destructive">*</span></label>
                        <Select v-model="form.category" :disabled="isEditMode">
                            <SelectTrigger class="w-full h-11 rounded-2xl border-border/80 bg-background px-4 text-xs font-medium text-foreground shadow-2xs disabled:opacity-60">
                                <SelectValue placeholder="Pilih Kategori" />
                            </SelectTrigger>
                            <SelectContent class="rounded-2xl">
                                <SelectItem v-for="(label, val) in categoryLabels" :key="val" :value="val" class="text-xs font-medium">
                                    ({{ val }}) {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Nomor Seri Akun <span class="text-destructive">*</span></label>
                        <div class="flex items-center gap-2">
                            <div class="h-11 px-4 bg-secondary border border-border/80 rounded-2xl flex items-center font-mono font-bold text-muted-foreground select-none">
                                {{ form.category }}-
                            </div>
                            <Input v-model="form.account_number" type="text" placeholder="1000" class="h-11 rounded-2xl font-mono font-bold text-foreground shadow-2xs" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Nama Rekening <span class="text-destructive">*</span></label>
                        <Input v-model="form.name" type="text" placeholder="Misal: Kas Operasional" class="h-11 rounded-2xl font-medium text-foreground shadow-2xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div class="space-y-1.5">
                            <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Saldo Normal</label>
                            <div class="flex h-11 p-1 bg-secondary rounded-2xl border border-border/80">
                                <button type="button" @click="form.normal_balance = 'debit'" :class="['flex-1 text-[10px] font-bold uppercase rounded-xl transition-all cursor-pointer', form.normal_balance === 'debit' ? 'bg-background text-primary shadow-xs' : 'text-muted-foreground']">Debit</button>
                                <button type="button" @click="form.normal_balance = 'credit'" :class="['flex-1 text-[10px] font-bold uppercase rounded-xl transition-all cursor-pointer', form.normal_balance === 'credit' ? 'bg-background text-primary shadow-xs' : 'text-muted-foreground']">Kredit</button>
                            </div>
                        </div>
                        
                        <div class="space-y-1.5">
                            <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Status</label>
                            <div class="flex h-11 p-1 bg-secondary rounded-2xl border border-border/80">
                                <button type="button" @click="form.is_active = true" :class="['flex-1 text-[10px] font-bold rounded-xl transition-all cursor-pointer', form.is_active ? 'bg-background text-emerald-600 shadow-xs' : 'text-muted-foreground']">Aktif</button>
                                <button type="button" @click="form.is_active = false" :class="['flex-1 text-[10px] font-bold rounded-xl transition-all cursor-pointer', !form.is_active ? 'bg-background text-amber-600 shadow-xs' : 'text-muted-foreground']">Nonaktif</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-secondary/30 border-t border-border/60 flex items-center justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-10 px-4 rounded-xl text-xs font-semibold cursor-pointer" @click="isModalOpen = false" :disabled="submitLoading">
                        Batal
                    </Button>
                    <Button type="button" size="sm" class="h-10 px-5 rounded-xl text-xs font-semibold cursor-pointer" @click="handleSubmit" :disabled="submitLoading">
                        {{ submitLoading ? 'Menyimpan...' : 'Simpan Akun' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
    <Modal :show="isOpeningModalOpen" title="Pengaturan Saldo Awal Rekening (Opening Balance)" maxWidth="max-w-4xl" @close="isOpeningModalOpen = false">
        <div class="space-y-4 text-xs">
            <p class="text-muted-foreground">
                Masukkan saldo awal periode. Pastikan Total Debit dan Total Kredit bernilai sama (seimbang) agar sistem dapat menyimpannya.
            </p>

            <div class="flex items-center gap-3 p-3 bg-secondary/40 rounded-2xl border border-border/60">
                <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px] shrink-0">Tanggal Efektif Saldo Awal:</label>
                <input type="date" v-model="openingDate" class="h-9 px-3 rounded-xl border border-border bg-background text-xs font-mono font-bold text-foreground" />
            </div>

            <div class="rounded-2xl border border-border/60 overflow-hidden max-h-[50vh] overflow-y-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-secondary/60 text-muted-foreground font-semibold uppercase tracking-wider text-[10px] border-b border-border/60 sticky top-0 z-10">
                            <th class="px-4 py-3 w-32">Kode</th>
                            <th class="px-4 py-3">Nama Rekening</th>
                            <th class="px-4 py-3 w-28 text-center">Saldo Normal</th>
                            <th class="px-4 py-3 w-48 text-right">Nominal Saldo Awal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-for="(item, idx) in openingBalancesForm" :key="item.id" class="hover:bg-secondary/20 transition-colors">
                            <td class="px-4 py-3 font-mono font-bold text-foreground">{{ item.code }}</td>
                            <td class="px-4 py-3 font-medium text-foreground">{{ item.name }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-secondary text-muted-foreground">
                                    {{ item.normal_balance }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <input 
                                    type="number" 
                                    v-model.number="item.opening_balance" 
                                    step="any" 
                                    class="w-full h-9 px-3 text-right font-mono font-bold rounded-xl border border-border bg-background text-foreground shadow-2xs" 
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Baris Rekapitulasi Total & Indikator Keseimbangan -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 bg-secondary/40 rounded-2xl border border-border/60">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 font-mono">
                        <span class="text-muted-foreground font-bold">Total Debit:</span>
                        <span class="font-extrabold text-foreground">Rp {{ totalOpeningDebit.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                    </div>
                    <div class="flex items-center gap-2 font-mono">
                        <span class="text-muted-foreground font-bold">Total Kredit:</span>
                        <span class="font-extrabold text-rose-600 dark:text-rose-400">Rp {{ totalOpeningCredit.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                    </div>
                </div>
                <div>
                    <span :class="[
                        'px-3 py-1.5 rounded-xl text-[11px] font-bold uppercase tracking-wider inline-flex items-center gap-1.5',
                        isOpeningBalanced 
                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' 
                            : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 animate-pulse'
                    ]">
                        <span :class="['size-1.5 rounded-full', isOpeningBalanced ? 'bg-emerald-500' : 'bg-rose-500']"></span>
                        {{ isOpeningBalanced ? 'Balance (Seimbang)' : 'Belum Balance' }}
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border/60">
                <Button type="button" variant="outline" size="sm" class="h-10 px-4 rounded-xl text-xs font-semibold cursor-pointer" @click="isOpeningModalOpen = false" :disabled="openingProcessing">
                    Batal
                </Button>
                <Button 
                    type="button" 
                    size="sm" 
                    class="h-10 px-5 rounded-xl text-xs font-semibold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" 
                    @click="submitOpeningBalances" 
                    :disabled="openingProcessing || !isOpeningBalanced"
                >
                    {{ openingProcessing ? 'Menyimpan...' : 'Simpan Saldo Awal' }}
                </Button>
            </div>
        </div>
    </Modal>
</template>