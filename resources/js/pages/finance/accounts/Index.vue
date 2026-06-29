<script setup lang="ts">
import { ref, computed } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';

// Definisikan tipe data untuk keamanan type-checking
export interface Account {
    id: string;
    account_number: string;
    code: string;
    name: string;
    normal_balance: 'debit' | 'credit';
    is_active: boolean;

    category: {
        id: string;
        name: string;
    };
}
// State data master akun
import { onMounted, watch } from 'vue';
import { useAccount } from '@/composables/useAccount';

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

const getCategoryLabel = (category: string) =>
    categoryLabels[category] ?? '-';

// Filter data pencarian & kategori
const filteredAccounts = computed(() => {
    return accounts.value.filter(acc => {
        const matchesSearch = acc.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || acc.code.includes(searchQuery.value);
        const matchesCategory = selectedCategoryFilter.value === '' || acc.category === selectedCategoryFilter.value;
        return matchesSearch && matchesCategory;
    });
});
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
                <Button size="sm" class="h-9 text-xs font-semibold">+ Tambah Akun Baru</Button>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-2.5 p-3 bg-muted/20 rounded-xl border border-border/60 shadow-sm">
            <div class="w-full sm:flex-1">
                <Input v-model="searchQuery" placeholder="Cari kode atau nama akun..." class="w-full text-xs h-9" />
            </div>
            <div class="w-full sm:w-56 shrink-0">
                <select v-model="selectedCategoryFilter" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    <option value="">Semua Kategori Akun</option>
                    <option v-for="(label, val) in categories" :key="val" :value="val">({{ val }}) {{ label }}</option>
                </select>
            </div>
        </div>

        <div class="rounded-xl border border-border/60 overflow-hidden bg-card shadow-sm">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-muted/40 border-b border-border/60 font-semibold text-muted-foreground uppercase tracking-wider">
                        <th class="px-4 py-3 w-36">Kode Akun</th>
                        <th class="px-4 py-3">Nama Transaksi / Rekening</th>
                        <th class="px-4 py-3 w-44">Kelompok Kategori</th>
                        <th class="px-4 py-3 w-32 text-center">Saldo Normal</th>
                        <th class="px-4 py-3 w-28 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    <tr v-for="acc in filteredAccounts" :key="acc.id" class="hover:bg-muted/10 transition-colors">
                        <td class="px-4 py-3 font-mono font-bold text-foreground">{{ acc.code }}</td>
                        <td class="px-4 py-3 font-medium text-foreground">{{ acc.name }}</td>
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
                    </tr>
                    <tr v-if="filteredAccounts.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground italic">Data rekening tidak ditemukan.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>