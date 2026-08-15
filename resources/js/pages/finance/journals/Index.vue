<script setup lang="ts">
import { useJournal } from '@/composables/useJournal'; // Sesuaikan path folder composable Anda

const { 
    journals, 
    isLoading, 
    startDate, 
    endDate, 
    handleFilterChange, 
    formatCurrency 
} = useJournal();
</script>

<template>
    <div class="p-6 space-y-6">
        <div class="border-b pb-5 border-border/60">
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Buku Jurnal Umum</h1>
            <p class="text-xs text-muted-foreground mt-1">
                Riwayat catatan pembukuan transaksi finansial berpasangan (*double-entry ledger*) secara kronologis.
            </p>
        </div>

        <div class="flex items-center gap-2.5 p-3 bg-muted/20 rounded-xl border border-border/60 text-xs shadow-sm w-fit">
            <span class="font-bold text-muted-foreground">Periode Buku:</span>
            <input 
                v-model="startDate" 
                @change="handleFilterChange"
                type="date" 
                class="bg-background border rounded px-2 py-1 focus:outline-none h-8 font-medium" 
            />
            <span class="text-muted-foreground font-semibold">s/d</span>
            <input 
                v-model="endDate" 
                @change="handleFilterChange"
                type="date" 
                class="bg-background border rounded px-2 py-1 focus:outline-none h-8 font-medium" 
            />
            
            <span v-if="isLoading" class="text-muted-foreground italic ml-2 flex items-center gap-1">
                <svg class="animate-spin h-3.5 w-3.5 text-blue-600" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Sinkronisasi data...
            </span>
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
                    
                    <tr v-if="journals.length === 0 && !isLoading">
                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground italic">
                            Belum ada aktivitas jurnal masuk untuk periode ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>