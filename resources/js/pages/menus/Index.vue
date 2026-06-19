<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import Button from '@/components/ui/button/Button.vue';
import MenuModal from './MenuModal.vue';
import { useMaterials } from '@/composables/useMaterials';
import { Head, Link, router } from '@inertiajs/vue3';

defineOptions({ layout: AppSidebarLayout });

// 1. Perbaikan destructuring: Gunakan 'materials' sesuai error, bukan 'rawMaterials'
const { materials, fetchMaterials } = useMaterials();

// 2. Perbaikan Typing: Berikan interface eksplisit agar TypeScript tidak bingung (type: never)
// Anda bisa menyesuaikan tipe 'any' dengan interface Menu jika sudah ada
const menus = ref<{ data: any[] }>({ data: [] }); 

const showModal = ref(false);
const editingMenu = ref<any>(null); // Eksplisit typed

// ... sisa fungsi lainnya tetap sama
const openCreate = () => {
    editingMenu.value = null;
    showModal.value = true;
};

const openEdit = (menu: any) => {
    editingMenu.value = menu;
    showModal.value = true;
};

const fetchMenus = async () => {
    try {
        const { data } = await axios.get('/api/menus');
        menus.value = data;
    } catch (e) {
        console.error("Gagal mengambil menu", e);
    }
};

onMounted(() => {
    fetchMenus();
    fetchMaterials();
});

// Helper formatter
const currency = (n: any) => new Intl.NumberFormat('id-ID', { 
    style: 'currency', currency: 'IDR', minimumFractionDigits: 0 
}).format(n ?? 0);

const remove = (id: string) => {
    if (confirm('Yakin ingin menghapus menu ini?')) {
        router.delete(`/menus/${id}`, { onSuccess: () => fetchMenus() });
    }
};
</script>

<template>
    <Head title="Menu" />

    <div class="p-6">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Daftar Menu</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Kelola menu dan harga jual per channel.
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <Button @click="openCreate()">+ New Menu</Button>
            </div>
        </div>

        <div v-if="menus?.data?.length > 0" class="table-container mt-8">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Menu</th>
                        <th>Status</th>
                        <th>HPP</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in menus.data" :key="m.id">
                        <td>
                            <div class="font-medium">{{ m.name }}</div>
                            <div class="text-muted-foreground text-xs">{{ m.category }}</div>
                        </td>
                        <td>
                            <span :class="m.is_active ? 'text-green-600 dark:text-green-400' : 'text-destructive'">
                                {{ m.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>{{ currency(m.hpp) }}</td>
                        <td class="text-right">
                            <Button variant="ghost" size="sm" @click="openEdit(m)">Edit</Button>
                            <Button variant="ghost" size="sm" class="text-destructive" @click="remove(m.id)">Hapus</Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20 mt-8 text-center border rounded-lg bg-card border-dashed border-border">
            <h3 class="text-lg font-semibold">Data Kosong</h3>
            <p class="text-sm text-muted-foreground">Belum ada menu yang dibuat.</p>
            <Button class="mt-4" @click="openCreate()">+ Buat Menu</Button>
        </div>
    </div>

    <MenuModal 
        :show="showModal" 
        :menu="editingMenu" 
        :rawMaterials="materials" 
        @close="showModal = false"
        @success="fetchMenus; showModal = false" 
    />
</template>