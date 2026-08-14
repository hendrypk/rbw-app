<script setup lang="ts">
import { ref, computed, onMounted, watch, reactive } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useAccount } from '@/composables/useAccount';
import axios from 'axios';
import { toast } from 'vue-sonner';

// ==========================================
// INTERFACE & TIPE DATA (PSAK COMPLIANT)
// ==========================================
export interface Account {
    id?: string;
    category: string;
    account_number: string;
    code?: string;
    name: string;
    normal_balance: 'debit' | 'credit';
    balance: string;
    is_active: boolean;
}

const searchQuery = ref('');
const selectedCategoryFilter = ref('');

const {
    accounts,
    loading,
    fetchAccounts,
} = useAccount();

onMounted(() => {
    fetchAccounts();
});

watch(
    [searchQuery, selectedCategoryFilter],
    () => {
        fetchAccounts({
            search: searchQuery.value,
            category: selectedCategoryFilter.value,
        });
    }
);

const categoryLabels: Record<string, string> = {
    '1': 'Kas & Bank',
    '2': 'Pendapatan',
    '3': 'Kewajiban',
    '4': 'Ekuitas',
    '5': 'Biaya',
};

const getCategoryLabel = (category: string) => categoryLabels[category] ?? '-';

// Filter pencarian & kategori di sisi client
const filteredAccounts = computed(() => {
    return accounts.value.filter(acc => {
        const matchesSearch = acc.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             (acc.code && acc.code.includes(searchQuery.value));
        const matchesCategory = selectedCategoryFilter.value === '' || acc.category === selectedCategoryFilter.value;
        return matchesSearch && matchesCategory;
    });
});

// ==========================================
// STATE & LOGIKA MODAL FORM (REACTIVE ENGINE)
// ==========================================
const isModalOpen = ref(false);
const isEditMode = ref(false);
const submitLoading = ref(false);

const defaultForm: Account = {
    id: undefined,
    category: '1',
    account_number: '',
    name: '',
    normal_balance: 'debit',
    is_active: true
};

// Menggunakan reactive untuk menghindari penulisan '.value' yang memicu crash transpilasi TS
const form = reactive<Account>({ ...defaultForm });

// Otomatisasi deteksi saldo normal berdasarkan baseline standard akuntansi PSAK
watch(() => form.category, (newCategory) => {
    if (!isEditMode.value) {
        if (['1', '5'].includes(newCategory)) {
            form.normal_balance = 'debit';
        } else {
            form.normal_balance = 'credit';
        }
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
        const errMsg = error.response?.data?.message || 'Gagal menyimpan perubahan struktur COA.';
        toast.error(errMsg);
    } finally {
        submitLoading.value = false;
    }
};
</script>

<template>
    <div class="p-6 space-y-6">
        <div class="border-b pb-5 border-border/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Chart of Accounts (COA)</h1>
                <p class="text-xs text-muted-foreground mt-1">
                    Daftar standarisasi rekening akuntansi manufaktur berdasarkan aturan PSAK.
                </p>
            </div>
            <div class="shrink-0">
                <Button size="sm" class="h-9 text-xs font-semibold shadow-sm" @click="openAddModal">
                    + Tambah Akun Baru
                </Button>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-2.5 p-3 bg-muted/20 rounded-xl border border-border/60 shadow-sm">
            <div class="w-full sm:flex-1">
                <Input v-model="searchQuery" placeholder="Cari kode atau nama akun..." class="w-full text-xs h-9" />
            </div>
            <div class="w-full sm:w-56 shrink-0">
                <select v-model="selectedCategoryFilter" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    <option value="">Semua Kategori Akun</option>
                    <option v-for="(label, val) in categoryLabels" :key="val" :value="val">({{ val }}) {{ label }}</option>
                </select>
            </div>
        </div>

        <div v-if="loading" class="py-20 text-center text-xs text-muted-foreground font-medium animate-pulse">
            Memuat data akuntansi, mohon tunggu sebentar...
        </div>

        <div v-else class="rounded-xl border border-border/60 overflow-hidden bg-card shadow-sm">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-muted/40 border-b border-border/60 font-semibold text-muted-foreground uppercase tracking-wider">
                        <th class="px-4 py-3 w-36">Kode Akun</th>
                        <th class="px-4 py-3">Nama Transaksi / Rekening</th>
                        <th class="px-4 py-3 w-44">Kelompok Kategori</th>
                        <th class="px-4 py-3 w-32 text-center">Saldo Normal</th>
                        <th class="px-4 py-3 w-28 text-center">Status</th>
                        <th class="px-4 py-3 w-20 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    <tr v-for="acc in filteredAccounts" :key="acc.id" class="hover:bg-muted/10 transition-colors">
                        <td class="px-4 py-3 font-mono font-bold text-foreground">{{ acc.code }}</td><td class="px-4 py-3 font-medium text-foreground">
        <div>{{ acc.name }}</div>
        <div class="text-[11px] font-mono text-muted-foreground mt-0.5">
            Saldo: Rp {{ Number(acc.balance).toLocaleString('id-ID') }}
        </div>
    </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ getCategoryLabel(acc.category) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="[
                                'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                acc.normal_balance === 'debit' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400'
                            ]">
                                {{ acc.normal_balance }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1.5 font-medium" :class="acc.is_active ? 'text-emerald-600' : 'text-muted-foreground'">
                                <span class="w-1.5 h-1.5 rounded-full" :class="acc.is_active ? 'bg-emerald-500' : 'bg-muted'"></span>
                                {{ acc.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button @click="openEditModal(acc)" class="font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                Edit
                            </button>
                        </td>
                    </tr>
                    <tr v-if="filteredAccounts.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-muted-foreground italic">Data rekening tidak ditemukan.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 dark:bg-zinc-950/60 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden rounded-xl border border-border/80 bg-card text-card-foreground shadow-xl animate-in fade-in zoom-in-95 duration-150">
                
                <div class="px-5 py-4 border-b border-border/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold tracking-tight text-foreground">
                            {{ isEditMode ? 'Edit Struktur Rekening' : 'Tambah Rekening Baru' }}
                        </h3>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            {{ isEditMode ? 'Ubah parameter detail COA akuntansi' : 'Daftarkan struktur COA baru ke dalam ledger.' }}
                        </p>
                    </div>
                    <button @click="isModalOpen = false" class="text-muted-foreground hover:text-foreground text-sm font-semibold p-1">✕</button>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    
                    <div class="space-y-1.5">
                        <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Kelompok Kategori <span class="text-destructive">*</span></label>
                        <select v-model="form.category" :disabled="isEditMode" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60">
                            <option v-for="(label, val) in categoryLabels" :key="val" :value="val">({{ val }}) {{ label }}</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Nomor Seri Akun <span class="text-destructive">*</span></label>
                        <div class="flex items-center gap-2">
                            <div class="h-9 px-3 bg-muted border border-input rounded-md flex items-center font-mono font-bold text-muted-foreground select-none">
                                {{ form.category }}-
                            </div>
                            <Input v-model="form.account_number" type="text" placeholder="Contoh: 1000 atau 2001" class="font-mono h-9 font-bold tracking-wider text-foreground" />
                        </div>
                        <p class="text-[10px] text-muted-foreground">Ekor digit identifikasi unik rekening (tidak termasuk kode induk depan).</p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Nama Rekening / Akun <span class="text-destructive">*</span></label>
                        <Input v-model="form.name" type="text" placeholder="Misal: Kas Kecil Toko, Persediaan Bahan Baku" class="h-9 font-medium text-foreground" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-1">
                        <div class="space-y-1.5">
                            <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Saldo Normal</label>
                            <div class="flex h-9 p-0.5 bg-muted rounded-lg border border-input">
                                <button type="button" @click="form.normal_balance = 'debit'" :class="['flex-1 text-[10px] font-bold uppercase rounded-md tracking-wider transition-all', form.normal_balance === 'debit' ? 'bg-background text-blue-600 shadow-sm border border-border/40' : 'text-muted-foreground']">Debit</button>
                                <button type="button" @click="form.normal_balance = 'credit'" :class="['flex-1 text-[10px] font-bold uppercase rounded-md tracking-wider transition-all', form.normal_balance === 'credit' ? 'bg-background text-purple-600 shadow-sm border border-border/40' : 'text-muted-foreground']">Credit</button>
                            </div>
                        </div>
                        
                        <div class="space-y-1.5">
                            <label class="font-bold text-muted-foreground uppercase tracking-wider text-[10px]">Status Rekening</label>
                            <div class="flex h-9 p-0.5 bg-muted rounded-lg border border-input">
                                <button type="button" @click="form.is_active = true" :class="['flex-1 text-[10px] font-bold rounded-md transition-all', form.is_active ? 'bg-background text-emerald-600 shadow-sm border border-border/40' : 'text-muted-foreground']">Aktif</button>
                                <button type="button" @click="form.is_active = false" :class="['flex-1 text-[10px] font-bold rounded-md transition-all', !form.is_active ? 'bg-background text-amber-600 shadow-sm border border-border/40' : 'text-muted-foreground']">Nonaktif</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 bg-muted/30 border-t border-border/60 flex items-center justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-8 text-xs font-semibold" @click="isModalOpen = false" :disabled="submitLoading">
                        Batal
                    </Button>
                    <Button type="button" size="sm" class="h-8 text-xs font-semibold" @click="handleSubmit" :disabled="submitLoading">
                        {{ submitLoading ? 'Menyimpan...' : 'Simpan Akun' }}
                    </Button>
                </div>

            </div>
        </div>

    </div>
</template>