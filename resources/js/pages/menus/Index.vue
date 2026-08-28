<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import MenuModal from './MenuModal.vue';
import CategorySortModal from './CategorySortModal.vue'; // Modal khusus untuk urutkan menu dalam kategori
import { useSwal } from '@/composables/useSwal';
import { useMenus } from '@/composables/useMenus';
import { useMaterials } from '@/composables/useMaterials';
import { useCategories } from '@/composables/useCategories';
import axios from 'axios';
import CategoryModal from './CategoryModal.vue';

defineOptions({ layout: AppSidebarLayout });

const { confirm, success, error } = useSwal();
const { menus, isLoading, fetchMenus } = useMenus();
const { materials, fetchMaterialOptions } = useMaterials();
const { categories, fetchCategories } = useCategories();

// State Fungsionalitas Modal Menu & Kategori
const showModal = ref(false);
const showCategoryModal = ref(false);
const showCategorySortModal = ref(false);
const activeMenu = ref<any>(null);
const selectedCategoryForSort = ref<any>(null);

// State Navigasi Tab Kategori Aktif (default 'all')
const activeCategoryId = ref<string>('all');

// State Filter, Search, dan Sort Data
const searchQuery = ref('');
const sortBy = ref('name'); 
const sortDirection = ref<'asc' | 'desc'>('asc'); 

// State Fungsionalitas Bulk Delete / Selection
const selectedIds = ref<string[]>([]);

// State Tracking Sync Overhead & Recipes
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

// Urutkan kategori berdasarkan properti sort secara ascending
const sortedCategories = computed(() => {
    return [...categories.value].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));
});

// Fungsi Pengecekan Status Sinkronisasi Overhead
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

// Fungsi Pengecekan Sinkronisasi HPP Resep
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
    if (await confirm('Sinkronkan Resep & HPP?', 'Semua resep dan kalkulasi HPP menu aktif akan diperbarui menggunakan avg_cost terbaru dari master bahan baku.')) {
        isSyncingRecipe.value = true;
        try {
            await axios.post('/api/menus/sync-recipes');
            showRecipeBanner.value = false;
            isRecipeOutOfSync.value = false;
            success('Berhasil', 'Seluruh resep menu berhasil disinkronkan dengan avg_cost terbaru.');
            fetchMenus();
        } catch (err) {
            error('Gagal', 'Gagal melakukan sinkronisasi resep.');
        } finally {
            isSyncingRecipe.value = false;
        }
    }
};

// --- LOGIKA PIPELINE FILTER & SORTING (FRONT-END) ---
// --- LOGIKA PIPELINE FILTER & SORTING (FRONT-END) ---
const filteredAndSortedMenus = computed(() => {
    let result = [...menus.value];

    // Filter berdasarkan Pencarian Teks
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim();
        result = result.filter(menu => menu.name.toLowerCase().includes(query));
    }

    // Filter berdasarkan Tab Kategori Aktif
    if (activeCategoryId.value !== 'all') {
        result = result.filter(menu => {
            if (!menu.categories || !Array.isArray(menu.categories)) return false;
            return menu.categories.some((cat: any) => cat.id === activeCategoryId.value);
        });

        // JIKA KATEGORI SPESIFIK DIPILIH: Urutkan otomatis berdasarkan urutan (pivot.sort) di kategori tersebut
        result.sort((a, b) => {
            const catA = a.categories?.find((c: any) => c.id === activeCategoryId.value);
            const catB = b.categories?.find((c: any) => c.id === activeCategoryId.value);
            
            const sortA = catA?.pivot?.sort ?? 0;
            const sortB = catB?.pivot?.sort ?? 0;

            return sortA - sortB;
        });

    } else {
        // JIKA DI TAB "SEMUA MENU": Gunakan sorting manual dari header kolom (Nama, HPP, dll)
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
    if (await confirm('Sinkronkan Overhead?', `Nilai overhead di semua menu akan disesuaikan menjadi Rp ${masterOverheadTotal.value.toLocaleString()}. HPP dan Harga Jual Ojol akan di-kalkulasi ulang otomatis.`)) {
        isSyncing.value = true;
        try {
            await axios.post('/api/menus/overhead-sync');
            success('Berhasil', 'Seluruh menu berhasil disinkronkan dengan master overhead terbaru.');
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
        case 'gofood': return 'bg-red-50 text-red-700 border-red-100 dark:bg-red-950/20 dark:text-red-400 dark:border-red-900/30';
        case 'grabfood': return 'bg-green-50 text-green-700 border-green-100 dark:bg-green-950/20 dark:text-green-400 dark:border-green-900/30';
        case 'shopeefood': return 'bg-orange-50 text-orange-700 border-orange-100 dark:bg-orange-950/20 dark:text-orange-400 dark:border-orange-900/30';
        default: return 'bg-muted text-muted-foreground';
    }
};

const openCategorySortModal = (category: any, event: Event) => {
    event.stopPropagation(); // Mencegah klik tab terpicu
    selectedCategoryForSort.value = category;
    showCategorySortModal.value = true;
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
    <div class="p-4 sm:p-6 space-y-6 max-w-full overflow-x-hidden">
        <!-- Header Halaman -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5 border-border/60">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Daftar Menu Produksi</h1>
                <p class="text-xs text-muted-foreground mt-1">
                    Kelola data resep, kalkulasi overhead cost, dan optimasi harga jual multi-channel secara real-time.
                </p>
            </div>
            
            <div class="flex items-center gap-2 sm:shrink-0 flex-wrap justify-end">
                <Button 
                    v-if="isOutOfSync" 
                    variant="outline"
                    class="h-9 border-amber-500/50 text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/20 font-semibold text-xs flex items-center gap-2 animate-pulse"
                    :disabled="isSyncing"
                    @click="handleSyncNow"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    {{ isSyncing ? 'Syncing...' : 'Sync Overhead' }}
                </Button>

                <Button 
                    v-if="isRecipeOutOfSync" 
                    variant="outline"
                    class="h-9 border-amber-500/50 text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/20 font-semibold text-xs flex items-center gap-2 animate-pulse"
                    :disabled="isSyncingRecipe"
                    @click="executeRecipeSync"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    {{ isSyncingRecipe ? 'Syncing...' : 'Sync Bahan & HPP' }}
                </Button>
                
                <Button variant="outline" size="sm" class="h-9 text-xs font-medium" @click="showCategoryModal = true">
                    📂 Kelola Kategori
                </Button>
                
                <Button size="sm" class="h-9 text-xs font-semibold shadow-sm" @click="openCreate">
                    + New Menu
                </Button>
            </div>
        </div>

        <!-- Layout Utama: Grid Kiri (Tab Vertikal Kategori) & Kanan (Tabel Menu) -->
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-6 items-start">
            
            <!-- Sidebar / Tab Vertikal Kategori -->
            <div class="md:col-span-1 bg-card border rounded-xl p-3 shadow-sm space-y-1 sticky top-4">
                <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider px-3 py-2">Kategori Menu</h4>
                
                <!-- Tab Semua Kategori -->
                <button
                    @click="activeCategoryId = 'all'"
                    :class="[
                        'w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition-colors text-left',
                        activeCategoryId === 'all' 
                            ? 'bg-primary text-primary-foreground font-semibold shadow-sm' 
                            : 'hover:bg-muted text-muted-foreground hover:text-foreground'
                    ]"
                >
                    <span>Semua Menu</span>
                    <span class="text-[10px] opacity-80 px-1.5 py-0.5 rounded bg-background/20">{{ menus.length }}</span>
                </button>

                <!-- Daftar Kategori Vertikal -->
                <div v-for="cat in sortedCategories" :key="cat.id" class="group relative">
                    <button
                        @click="activeCategoryId = cat.id"
                        :class="[
                            'w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition-colors text-left pr-12',
                            activeCategoryId === cat.id 
                                ? 'bg-primary text-primary-foreground font-semibold shadow-sm' 
                                : 'hover:bg-muted text-muted-foreground hover:text-foreground',
                            !cat.is_visible ? 'opacity-50 line-through' : ''
                        ]"
                    >
                        <span class="truncate">{{ cat.name }}</span>
                    </button>

                    <!-- Tombol Urutkan Menu di Paling Kanan Kategori (Muncul saat Hover atau Kategori Aktif) -->
                    <button
                        type="button"
                        @click="openCategorySortModal(cat, $event)"
                        title="Urutkan menu dalam kategori ini"
                        :class="[
                            'absolute right-1.5 top-1/2 -translate-y-1/2 p-1 rounded text-[10px] transition-all opacity-0 group-hover:opacity-100 flex items-center gap-0.5',
                            activeCategoryId === cat.id ? 'opacity-100 text-primary-foreground bg-black/10 hover:bg-black/20' : 'text-muted-foreground hover:text-foreground hover:bg-muted-foreground/10'
                        ]"
                    >
                        ↕️ Urutkan
                    </button>
                </div>
            </div>

            <!-- Konten Utama: Search & Tabel Menu -->
            <div class="md:col-span-3 lg:col-span-4 space-y-4">
                
                <!-- Search Bar -->
                <div class="flex items-center gap-2.5 p-3 bg-muted/20 rounded-xl border border-border/60 shadow-sm">
                    <div class="w-full relative">
                        <Input 
                            v-model="searchQuery" 
                            placeholder="Cari nama menu produksi..." 
                            class="w-full text-xs h-9 pl-3 pr-8"
                        />
                        <span v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-2 text-muted-foreground hover:text-foreground cursor-pointer text-sm font-bold">&times;</span>
                    </div>
                </div>

                <!-- Alert Bulk Delete -->
                <div 
                    v-if="selectedIds.length > 0" 
                    class="flex items-center justify-between rounded-xl bg-destructive/5 px-4 py-2.5 border border-destructive/20 animate-in fade-in zoom-in-95 duration-200 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center justify-center h-5 px-1.5 rounded bg-destructive/10 text-destructive font-bold">
                            {{ selectedIds.length }}
                        </span>
                        <span class="font-medium text-destructive/90">menu dipilih untuk dimodifikasi secara massal</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="selectedIds = []" class="text-muted-foreground hover:text-foreground text-xs font-medium">
                            Batal
                        </button>
                        <Button variant="destructive" size="sm" class="h-8 text-xs font-semibold px-3" @click="bulkDelete">
                            Hapus Terpilih
                        </Button>
                    </div>
                </div>

                <!-- Banner Overhead Out of Sync -->
                <div 
                    v-if="showBanner" 
                    class="flex flex-col md:flex-row md:items-center justify-between gap-4 rounded-xl bg-gradient-to-r from-amber-50 to-orange-50/50 dark:from-amber-950/10 dark:to-transparent px-5 py-4 border border-amber-200/60 dark:border-amber-900/40 shadow-sm"
                >
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white text-xs font-bold">⚠️</div>
                        <div class="space-y-0.5">
                            <h5 class="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-400">Perubahan Master Overhead Terdeteksi</h5>
                            <p class="text-xs text-muted-foreground">Total nominal biaya overhead aktif saat ini berubah menjadi <span class="font-bold text-foreground">{{ currency(masterOverheadTotal) }}</span>.</p>
                        </div>
                    </div>
                    <Button size="sm" class="bg-amber-600 hover:bg-amber-700 text-white text-xs h-8" :disabled="isSyncing" @click="handleSyncNow">
                        {{ isSyncing ? 'Syncing...' : 'Sync Sekarang' }}
                    </Button>
                </div>

                <!-- Banner Recipe Out of Sync -->
                <div 
                    v-if="showRecipeBanner" 
                    class="flex flex-col md:flex-row md:items-center justify-between gap-4 rounded-xl bg-gradient-to-r from-amber-50 to-orange-50/50 dark:from-amber-950/10 dark:to-transparent px-5 py-4 border border-amber-200/60 dark:border-amber-900/40 shadow-sm"
                >
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white text-xs font-bold">⚠️</div>
                        <div class="space-y-0.5">
                            <h5 class="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-400">Perubahan Harga Bahan Baku Terdeteksi</h5>
                            <p class="text-xs text-muted-foreground">Terdapat perubahan avg_cost pada master bahan baku yang belum disinkronkan ke resep menu.</p>
                        </div>
                    </div>
                    <Button size="sm" class="bg-amber-600 hover:bg-amber-700 text-white text-xs h-8" :disabled="isSyncingRecipe" @click="executeRecipeSync">
                        {{ isSyncingRecipe ? 'Syncing...' : 'Sync Resep Sekarang' }}
                    </Button>
                </div>

                <!-- Loading State -->
                <div v-if="isLoading" class="mt-8 text-center text-muted-foreground text-xs">
                    Memuat data menu...
                </div>

                <!-- Tabel Data Menu -->
                <div v-else-if="filteredAndSortedMenus.length > 0" class="w-full">
                    <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-sm w-full">
                        <table class="w-full text-sm text-left min-w-[750px]">
                            <thead class="bg-muted/50 text-muted-foreground text-xs">
                                <tr>
                                    <th class="px-4 py-3.5 w-10">
                                        <input type="checkbox" 
                                            :checked="selectedIds.length === filteredAndSortedMenus.length && filteredAndSortedMenus.length > 0" 
                                            @change="toggleSelectAll" />
                                    </th>
                                    <th class="px-4 py-3.5 font-medium cursor-pointer select-none hover:text-foreground transition-colors" @click="toggleSort('name')">
                                        Nama Menu 
                                        <span v-if="sortBy === 'name'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                    </th>
                                    <th class="px-4 py-3.5 font-medium">Kategori</th>
                                    <th class="px-4 py-3.5 font-medium">Overhead Terpasang</th>
                                    <th class="px-4 py-3.5 font-medium cursor-pointer select-none hover:text-foreground transition-colors" @click="toggleSort('hpp')">
                                        HPP 
                                        <span v-if="sortBy === 'hpp'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                    </th>
                                    <th class="px-4 py-3.5 font-medium">Harga Jual Kanal (Margin)</th>
                                    <th class="px-4 py-3.5 font-medium">Status</th>
                                    <th class="px-4 py-3.5 font-medium text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/50 text-xs">
                                <tr v-for="menu in filteredAndSortedMenus" :key="menu.id" class="hover:bg-muted/30 transition-colors">
                                    <td class="px-4 py-3.5">
                                        <input type="checkbox" v-model="selectedIds" :value="menu.id" class="rounded border-border" />
                                    </td>
                                    <td class="px-4 py-3.5 font-medium text-foreground whitespace-nowrap">{{ menu.name }}</td>
                                    <td class="px-4 py-3.5 text-muted-foreground">
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="cat in menu.categories" :key="cat.id" class="px-1.5 py-0.5 rounded bg-muted text-[10px]">
                                                {{ cat.name }}
                                            </span>
                                            <span v-if="!menu.categories || menu.categories.length === 0">-</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-medium whitespace-nowrap">
                                        Rp {{ Number(menu.overhead_cost || 0).toLocaleString() }}
                                        <span v-if="Number(menu.overhead_cost) !== masterOverheadTotal" class="ml-1.5 inline-block text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded font-normal">
                                            Outdated
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-muted-foreground font-medium whitespace-nowrap">{{ currency(menu.hpp) }}</td>
                                    
                                    <td class="px-4 py-3.5">
                                        <div v-if="Array.isArray(menu.prices) && menu.prices.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 w-full min-w-[260px]">
                                            <div 
                                                v-for="price in (menu.prices as any[])" 
                                                :key="price.id"
                                                :class="['px-2.5 py-1.5 rounded-lg border text-[11px] flex flex-col justify-between shadow-sm transition-all', getChannelClass(price.channel)]"
                                            >
                                                <div class="flex items-center justify-between border-b border-current/10 pb-0.5">
                                                    <span class="text-[9px] uppercase font-bold tracking-wider opacity-80">{{ price.channel }} </span>
                                                    <span class="text-[9px] opacity-60 mt-0.5">{{ price.margin_percent }}%</span>
                                                </div>
                                                <div class="flex flex-col mt-1.5">
                                                    <span class="font-bold text-xs tracking-tight">
                                                        {{ currency(price.selling_price) }}
                                                    </span>
                                                    <span class="text-[9px] opacity-60 mt-0.5">Margin: {{ currency(price.nett_price * (price.margin_percent / 100)) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground italic">Belum disetting</span>
                                    </td>

                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <span :class="menu.is_active ? 'bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'" class="px-2 py-0.5 rounded-full text-[10px] font-medium">
                                            {{ menu.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Button variant="outline" size="sm" class="h-7 px-2 text-[11px] font-medium" @click="openEdit(menu)">Edit</Button>
                                            <Button variant="destructive" size="sm" class="h-7 px-2 text-[11px] font-medium" @click="handleDelete(menu)">Hapus</Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center py-20 text-center border rounded-lg bg-card border-dashed border-border">
                    <h3 class="text-sm font-semibold text-foreground">Data tidak ditemukan</h3>
                    <p class="mt-1 text-xs text-muted-foreground">Tidak ada menu produksi yang cocok dengan kriteria pencarian Anda.</p>
                </div>
            </div>
        </div>
    </div>

    <MenuModal 
        :show="showModal" 
        :menu="activeMenu" 
        :masterOverhead="masterOverheadTotal"
        @close="showModal = false"
        @saved="handleSaved" 
    />
    <CategoryModal 
        :show="showCategoryModal"
        @close="showCategoryModal = false"
        @updated="handleCategoryUpdated"
    />

    <!-- Komponen Modal Drag & Drop untuk Mengatur Urutan Menu di dalam Kategori -->
    <CategorySortModal
        :show="showCategorySortModal"
        :category="selectedCategoryForSort"
        @close="showCategorySortModal = false"
        @updated="fetchMenus"
    />
</template>