<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{ 
    show: boolean, 
    po: any 
}>();

const emit = defineEmits(['close']);

// Helper untuk status agar konsisten dengan Index
const getStatusColor = (status: string) => {
    return status === 'received' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-3xl bg-card rounded-xl shadow-xl p-6 border border-border max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-xl font-bold">Detail PO: {{ po?.po_number }}</h2>
                    <p class="text-sm text-muted-foreground">{{ po?.order_date }}</p>
                </div>
                <span :class="['px-3 py-1 rounded-full text-xs font-semibold uppercase', getStatusColor(po?.status)]">
                    {{ po?.status }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <h3 class="text-xs font-semibold text-muted-foreground uppercase">Supplier</h3>
                    <p class="font-medium mt-1">{{ po?.supplier?.name }}</p>
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-muted-foreground uppercase">Tanggal</h3>
                    <p class="font-medium mt-1">{{ new Date(po?.order_date).toLocaleDateString('id-ID') }}</p>
                </div>
            </div>

            <div class="border rounded-lg overflow-hidden mb-6">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-2 text-left">Material</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2 text-right">Harga Satuan</th>
                            <th class="px-4 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in po?.items" :key="item.id">
                            <td class="px-4 py-3">{{ item.raw_material?.name }}</td>
                            <td class="px-4 py-3 text-right">{{ Number(item.qty).toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ Number(item.unit_price).toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right font-medium">Rp {{ Number(item.subtotal).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-between items-center border-t pt-4">
                <div class="text-sm text-muted-foreground">
                    Catatan: {{ po?.notes || '-' }}
                </div>
                <div class="text-lg font-bold">
                    Total: Rp {{ Number(po?.total_amount).toLocaleString() }}
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <Button variant="outline" @click="$emit('close')">Tutup</Button>
            </div>
        </div>
    </div>
</template>