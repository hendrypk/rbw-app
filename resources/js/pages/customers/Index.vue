<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal';
import CustomerModal from './CustomerModal.vue';
import { useCustomers } from '@/composables/useCustomers';

defineOptions({ layout: AppSidebarLayout });

const { success, error } = useSwal();
const { customers, meta, isLoading, fetchCustomers } = useCustomers();

const search = ref('');

const viewMode = ref<'table' | 'grid'>('table');

const isOpenModal = ref(false);
const activeCustomer = ref<any>(null);

const loadData = async (page = 1) => {
    await fetchCustomers({
        page: page,
        search: search.value || null,
        limit: 20
    });
};

onMounted(() => {
    loadData(1);
});

const changePage = (page: number) => {
    if (page >= 1 && (!meta.value || page <= meta.value.last_page)) {
        loadData(page);
    }
};

const displayCustomers = computed(() => {
    let result = customers.value || [];
    if (search.value.trim()) {
        const query = search.value.toLowerCase().trim();
        result = result.filter(c =>
            (c.name && c.name.toLowerCase().includes(query)) ||
            (c.phone && c.phone.includes(query)) ||
            (c.email && c.email.toLowerCase().includes(query))
        );
    }
    return result;
});

const openCreateModal = () => {
    activeCustomer.value = null;
    isOpenModal.value = true;
};

const openEditModal = (customer: any) => {
    activeCustomer.value = customer;
    isOpenModal.value = true;
};

const handleSaved = (type: 'simpan' | 'ubah') => {
    isOpenModal.value = false;
    loadData(1);
    if (type === 'ubah') {
        success('Berhasil', 'Data pelanggan berhasil diperbarui!');
    } else {
        success('Berhasil', 'Pelanggan baru berhasil ditambahkan!');
    }
};

const deleteCustomer = async (customer: any) => {
    if (confirm(`Apakah Anda yakin ingin menghapus pelanggan "${customer.name}"?`)) {
        try {
            await axios.delete(`/api/customers/${customer.id}`);
            loadData(1);
            success('Berhasil', 'Data pelanggan berhasil dihapus.');
        } catch (err: any) {
            error('Gagal', err.response?.data?.message || 'Terjadi kesalahan saat menghapus pelanggan.');
        }
    }
};

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('id-ID').format(num || 0);
};
</script>

<template>
    <div class="">

        <div class="space-y-6 max-w-full px-4 sm:px-6 lg:px-8 py-6 overflow-x-hidden">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground">Database Pelanggan</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Kelola ratusan data pelanggan tanpa pusing.
                    </p>
                </div>

                <Button
                    size="sm"
                    class="h-9 px-5 rounded-xl text-xs font-bold bg-foreground text-background hover:opacity-90 shadow-md"
                    @click="openCreateModal"
                >
                    + Pelanggan Baru
                </Button>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="w-full sm:w-80 relative">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-2.5 text-muted-foreground"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <Input
                        v-model="search"
                        placeholder="Cari nama, email, atau telepon..."
                        class="w-full text-xs h-9 pl-9 pr-8 bg-secondary/40 border-border/60 rounded-xl font-medium focus:ring-1 focus:ring-ring shadow-sm"
                        @keyup.enter="loadData(1)"
                    />
                </div>

                <div class="flex items-center gap-1 bg-secondary/30 p-1 rounded-xl border border-border/60 w-full sm:w-auto justify-end sm:justify-start">
                    <button
                        @click="viewMode = 'table'"
                        :class="['p-1.5 rounded-lg transition-all', viewMode === 'table' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground']"
                        title="Tampilan Tabel"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/><path d="M15 3v18"/></svg>
                    </button>
                    <button
                        @click="viewMode = 'grid'"
                        :class="['p-1.5 rounded-lg transition-all', viewMode === 'grid' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground']"
                        title="Tampilan Kartu"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    </button>
                </div>
            </div>

            <div v-if="isLoading" class="py-12 flex flex-col items-center justify-center text-muted-foreground">
                <div class="w-8 h-8 border-4 border-primary/30 border-t-primary rounded-full animate-spin mb-3"></div>
                <p class="text-xs font-medium">Memuat data pelanggan...</p>
            </div>

            <div v-else-if="displayCustomers.length === 0" class="py-16 bg-card border border-dashed border-border/60 rounded-3xl flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 bg-secondary/50 rounded-full flex items-center justify-center mb-4 text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                </div>
                <h3 class="text-sm font-bold text-foreground">Tidak Ada Data</h3>
                <p class="text-xs text-muted-foreground mt-1 max-w-sm">Pelanggan yang Anda cari tidak ditemukan.</p>
            </div>

            <div v-else-if="viewMode === 'table'" class="bg-card border border-border/60 rounded-2xl overflow-hidden shadow-xs backdrop-blur-md">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border/60 bg-secondary/30 text-muted-foreground text-[11px] font-semibold uppercase tracking-wider">
                                <th class="px-4 py-3">Nama Pelanggan</th>
                                <th class="px-4 py-3">Kontak</th>
                                <th class="px-4 py-3">Alamat</th>
                                <th class="px-4 py-3">Riwayat Transaksi</th>
                                <th class="px-4 py-3 text-right">Total Poin</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40 text-xs font-medium">
                            <tr v-for="customer in displayCustomers" :key="customer.id" class="hover:bg-secondary/20 transition-colors group">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-foreground">{{ customer.name }}</div>
                                    <div v-if="customer.email" class="text-[11px] text-muted-foreground font-normal mt-0.5">{{ customer.email }}</div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground font-mono">{{ customer.phone || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground max-w-[150px] truncate" :title="customer.shipping_address">{{ customer.shipping_address || '-' }}</td>

                                <td class="px-4 py-3">
                                    <div class="text-[11px] text-muted-foreground flex flex-col gap-0.5">
                                        <span><b class="text-foreground">{{ customer.orders_count || 0 }}x</b> Trx | <b class="text-foreground">{{ customer.total_portions || 0 }}</b> Porsi</span>
                                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">Rp {{ formatNumber(customer.total_spent) }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 font-bold">
                                        {{ formatNumber(customer.total_points) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                        <Button variant="ghost" size="sm" class="h-7 w-7 p-0 text-muted-foreground hover:text-foreground" @click="openEditModal(customer)">✎</Button>
                                        <Button variant="ghost" size="sm" class="h-7 w-7 p-0 text-destructive/70 hover:text-destructive" @click="deleteCustomer(customer)">🗑</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div
                    v-for="customer in displayCustomers"
                    :key="customer.id"
                    class="bg-card border border-border/60 rounded-2xl p-4 shadow-xs hover:shadow-md hover:border-primary/30 transition-all duration-300 flex flex-col group relative overflow-hidden"
                >
                    <div class="flex justify-between items-start mb-3">
                        <div class="min-w-0 pr-4">
                            <h3 class="font-bold text-foreground text-sm truncate" :title="customer.name">{{ customer.name }}</h3>
                            <p class="text-[11px] text-muted-foreground truncate">{{ customer.email || 'Tidak ada email' }}</p>
                        </div>

                        <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity flex-shrink-0">
                            <button @click="openEditModal(customer)" class="p-1.5 bg-secondary/50 hover:bg-secondary text-muted-foreground hover:text-foreground rounded-lg transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            </button>
                            <button @click="deleteCustomer(customer)" class="p-1.5 bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 dark:hover:bg-red-900/40 rounded-lg transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2 mb-4">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                            <span class="font-mono">{{ customer.phone || '-' }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span class="line-clamp-2 leading-relaxed" :title="customer.shipping_address">{{ customer.shipping_address || 'Belum ada alamat' }}</span>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="bg-secondary/30 rounded-xl p-3 grid grid-cols-3 gap-2 text-center divide-x divide-border/60 mb-3">
                            <div class="flex flex-col justify-center">
                                <span class="text-[9px] text-muted-foreground uppercase font-bold tracking-wider mb-0.5">Order</span>
                                <span class="text-xs font-extrabold text-foreground">{{ customer.orders_count || 0 }}</span>
                            </div>
                            <div class="flex flex-col justify-center">
                                <span class="text-[9px] text-muted-foreground uppercase font-bold tracking-wider mb-0.5">Porsi</span>
                                <span class="text-xs font-extrabold text-foreground">{{ customer.total_portions || 0 }}</span>
                            </div>
                            <div class="flex flex-col justify-center">
                                <span class="text-[9px] text-muted-foreground uppercase font-bold tracking-wider mb-0.5">Poin</span>
                                <span class="text-xs font-extrabold text-amber-500">{{ formatNumber(customer.total_points) }}</span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center px-1">
                            <span class="text-[10px] text-muted-foreground font-medium">Total Belanja</span>
                            <span class="text-xs font-black text-emerald-600 dark:text-emerald-400 font-mono">Rp {{ formatNumber(customer.total_spent) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="meta && meta.last_page > 1" class="flex flex-col sm:flex-row justify-between items-center mt-4 gap-4">
                <span class="text-[11px] text-muted-foreground font-medium">
                    Menampilkan <b class="text-foreground">{{ displayCustomers.length }}</b> dari total <b class="text-foreground">{{ meta.total }}</b> pelanggan
                </span>

                <div class="flex items-center gap-1 bg-card border border-border/60 rounded-lg p-1 shadow-xs">
                    <button
                        @click="changePage(meta.current_page - 1)"
                        :disabled="meta.current_page === 1"
                        class="px-3 py-1.5 text-xs font-medium rounded-md hover:bg-secondary disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-foreground"
                    >
                        Sebelumnya
                    </button>
                    <div class="px-3 py-1.5 text-xs font-bold bg-primary text-primary-foreground rounded-md">
                        {{ meta.current_page }}
                    </div>
                    <button
                        @click="changePage(meta.current_page + 1)"
                        :disabled="meta.current_page === meta.last_page"
                        class="px-3 py-1.5 text-xs font-medium rounded-md hover:bg-secondary disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-foreground"
                    >
                        Selanjutnya
                    </button>
                </div>
            </div>

            <CustomerModal :show="isOpenModal" :customer="activeCustomer" @close="isOpenModal = false" @saved="handleSaved" />
        </div>
    </div>
</template>
