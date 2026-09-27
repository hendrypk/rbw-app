<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Daftar Penjualan</h1>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex flex-col md:flex-row gap-4 mb-6 bg-white dark:bg-zinc-900 p-4 rounded-xl border border-gray-100 dark:border-zinc-800">
            <div class="flex-1">
                <input
                    v-model="form.search"
                    @keyup.enter="handleFilter"
                    type="text"
                    placeholder="Cari No. Order atau Nama Pelanggan..."
                    class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-emerald-200 dark:bg-zinc-800 dark:border-zinc-700 dark:text-white"
                />
            </div>
            <div class="w-full md:w-64">
                <select
                    v-model="form.status"
                    @change="handleFilter"
                    class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-emerald-200 dark:bg-zinc-800 dark:border-zinc-700 dark:text-white"
                >
                    <option value="">Semua Status</option>
                    <option value="paid">Lunas (Paid)</option>
                    <option value="unpaid">Belum Lunas (Unpaid)</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-100 dark:border-zinc-800 overflow-hidden relative">

            <!-- Loading Overlay -->
            <div v-if="isLoading" class="absolute inset-0 z-10 bg-white/50 dark:bg-zinc-900/50 backdrop-blur-sm flex items-center justify-center">
                <span class="text-emerald-600 font-medium animate-pulse">Memuat data...</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-zinc-800 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-4">No. Order</th>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Total Harga</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop sekarang langsung dari 'orders' -->
                        <tr v-for="order in orders" :key="order.id" class="border-b dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800/50">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                {{ order.order_number }}
                                <div class="text-xs text-gray-500">{{ order.customer_name || 'Pelanggan Umum' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                {{ formatDate(order.transaction_at) }}
                            </td>
                            <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                {{ formatRupiah(order.final_total) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="order.status === 'paid'" class="px-3 py-1 text-[11px] font-bold text-emerald-700 bg-emerald-100 rounded-full">
                                    LUNAS
                                </span>
                                <span v-else class="px-3 py-1 text-[11px] font-bold text-red-700 bg-red-100 rounded-full">
                                    BELUM LUNAS
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <Link :href="`/sales/${order.id}`" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                    Detail
                                </Link>
                            </td>
                        </tr>

                        <tr v-if="!isLoading && orders.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">Tidak ada data ditemukan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination menggunakan 'meta' -->
            <div v-if="meta?.total > 0" class="p-4 border-t border-gray-100 dark:border-zinc-800 flex justify-between items-center text-sm text-gray-500">
                <span>Menampilkan {{ meta.from }} - {{ meta.to }} dari {{ meta.total }}</span>
                <div class="flex gap-2">
                    <button
                        v-for="(link, i) in meta.links"
                        :key="i"
                        @click.prevent="link.url ? handlePagination(link.url) : null"
                        v-html="link.label"
                        :disabled="!link.url"
                        class="px-3 py-1 rounded border transition-colors"
                        :class="[
                            link.active ? 'bg-black text-white border-transparent' : 'bg-white hover:bg-gray-100',
                            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                        ]"
                    ></button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useOrder } from '@/composables/useOrder';
import { useOutlet } from '@/composables/useOutlet';

const { orders, isLoading, meta, fetchOrders } = useOrder();
const { getOutletParam } = useOutlet(); // Sesuaikan dengan variabel yg di-export useOutlet

// State untuk filter pencarian
const form = ref({
    search: '',
    status: '',
    page: 1, // Menyimpan state halaman yang sedang aktif
});

// Fungsi fetch saat tombol Cari / Enter / Ganti Status
const handleFilter = () => {
    form.value.page = 1; // Jika difilter, reset ke halaman 1
    fetchOrders(form.value);
};

// Fungsi khusus menangani klik pagination
const handlePagination = (url: string) => {
    // Laravel mengembalikan URL lengkap di meta.links (misal: http://localhost:8000/api/orders?page=2)
    // Kita ekstrak angka halamannya, lalu masukkan ke params API kita
    const urlObj = new URL(url);
    const pageNum = urlObj.searchParams.get('page');

    if (pageNum) {
        form.value.page = Number(pageNum);
        fetchOrders(form.value);
    }
};

// Pantau perubahan Outlet
// Apabila outlet berubah dari navbar/sidebar atas, muat ulang tabel ini dari hal 1
watch(() => activeOutletId?.value, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        handleFilter();
    }
});

// Helper Formatter
const formatRupiah = (angka: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka || 0);
};

const formatDate = (dateString: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Panggilan pertama saat komponen dimuat
onMounted(() => {
    handleFilter();
});
</script>
