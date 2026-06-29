<script setup lang="ts">
import { ref } from 'vue';

interface JournalItem {
    account_code: string;
    account_name: string;
    type: 'debit' | 'credit';
    amount: number;
}

interface JournalEntry {
    id: string;
    entry_date: string;
    description: string;
    total_amount: number;
    items: JournalItem[];
}

const journals = ref<JournalEntry[]>([
    {
        id: 'j1',
        entry_date: '2026-06-29',
        description: 'Pendapatan penjualan POS #ORD-20260629-A76F',
        total_amount: 150000,
        items: [
            { account_code: '1-1000', account_name: 'Kas Utama / POS', type: 'debit', amount: 150000 },
            { account_code: '2-1000', account_name: 'Pendapatan Penjualan POS', type: 'credit', amount: 150000 },
        ]
    },
    {
        id: 'j2',
        entry_date: '2026-06-29',
        description: 'Alokasi HPP bahan baku terpakai untuk POS #ORD-20260629-A76F',
        total_amount: 45000,
        items: [
            { account_code: '5-1000', account_name: 'Harga Pokok Penjualan (HPP)', type: 'debit', amount: 45000 },
            { account_code: '1-2000', account_name: 'Persediaan Bahan Baku', type: 'credit', amount: 45000 },
        ]
    }
]);

const formatCurrency = (val: number) => 'Rp ' + val.toLocaleString('id-ID');
</script>

<template>
    <div class="p-6 space-y-6">
        <div class="border-b pb-5 border-border/60">
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Buku Jurnal Umum</h1>
            <p class="text-xs text-muted-foreground mt-1">
                Riwayat catatan pembukuan transaksi finansial berpasangan (*double-entry ledger*) secara kronologis.
            </p>
        </div>

        <div class="flex items-center gap-2.5 p-3 bg-muted/20 rounded-xl border border-border/60 text-xs shadow-sm">
            <span class="font-bold text-muted-foreground">Periode Buku:</span>
            <input type="date" class="bg-background border rounded px-2 py-1 focus:outline-none h-8 font-medium" />
            <span class="text-muted-foreground font-semibold">s/d</span>
            <input type="date" class="bg-background border rounded px-2 py-1 focus:outline-none h-8 font-medium" />
        </div>

        <div class="rounded-xl border border-border/60 overflow-hidden bg-card shadow-sm">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-muted/40 border-b border-border/60 font-semibold text-muted-foreground uppercase tracking-wider">
                        <th class="px-4 py-3 w-32">Tanggal Buku</th>
                        <th class="px-4 py-3 w-32">Kode Rekening</th>
                        <th class="px-4 py-3">Keterangan Akun Pembukuan</th>
                        <th class="px-4 py-3 w-36 text-right">Debet</th>
                        <th class="px-4 py-3 w-36 text-right">Kredit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    <template v-for="j in journals" :key="j.id">
                        <tr class="bg-muted/20 font-medium text-foreground/90">
                            <td class="px-4 py-2.5 font-mono font-semibold">{{ j.entry_date }}</td>
                            <td class="px-4 py-2.5" colspan="2">
                                <span class="font-bold text-foreground text-[12px]">{{ j.description }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-extrabold text-foreground" colspan="2">
                                {{ formatCurrency(j.total_amount) }}
                            </td>
                        </tr>
                        <tr v-for="(item, idx) in j.items" :key="idx" class="hover:bg-muted/5 transition-colors">
                            <td></td>
                            <td class="px-4 py-2 font-mono text-muted-foreground font-semibold">{{ item.account_code }}</td>
                            <td class="px-4 py-2">
                                <span :class="[
                                    'font-medium text-[11px]',
                                    item.type === 'credit' ? 'pl-8 text-purple-600 dark:text-purple-400 font-bold' : 'text-blue-600 dark:text-blue-400 font-bold'
                                ]">
                                    {{ item.account_name }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right font-bold text-foreground">
                                {{ item.type === 'debit' ? formatCurrency(item.amount) : '-' }}
                            </td>
                            <td class="px-4 py-2 text-right font-bold text-foreground">
                                {{ item.type === 'credit' ? formatCurrency(item.amount) : '-' }}
                            </td>
                        </tr>
                    </template>
                    <tr v-if="journals.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground italic">Belum ada aktivitas jurnal masuk untuk periode ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>