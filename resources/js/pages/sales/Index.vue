<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Daftar Penjualan</h1>
        </div>
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-100 dark:border-zinc-800 overflow-hidden relative">

            <div v-if="isLoading" class="absolute inset-0 z-10 bg-white/50 dark:bg-zinc-900/50 backdrop-blur-sm flex items-center justify-center">
                <span class="text-emerald-600 font-medium animate-pulse">Memuat data...</span>
            </div>

        <div v-else-if="orders.length > 0" class="table-container mt-8">
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <table class="w-full text-sm text-left">
                        <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-6 py-4 w-12">
                                <!-- <Input type="checkbox"
                                    :checked="selectedIds.length === orders.length && orders.length > 0"
                                    @change="toggleSelectAll" /> -->
                            </th>
                            <th class="px-6 py-4">No. PO</th>
                            <th class="px-6 py-4">Supplier</th>
                            <th class="px-6 py-4">Total</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr v-for="po in orders" :key="po.id" class="group hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4"><input type="checkbox" v-model="selectedIds" :value="po.id" /></td>
                            <td class="px-6 py-4 font-medium">{{ po.order_number }}</td>
                            <td class="px-6 py-4">{{ po.customers?.name || '-' }}</td>
                            <td class="px-6 py-4">Rp {{ Number(po.final_total).toLocaleString() }}</td>
                            <td class="px-6 py-4"><span :class="['px-2 py-1 rounded-full text-xs', getStatusColor(po.status)]">{{ po.status }}</span></td>
                            <td class="px-6 py-4 text-right">
                                <!-- <Button variant="outline" size="sm" @click="openView(po)">Detail</Button>
                                <Button variant="ghost" size="sm" @click="openEdit(po)">Edit</Button> -->
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useOrders } from '@/composables/useOrder';
import { useSwal } from '@/composables/useSwal';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import Layout from '@/layouts/settings/Layout.vue';
import { onMounted, onUnmounted, ref } from 'vue';
import { formatDate } from '@/composables/useFormateDate';
import { Link } from '@lucide/vue';

defineProps({ Layout: AppSidebarLayout });

const { orders, isLoading, fetchOrders }  = useOrders();
const { success, error } = useSwal();
const showModal = ref(false);
const showViewModal = ref(false);
const activeOrder = ref(null);
const selectedIds = ref<string[]>([]);

const handleOutletChanged = () => {
    fetchOrders();
}

onMounted(() => {
    fetchOrders();
    window.addEventListener('outlet-changed', handleOutletChanged);
})

onUnmounted(() => {
    window.removeEventListener('outlet-changed', handleOutletChanged);
})

const getStatusColor = (status: string) => {
    return status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
};

</script>
