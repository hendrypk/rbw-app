<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useSuppliers } from '@/composables/useSuppliers'; 
import { onMounted, ref } from 'vue';
import SupplierModal from './SupplierModal.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal.js';
import axios from 'axios';


defineOptions({ layout: AppSidebarLayout });

const showModal = ref(false);
const activeSupplier = ref(null); // null = create, object = edit
const { confirm, success, error } = useSwal();

const openCreate = () => {
    activeSupplier.value = null; // Reset ke null
    showModal.value = true;
};

const openEdit = (supplier: any) => {
    activeSupplier.value = supplier; // Set supplier yang dipilih
    showModal.value = true;
};

const handleSaved = () => {
    fetchSuppliers(); // Refresh data
};

const { suppliers, isLoading, fetchSuppliers } = useSuppliers();

onMounted(() => {
    fetchSuppliers();
});

const remove = async (id: string | number) => {
    // 1. Munculkan konfirmasi Swal
    if (await confirm('Data ini akan dihapus permanen!')) {
        try {
            // 2. Kirim request delete ke API
            const response = await axios.delete(`/api/suppliers/${id}`);

            // 3. Gunakan pesan dari API (response.data.message)
            success('Berhasil', response.data.message);

            // 4. Refresh data
            fetchSuppliers();
        } catch (err: any) {
            // 5. Tangkap error jika API gagal (misalnya karena relasi data)
            const errorMessage = err.response?.data?.message || 'Terjadi kesalahan saat menghapus.';
            error('Gagal', errorMessage);
        }
    }
};

const selectedIds = ref<Number[]>([]);

const toggleSelectAll = () => {
    if (selectedIds.value.length === suppliers.value.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = suppliers.value.map(s => s.id);
    }
};

const bulkDelete = async () => {
    // Debug: Lihat apa isinya sebelum dikirim
    console.log("Isi selectedIds yang akan dikirim:", selectedIds.value);

    if (await confirm(`Yakin ingin menghapus ${selectedIds.value.length} supplier terpilih?`)) {
        try {
            // HAPUS .map(Number) karena UUID adalah string!
            const payload = {
                ids: selectedIds.value 
            };

            const response = await axios.post('/api/suppliers/bulk-delete', payload);
            
            selectedIds.value = [];
            success('Berhasil', response.data.message);
            fetchSuppliers();
        } catch (err: any) {
            console.error("Error Detail:", err.response?.data);
            error('Gagal', err.response?.data?.message || 'Terjadi kesalahan.');
        }
    }
};
</script>

<template>
    <div class="p-6">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Daftar Supplier</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Kelola data rekanan supplier bahan baku.
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <Button @click="openCreate()">New Supplier</Button>
            </div>
        </div>

        <div v-if="selectedIds.length > 0" class="mt-8 flex items-center justify-between rounded-lg bg-destructive/10 px-4 py-3 border border-destructive/20 animate-in fade-in zoom-in duration-200">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-destructive">
                    {{ selectedIds.length }} supplier dipilih
                </span>
                <Button variant="destructive" size="sm" @click="bulkDelete">Hapus Terpilih</Button>
            </div>
            <button @click="selectedIds = []" class="text-muted-foreground hover:text-foreground">
                Batal
            </button>
        </div>

        <div v-if="isLoading" class="mt-8 text-center text-muted-foreground">
            Memuat data...
        </div>

        <div v-else-if="suppliers.length > 0" class="table-container mt-8">
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <table class="w-full text-sm text-left">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-6 py-4 w-12">
                                <Input type="checkbox" 
                                    :checked="selectedIds.length === suppliers.length && suppliers.length > 0" 
                                    @change="toggleSelectAll" />
                            </th>
                            <th class="px-6 py-4 font-medium">#</th>
                            <th class="px-6 py-4 font-medium">Nama Supplier</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium">Kontak</th>
                            <th class="px-6 py-4 font-medium">Alamat</th>
                            <th class="px-6 py-4 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-border/50">
                        <tr v-for="(s, index) in suppliers" :key="s.id" class="group hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                               <input type="checkbox" v-model="selectedIds" :value="s.id" class="rounded border-border" />
                            </td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ index + 1 }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-foreground">{{ s.name }}</div>
                                <div class="text-muted-foreground text-xs">{{ s.email || 'Tidak ada email' }}</div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <span :class="[
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    s.is_active 
                                        ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' 
                                        : 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                                ]">
                                    {{ s.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ s.phone || '-' }}
                            </td>

                            <td class="px-6 py-4 text-muted-foreground">
                                {{ s.address || '-' }}
                            </td>
                            
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button variant="ghost" size="sm" @click="openEdit(s)" class="text-xs font-medium text-primary hover:underline">Edit</Button>                            <span class="text-border">|</span>
                                    <button @click="remove(s.id)" class="text-xs font-medium text-destructive hover:underline">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20 mt-8 text-center border rounded-lg bg-card border-dashed border-border">
            <h3 class="text-lg font-semibold text-foreground">Belum ada supplier</h3>
            <p class="mt-1 text-sm text-muted-foreground">Yuk tambahkan supplier pertama Anda!</p>
        </div>
    </div>
        <SupplierModal 
        :show="showModal" 
        :supplier="activeSupplier"
        @close="showModal = false" 
        @saved="handleSaved" 
    />
</template>