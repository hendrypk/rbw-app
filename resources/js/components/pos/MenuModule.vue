<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useSwal } from '@/composables/useSwal';
import { useMenus } from '@/composables/useMenus';
import { useMaterials } from '@/composables/useMaterials';
import { useCategories } from '@/composables/useCategories';
import axios from 'axios';
import MenuModal from '@/pages/menus/MenuModal.vue';
import CategoryModal from '@/pages/menus/CategoryModal.vue';
import CategorySortModal from '@/pages/menus/CategorySortModal.vue';

const { confirm, success, error } = useSwal();
const { menus, isLoading, fetchMenus } = useMenus();
const { fetchMaterialOptions } = useMaterials();
const { categories, fetchCategories } = useCategories();

const showModal = ref(false);
const showCategoryModal = ref(false);
const showCategorySortModal = ref(false);
const activeMenu = ref<any>(null);
const selectedCategoryForSort = ref<any>(null);
const activeCategoryId = ref<string>('all');
const searchQuery = ref('');
const sortBy = ref('name'); 
const sortDirection = ref<'asc' | 'desc'>('asc'); 
const selectedIds = ref<string[]>([]);
const isOutOfSync = ref(false);
const showBanner = ref(false); 
const masterOverheadTotal = ref(0);
const isSyncing = ref(false);
const isSyncingRecipe = ref(false);
const isRecipeOutOfSync = ref(false);
const showRecipeBanner = ref(false);

const handleCategoryUpdated = () => {
    fetchCategories();
    fetchMenus();
};

const sortedCategories = computed(() => {
    return [...categories.value].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));
});

const checkSyncStatus = async () => {
    try {
        const res = await axios.get('/api/menus/overhead-sync-status');
        isOutOfSync.value = res.data.is_out_of_sync;
        if (res.data.is_out_of_sync) showBanner.value = true; 
        masterOverheadTotal.value = res.data.master_total;
    } catch (err) {
        console.error('Gagal mengecek sinkronisasi overhead', err);
    }
};

const checkRecipeSyncStatus = async () => {
    try {
        const res = await axios.get('/api/menus/recipe-sync-status');
        isRecipeOutOfSync.value = res.data.is_out_of_sync;
        if (res.data.is_out_of_sync) {
            showRecipeBanner.value = true; 
        }
    } catch (err) {
        console.error('Gagal mengecek sinkronisasi HPP bahan', err);
    }
};

const executeRecipeSync = async () => {
    if (await confirm('Sinkronkan Resep & HPP?', 'Semua resep dan kalkulasi HPP menu aktif akan diperbarui menggunakan avg_cost terbaru.')) {
        isSyncingRecipe.value = true;
        try {
            await axios.post('/api/menus/sync-recipes');
            showRecipeBanner.value = false;
            isRecipeOutOfSync.value = false;
            success('Berhasil', 'Seluruh resep menu berhasil disinkronkan.');
            fetchMenus();
        } catch (err) {
            error('Gagal', 'Gagal melakukan sinkronisasi resep.');
        } finally {
            isSyncingRecipe.value = false;
        }
    }
};

const filteredAndSortedMenus = computed(() => {
    let result = [...menus.value];
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim();
        result = result.filter(menu => menu.name.toLowerCase().includes(query));
    }
    if (activeCategoryId.value !== 'all') {
        result = result.filter(menu => {
            if (!menu.categories || !Array.isArray(menu.categories)) return false;
            return menu.categories.some((cat: any) => cat.id === activeCategoryId.value);
        });
        result.sort((a, b) => {
            const catA = a.categories?.find((c: any) => c.id === activeCategoryId.value);
            const catB = b.categories?.find((c: any) => c.id === activeCategoryId.value);
            return (catA?.pivot?.sort ?? 0) - (catB?.pivot?.sort ?? 0);
        });
    } else {
        result.sort((a, b) => {
            let modifier = sortDirection.value === 'desc' ? -1 : 1;
            if (sortBy.value === 'hpp') {
                return (Number(a.hpp) - Number(b.hpp)) * modifier;
            }
            const key = sortBy.value as keyof typeof a;
            const fieldA = (a[key] ?? '').toString().toLowerCase();
            const fieldB = (b[key] ?? '').toString().toLowerCase();
            if (fieldA < fieldB) return -1 * modifier;
            if (fieldA > fieldB) return 1 * modifier;
            return 0;
        });
    }
    return result;
});

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDirection.value = 'asc';
    }
};

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
    showModal.value = false;
    fetchMenus();
    success('Berhasil', 'Data menu berhasil disimpan!');
};

const toggleSelectAll = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    selectedIds.value = checked ? filteredAndSortedMenus.value.map(m => m.id) : [];
};

const bulkDelete = async () => {
    if (await confirm('Hapus semua menu yang dipilih?', 'Tindakan ini tidak dapat dibatalkan.')) {
        try {
            await axios.post('/api/menus/bulk-destroy', { ids: selectedIds.value });
            selectedIds.value = [];
            fetchMenus();
            success('Berhasil', 'Menu terpilih berhasil dihapus.');
        } catch (e) {
            error('Gagal', 'Gagal menghapus menu.');
        }
    }
};

const handleDelete = async (menu: any) => {
    if (await confirm('Yakin ingin menghapus menu ini?', `Menu "${menu.name}" akan dihapus permanen.`)) {
        try {
            await axios.delete(`/api/menus/${menu.id}`);
            fetchMenus();
            success('Berhasil', 'Menu berhasil dihapus.');
        } catch (e) {
            error('Gagal', 'Gagal menghapus menu.');
        }
    }
};

const handleSyncNow = async () => {
    if (await confirm('Sinkronkan Overhead?', `Nilai overhead disesuaikan menjadi Rp ${masterOverheadTotal.value.toLocaleString()}.`)) {
        isSyncing.value = true;
        try {
            await axios.post('/api/menus/overhead-sync');
            success('Berhasil', 'Seluruh menu berhasil disinkronkan.');
            isOutOfSync.value = false;
            showBanner.value = false;
            fetchMenus();
        } catch (err) {
            error('Gagal', 'Terjadi kesalahan saat sinkronisasi.');
        } finally {
            isSyncing.value = false;
        }
    }
};

const getChannelClass = (channel: string) => {
    switch(channel.toLowerCase()) {
        case 'offline': return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
        case 'gofood': return 'bg-red-50 text-red-700 border-red-100 dark:bg-red-950/20 dark:text-red-400';
        case 'grabfood': return 'bg-green-50 text-green-700 border-green-100 dark:bg-green-950/20 dark:text-green-400';
        case 'shopeefood': return 'bg-orange-50 text-orange-700 border-orange-100 dark:bg-orange-950/20 dark:text-orange-400';
        default: return 'bg-muted text-muted-foreground';
    }
};

onMounted(() => {
    fetchMenus();
    fetchMaterialOptions();
    fetchCategories();
    checkSyncStatus();
    checkRecipeSyncStatus();
});
</script>

<template>
    <div class="space-y-5 max-w-full overflow-x-hidden">
        
        <!-- Header & Tombol Aksi Utama -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4 border-border/60">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-foreground">Manajemen Katalog Menu</h2>
                <p class="text-xs text-muted-foreground">Kelola resep, kalkulasi overhead, dan harga jual multi-channel.</p>
            </div>
            
            <div class="flex items-center gap-2 flex-wrap">
                <Button v-if="isOutOfSync" variant="outline" size="sm" class="h-8 text-xs text-amber-600 border-amber-400 animate-pulse" @click="handleSyncNow">
                    ⚠️ Sync Overhead
                </Button>
                <Button variant="outline" size="sm" class="h-8 text-xs" @click="showCategoryModal = true">
                    📂 Kelola Kategori
                </Button>
                <Button size="sm" class="h-8 text-xs font-semibold shadow-xs" @click="openCreate">
                    + Menu Baru
                </Button>
            </div>
        </div>

        <!-- Layout Sederhana: Kategori Horizontal Pills + Search Bar -->
        <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between bg-muted/30 p-3 rounded-xl border border-border/60">
            
            <!-- Daftar Kategori Horizontal (Scrollable jika banyak) -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                <button
                    @click="activeCategoryId = 'all'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap cursor-pointer',
                        activeCategoryId === 'all' 
                            ? 'bg-primary text-primary-foreground font-bold shadow-xs' 
                            : 'bg-background hover:bg-muted text-muted-foreground hover:text-foreground border border-border/40'
                    ]"
                >
                    Semua ({{ menus.length }})
                </button>

                <button
                    v-for="cat in sortedCategories" 
                    :key="cat.id"
                    @click="activeCategoryId = cat.id"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap cursor-pointer',
                        activeCategoryId === cat.id 
                            ? 'bg-primary text-primary-foreground font-bold shadow-xs' 
                            : 'bg-background hover:bg-muted text-muted-foreground hover:text-foreground border border-border/40',
                        !cat.is_visible ? 'opacity-50 line-through' : ''
                    ]"
                >
                    {{ cat.name }}
                </button>
            </div>

            <!-- Search Bar Ringkas -->
            <div class="w-full sm:w-64 relative shrink-0">
                <Input 
                    v-model="searchQuery" 
                    placeholder="Cari nama menu..." 
                    class="w-full text-xs h-8 pl-3 pr-8"
                />
                <span v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-1.5 text-muted-foreground hover:text-foreground cursor-pointer font-bold">&times;</span>
            </div>
        </div>

        <!-- Alert & Banner Status -->
        <div v-if="selectedIds.length > 0" class="flex items-center justify-between rounded-xl bg-destructive/5 px-4 py-2 border border-destructive/20 text-xs">
            <span class="font-medium text-destructive">{{ selectedIds.length }} menu dipilih untuk aksi massal</span>
            <div class="flex items-center gap-2">
                <button @click="selectedIds = []" class="text-muted-foreground hover:text-foreground">Batal</button>
                <Button variant="destructive" size="sm" class="h-7 text-xs px-3" @click="bulkDelete">Hapus Terpilih</Button>
            </div>
        </div>

        <!-- Tabel Menu Penuh & Luas -->
        <div v-if="isLoading" class="text-center py-10 text-muted-foreground text-xs">Memuat data menu...</div>

        <div v-else-if="filteredAndSortedMenus.length > 0" class="w-full overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left min-w-[750px]">
                <thead class="bg-muted/50 text-muted-foreground text-xs">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" :checked="selectedIds.length === filteredAndSortedMenus.length && filteredAndSortedMenus.length > 0" @change="toggleSelectAll" />
                        </th>
                        <th class="px-4 py-3 font-medium cursor-pointer" @click="toggleSort('name')">Nama Menu</th>
                        <th class="px-4 py-3 font-medium">Kategori</th>
                        <!-- <th class="px-4 py-3 font-medium">Overhead</th>
                        <th class="px-4 py-3 font-medium cursor-pointer" @click="toggleSort('hpp')">HPP</th> -->
                        <th class="px-4 py-3 font-medium">Harga Jual Kanal</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/50 text-xs">
                    <tr v-for="menu in filteredAndSortedMenus" :key="menu.id" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3"><input type="checkbox" v-model="selectedIds" :value="menu.id" /></td>
                        <td class="px-4 py-3 font-medium text-foreground whitespace-nowrap">{{ menu.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <span v-for="cat in menu.categories" :key="cat.id" class="px-1.5 py-0.5 rounded bg-muted text-[10px] mr-1">{{ cat.name }}</span>
                        </td>
                        <!-- <td class="px-4 py-3 font-medium whitespace-nowrap">Rp {{ Number(menu.overhead_cost || 0).toLocaleString() }}</td>
                        <td class="px-4 py-3 text-muted-foreground font-medium whitespace-nowrap">{{ currency(menu.hpp) }}</td> -->
                        <td class="px-4 py-3">
                            <div v-if="Array.isArray(menu.prices) && menu.prices.length > 0" class="flex flex-wrap gap-1.5">
                                <div v-for="price in menu.prices" :key="price.id" :class="['px-2 py-1 rounded-md border text-[10px]', getChannelClass(price.channel)]">
                                    <span class="font-bold">{{ price.channel }}:</span> {{ currency(price.selling_price) }}
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="menu.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'" class="px-2 py-0.5 rounded-full text-[10px] font-medium">
                                {{ menu.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <Button variant="outline" size="sm" class="h-7 px-2 text-[11px]" @click="openEdit(menu)">Edit</Button>
                                <Button variant="destructive" size="sm" class="h-7 px-2 text-[11px]" @click="handleDelete(menu)">Hapus</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="text-center py-16 border border-dashed rounded-xl bg-card text-muted-foreground text-xs">
            Tidak ada menu yang ditemukan dalam kategori ini.
        </div>
    </div>

    <MenuModal :show="showModal" :menu="activeMenu" :masterOverhead="masterOverheadTotal" @close="showModal = false" @saved="handleSaved" />
    <CategoryModal :show="showCategoryModal" @close="showCategoryModal = false" @updated="handleCategoryUpdated" />
    <CategorySortModal :show="showCategorySortModal" :category="selectedCategoryForSort" @close="showCategorySortModal = false" @updated="fetchMenus" />
</template>