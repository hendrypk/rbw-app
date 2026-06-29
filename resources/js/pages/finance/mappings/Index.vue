<script setup lang="ts">
import { ref } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import MappingModal from './MappingModal.vue';

interface Mapping {
    id: string;
    transaction_event: string;
    debit_code: string;
    debit_name: string;
    credit_code: string;
    credit_name: string;
    template: string;
}


const showModal = ref(false);
const selectedMapping = ref<Mapping | null>(null);

const editMapping = (mapping: Mapping) => {
    selectedMapping.value = mapping;
    showModal.value = true;
};

const saveMapping = (updated: Mapping) => {
    const index = mappings.value.findIndex(i => i.id === updated.id);

    if (index !== -1) {
        mappings.value[index] = updated;
    }
};

const mappings = ref<Mapping[]>([
    { id: '1', transaction_event: 'purchase_received_cash', debit_code: '1-2000', debit_name: 'Persediaan Bahan Baku', credit_code: '1-1000', credit_name: 'Kas Utama / POS', template: 'Penerimaan inventory tunai atas PO #{{po_number}}' },
    { id: '2', transaction_event: 'purchase_received_credit', debit_code: '1-2000', debit_name: 'Persediaan Bahan Baku', credit_code: '3-1000', credit_name: 'Utang Dagang Supplier', template: 'Penerimaan inventory kredit atas PO #{{po_number}}' },
    { id: '3', transaction_event: 'pos_sales_revenue', debit_code: '1-1000', debit_name: 'Kas Utama / POS', credit_code: '2-1000', credit_name: 'Pendapatan Penjualan POS', template: 'Pendapatan penjualan POS #{{order_number}}' },
    { id: '4', transaction_event: 'pos_sales_hpp', debit_code: '5-1000', debit_name: 'Harga Pokok Penjualan (HPP)', credit_code: '1-2000', credit_name: 'Persediaan Bahan Baku', template: 'Alokasi HPP bahan baku terpakai untuk POS #{{order_number}}' },
    { id: '5', transaction_event: 'inventory_adjustment_waste', debit_code: '5-3000', debit_name: 'Beban Kerugian Selisih Persediaan (Waste)', credit_code: '1-2000', credit_name: 'Persediaan Bahan Baku', template: 'Penyesuaian stok rusak/kadaluarsa: {{material_name}}' }
]);
</script>

<template>
    <div class="p-6 space-y-6">
        <div class="border-b pb-5 border-border/60">
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Account Mapping</h1>
            <p class="text-xs text-muted-foreground mt-1">
                Atur relasi otomasi jurnal berpasangan dari setiap aktivitas pemicu (*system trigger*) agar pembukuan otomatis berjalan.
            </p>
        </div>

        <div class="p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl text-xs text-muted-foreground leading-relaxed">
            💡 <span class="font-semibold text-foreground">Catatan Sistem:</span> Pemetaan akun di bawah ini mengunci alur Debet dan Kredit secara otomatis saat sistem mendeteksi transaksi lunas di POS, penerimaan PO Gudang, ataupun Opname bahan rusak.
        </div>

        <div class="rounded-xl border border-border/60 overflow-hidden bg-card shadow-sm">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-muted/40 border-b border-border/60 font-semibold text-muted-foreground uppercase tracking-wider">
                        <th class="px-4 py-3">Event Transaksi (Triggers)</th>
                        <th class="px-4 py-3">Akun Masuk (Debet)</th>
                        <th class="px-4 py-3">Akun Keluar (Kredit)</th>
                        <th class="px-4 py-3 hidden lg:table-cell">Template Keterangan Jurnal</th>
                        <th class="px-4 py-3 w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    <tr v-for="map in mappings" :key="map.id" class="hover:bg-muted/10 transition-colors">
                        <td class="px-4 py-4">
                            <span class="font-mono bg-muted/60 dark:bg-muted/30 px-2 py-1 rounded text-foreground font-semibold text-[11px]">
                                {{ map.transaction_event }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <div class="font-bold text-blue-600 dark:text-blue-400 font-mono">{{ map.debit_code }}</div>
                            <div class="text-[11px] text-muted-foreground mt-0.5">{{ map.debit_name }}</div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="font-bold text-purple-600 dark:text-purple-400 font-mono">{{ map.credit_code }}</div>
                            <div class="text-[11px] text-muted-foreground mt-0.5">{{ map.credit_name }}</div>
                        </td>
                        <td class="px-4 py-4 text-muted-foreground italic hidden lg:table-cell max-w-xs truncate">
                            {{ map.template }}
                        </td>
                        <td class="px-4 py-4 text-center">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs"
                                @click="editMapping(map)"
                            >
                                Edit Aturan
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <MappingModal
        :show="showModal"
        :mapping="selectedMapping"
        @close="showModal = false"
        @save="saveMapping"
    />
</template>