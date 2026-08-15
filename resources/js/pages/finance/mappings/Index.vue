<script setup lang="ts">
import { ref, onMounted } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import MappingModal from './MappingModal.vue';
import { useMapping, type Mapping } from '@/composables/useMapping';

// Menggunakan logika state terpusat dari composable
const { mappings, loading, fetchMappings, updateMapping } = useMapping();

const showModal = ref(false);
const selectedMapping = ref<Mapping | null>(null);

onMounted(() => {
    fetchMappings();
});

const editMapping = (mapping: Mapping) => {
    selectedMapping.value = {
        id: mapping.id,
        transaction_type: mapping.transaction_type,
        debit_account_id: mapping.debit_account_id || mapping.debit_account?.id || '',
        credit_account_id: mapping.credit_account_id || mapping.credit_account?.id || '',
        description_template: mapping.description_template || ''
    };
    showModal.value = true;
};

const saveMapping = async (updated: Mapping) => {
    try {
        await updateMapping(updated.id, {
            debit_account_id: updated.debit_account_id,
            credit_account_id: updated.credit_account_id,
            description_template: updated.description_template
        });
        fetchMappings();
    } catch (error) {
        console.error('Gagal memperbarui pemetaan akun:', error);
    }
};
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

        <div v-if="loading" class="py-20 text-center text-xs text-muted-foreground font-medium animate-pulse">
            Memuat data konfigurasi pemetaan jurnal otomatis...
        </div>

        <div v-else class="rounded-xl border border-border/60 overflow-hidden bg-card shadow-sm">
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
                                {{ map.transaction_type }}
                            </span>
                        </td>
                        
                        <td class="px-4 py-4">
                            <div v-if="map.debit_account">
                                <div class="font-bold text-blue-600 dark:text-blue-400 font-mono">{{ map.debit_account.code }}</div>
                                <div class="text-[11px] text-muted-foreground mt-0.5">{{ map.debit_account.name }}</div>
                            </div>
                            <div v-else class="text-destructive font-medium italic text-[11px]">
                                Belum dikonfigurasi
                            </div>
                        </td>
                        
                        <td class="px-4 py-4">
                            <div v-if="map.credit_account">
                                <div class="font-bold text-purple-600 dark:text-purple-400 font-mono">{{ map.credit_account.code }}</div>
                                <div class="text-[11px] text-muted-foreground mt-0.5">{{ map.credit_account.name }}</div>
                            </div>
                            <div v-else class="text-destructive font-medium italic text-[11px]">
                                Belum dikonfigurasi
                            </div>
                        </td>
                        
                        <td class="px-4 py-4 text-muted-foreground italic hidden lg:table-cell max-w-xs truncate">
                            {{ map.description_template }}
                        </td>
                        
                        <td class="px-4 py-4 text-center">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs font-semibold shadow-sm"
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