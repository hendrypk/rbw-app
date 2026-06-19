<script setup lang="ts">
    import { ref, onMounted } from 'vue';
    import { Head, router } from '@inertiajs/vue3';
    import axios from 'axios';
    import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
    import MenuModal from './MenuModal.vue';
    import Button from '@/components/ui/button/Button.vue';
    import { useSwal } from '@/composables/useSwal';
    import { useMenus } from '@/composables/useMenus';
    import { useMaterials } from '@/composables/useMaterials';
    
    defineOptions({ layout: AppSidebarLayout });

    const showModal = ref(false);
    const activeMenu = ref(null); // null = create, object = edit
    const { confirm, success, error } = useSwal();
    const { menus, isLoading, fetchMenus } = useMenus();
    const { materials, fetchMaterials } = useMaterials();

    // Helper untuk mata uang
    const currency = (n: number) => new Intl.NumberFormat('id-ID', { 
        style: 'currency', currency: 'IDR', minimumFractionDigits: 0 
    }).format(n ?? 0);

    const openCreate = () => {
        activeMenu.value = null;
        showModal.value = true;
    };

    const openEdit = (menu: any) => {
        activeMenu.value = menu;
        showModal.value = true;
    };

    const handleSaved = () => {
        fetchMenus();
        success('Menu berhasil disimpan!');
    };

    const selectedIds = ref<string[]>([]);

    const toggleSelectAll = (event: Event) => {
        const checked = (event.target as HTMLInputElement).checked;
        selectedIds.value = checked ? menus.value.map(m => m.id) : [];
    };

    const bulkDelete = async () => {
        if (await confirm('Hapus semua menu yang dipilih?')) {
            try {
                await axios.post('/api/menus/bulk-destroy', { ids: selectedIds.value });
                selectedIds.value = [];
                fetchMenus();
                success('Berhasil dihapus.');
            } catch (e) {
                error('Gagal menghapus menu.');
            }
        }
    };

    const handleDelete = async (menu: any) => {
        if (await confirm('Yakin ingin menghapus menu ini?')) {
            try {
                await axios.delete(`/api/menus/${menu.id}`);
                fetchMenus();
                success('Menu berhasil dihapus.');
            } catch (e) {
                error('Gagal menghapus menu.');
            }
        }
    };

    onMounted(() => {
        fetchMenus();
        fetchMaterials();
    });
</script>

<template>
    <div class="p-6">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Daftar Menu</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Kelola menu, resep, dan harga jual per channel.
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <Button @click="openCreate()">+ New Menu</Button>
            </div>
        </div>

        <div v-if="selectedIds.length > 0" class="mt-8 flex items-center justify-between rounded-lg bg-destructive/10 px-4 py-3 border border-destructive/20 animate-in fade-in zoom-in duration-200">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-destructive">
                    {{ selectedIds.length }} menu dipilih
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

        <div v-else-if="menus.length > 0" class="table-container mt-8">
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <table class="w-full text-sm text-left">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-6 py-4 w-12">
                                <input type="checkbox" 
                                    :checked="selectedIds.length === menus.length && menus.length > 0" 
                                    @change="toggleSelectAll" />
                            </th>
                            <th class="px-6 py-4 font-medium">Nama Menu</th>
                            <th class="px-6 py-4 font-medium">Kategori</th>
                            <th class="px-6 py-4 font-medium">HPP</th>
                            <th class="px-6 py-4 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-border/50">
                        <tr v-for="m in menus" :key="m.id" class="group hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4">
                                <input type="checkbox" v-model="selectedIds" :value="m.id" class="rounded border-border" />
                            </td>
                            <td class="px-6 py-4 font-medium text-foreground">{{ m.name }}</td>
                            <td class="px-6 py-4 text-muted-foreground">{{ m.category }}</td>
                            <td class="px-6 py-4 text-muted-foreground">{{ currency(m.hpp) }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button variant="ghost" size="sm" @click="openEdit(m)" class="text-xs font-medium text-primary hover:underline">Edit</Button>
                                    <span class="text-border">|</span>
                                    <button @click="handleDelete(m)" class="text-xs font-medium text-destructive hover:underline">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20 mt-8 text-center border rounded-lg bg-card border-dashed border-border">
            <h3 class="text-lg font-semibold text-foreground">Belum ada menu</h3>
            <p class="mt-1 text-sm text-muted-foreground">Buat menu pertama Anda untuk memulai.</p>
        </div>
    </div>

    <MenuModal 
        :show="showModal" 
        :menu="activeMenu" 
        :rawMaterials="materials" 
        @close="showModal = false"
        @saved="handleSaved" 
    />
</template>