<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import MenuModal from './MenuModal.vue';
import CategorySortModal from './CategorySortModal.vue';
import CategoryModal from './CategoryModal.vue';
import { useSwal } from '@/composables/useSwal';
import { useMenus } from '@/composables/useMenus';
import { useMaterials } from '@/composables/useMaterials';
import { useCategories } from '@/composables/useCategories';
import axios from 'axios';

import Select from '@/components/ui/select/Select.vue';
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue';
import SelectValue from '@/components/ui/select/SelectValue.vue';
import SelectContent from '@/components/ui/select/SelectContent.vue';
import SelectItem from '@/components/ui/select/SelectItem.vue';
import ConifrmModal from '@/components/ConifrmModal.vue';

defineOptions({ layout: AppSidebarLayout });

const { confirm, success, error } = useSwal();
const { menus, isLoading, fetchMenus } = useMenus();
const { fetchMaterialOptions } = useMaterials();
const { categories, fetchCategories } = useCategories();

// State Fungsionalitas Modal
const showModal = ref(false);
const showCategoryModal = ref(false);
const showCategorySortModal = ref(false);
const activeMenu = ref<any>(null);
const selectedCategoryForSort = ref<any>(null);
const isConfirmModalOpen = ref(false);
const confirmModalConfig = ref({
    title: 'Hapus Menu?',
    message: 'Tindakan ini tidak dapat dibatalkan. Menu akan dihapus permanen.',
    confirmText: 'Ya, Hapus',
    loading: false,
    action: async () => {}
});

// State Navigasi & Filter
const activeCategoryId = ref<string>('all');
const selectedOutletId = ref<string>(localStorage.getItem('active_outlet_id') || 'all');

// State Search & Sort
const searchQuery = ref('');
const sortBy = ref('name'); 
const sortDirection = ref<'asc' | 'desc'>('asc'); 
const selectedIds = ref<string[]>([]);

// State Sync Overhead & Resep
const isOutOfSync = ref(false);
const showBanner = ref(false); 
const masterOverheadTotal = ref(0);
const isSyncing = ref(false);
const isSyncingRecipe = ref(false);
const isRecipeOutOfSync = ref(false);
const showRecipeBanner = ref(false);

const loadData = () => {
    const currentActiveOutlet = localStorage.getItem('active_outlet_id') || 'all';
    const params = { outlet_id: currentActiveOutlet };
    fetchMenus(params);
    fetchCategories(params);
};

const handleOutletChanged = () => {
    selectedOutletId.value = localStorage.getItem('active_outlet_id') || 'all';
    loadData();
};

const handleCategoryUpdated = () => {
    loadData();
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
        console.error('Gagal mengecek overhead', err);
    }
};

const checkRecipeSyncStatus = async () => {
    try {
        const res = await axios.get('/api/menus/recipe-sync-status');
        isRecipeOutOfSync.value = res.data.is_out_of_sync;
        if (res.data.is_out_of_sync) showRecipeBanner.value = true; 
    } catch (err) {
        console.error('Gagal mengecek HPP bahan', err);
    }
};

const executeRecipeSync = async () => {
    const isConfirmed = await confirm('Sinkronkan Resep & HPP?', 'Semua resep dan kalkulasi HPP menu aktif akan diperbarui menggunakan avg_cost terbaru.');
    if (isConfirmed) {
        isSyncingRecipe.value = true;
        try {
            await axios.post('/api/menus/sync-recipes');
            showRecipeBanner.value = false;
            isRecipeOutOfSync.value = false;
            success('Berhasil', 'Seluruh resep menu berhasil disinkronkan.');
            loadData();
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

const openCreate = () => { activeMenu.value = null; showModal.value = true; };
const openEdit = (menu: any) => { activeMenu.value = menu; showModal.value = true; };
const handleSaved = () => { showModal.value = false; loadData(); success('Berhasil', 'Data menu berhasil disimpan!'); };

const toggleSelectAll = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    selectedIds.value = checked ? filteredAndSortedMenus.value.map(m => m.id) : [];
};

const handleDelete = (menu: any) => {
    confirmModalConfig.value = {
        title: 'Hapus Menu?',
        message: `Menu "${menu.name}" akan dihapus permanen dari sistem.`,
        confirmText: 'Ya, Hapus',
        loading: false,
        action: async () => {
            confirmModalConfig.value.loading = true;
            try {
                await axios.delete(`/api/menus/${menu.id}`);
                loadData();
                success('Berhasil', 'Menu berhasil dihapus.');
                isConfirmModalOpen.value = false;
            } catch (e: any) {
                const message = e.response?.data?.message || 'Gagal menghapus menu.';
                error('Gagal', message);
                isConfirmModalOpen.value = false;

            } finally {
                confirmModalConfig.value.loading = false;
            }
        }
    };
    isConfirmModalOpen.value = true;
};

// Handler Hapus Massal (Bulk Delete)
const bulkDelete = () => {
    confirmModalConfig.value = {
        title: 'Hapus Menu Terpilih?',
        message: `Sebanyak ${selectedIds.value.length} menu yang dipilih akan dihapus permanen.`,
        confirmText: 'Ya, Hapus Semua',
        loading: false,
        action: async () => {
            confirmModalConfig.value.loading = true;
            try {
                await axios.post('/api/menus/bulk-destroy', { ids: selectedIds.value });
                selectedIds.value = [];
                loadData();
                success('Berhasil', 'Menu terpilih berhasil dihapus.');
                isConfirmModalOpen.value = false;
            } catch (e: any) {
                const message = e.response?.data?.message || 'Gagal menghapus menu.';
                error('Gagal', message);
            } finally {
                confirmModalConfig.value.loading = false;
            }
        }
    };
    isConfirmModalOpen.value = true;
};

const handleSyncNow = async () => {
    const isConfirmed = await confirm('Sinkronkan Overhead?', `Nilai overhead di semua menu akan disesuaikan menjadi Rp ${masterOverheadTotal.value.toLocaleString()}.`);
    if (isConfirmed) {
        isSyncing.value = true;
        try {
            await axios.post('/api/menus/overhead-sync');
            success('Berhasil', 'Seluruh menu berhasil disinkronkan.');
            isOutOfSync.value = false;
            showBanner.value = false;
            loadData();
        } catch (err) {
            error('Gagal', 'Terjadi kesalahan saat sinkronisasi.');
        } finally {
            isSyncing.value = false;
        }
    }
};

onMounted(() => {
    loadData();
    fetchMaterialOptions();
    checkSyncStatus();
    checkRecipeSyncStatus();
    window.addEventListener('outlet-changed', handleOutletChanged);
});

onUnmounted(() => {
    window.removeEventListener('outlet-changed', handleOutletChanged);
});
</script>

<template>
    <div class="p-6 sm:p-8 space-y-8 max-w-full overflow-x-hidden font-sans">
        <!-- Header Halaman -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pb-2">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">Daftar Menu Produksi</h1>
                <p class="text-xs sm:text-sm text-muted-foreground mt-1">
                    Kelola data resep, kalkulasi overhead cost, dan optimasi harga jual multi-channel secara real-time.
                </p>
            </div>
            
            <div class="flex items-center gap-2.5 sm:shrink-0 flex-wrap justify-end">
                <Button 
                    v-if="isOutOfSync" 
                    variant="outline"
                    class="h-9 px-4 rounded-xl border-amber-500/30 text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/30 font-semibold text-xs transition-all flex items-center gap-2"
                    :disabled="isSyncing"
                    @click="handleSyncNow"
                >
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    {{ isSyncing ? 'Syncing...' : 'Sync Overhead' }}
                </Button>

                <Button 
                    v-if="isRecipeOutOfSync" 
                    variant="outline"
                    class="h-9 px-4 rounded-xl border-amber-500/30 text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/30 font-semibold text-xs transition-all flex items-center gap-2"
                    :disabled="isSyncingRecipe"
                    @click="executeRecipeSync"
                >
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    {{ isSyncingRecipe ? 'Syncing...' : 'Sync Bahan & HPP' }}
                </Button>
                
                <Button variant="outline" size="sm" class="h-9 px-4 rounded-xl text-xs font-semibold shadow-xs" @click="showCategoryModal = true">
                    📂 Kategori
                </Button>
                
                <Button size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm bg-foreground text-background hover:opacity-90 transition-all" @click="openCreate">
                    + New Menu
                </Button>
            </div>
        </div>

        <div class="space-y-4">
            <!-- Filter & Search Toolbar -->
            <div class="flex flex-col sm:flex-row items-center gap-3 p-3 bg-card rounded-2xl border border-border/60 shadow-xs backdrop-blur-md">
                <div class="w-full sm:w-72">
                    <Select v-model="activeCategoryId">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih Kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Semua Menu ({{ menus.length }})
                            </SelectItem>
                            <SelectItem 
                                v-for="cat in sortedCategories" 
                                :key="cat.id" 
                                :value="cat.id" 
                                :class="['text-xs font-semibold rounded-xl cursor-pointer py-2 px-3', !cat.is_visible ? 'opacity-50 line-through' : '']"
                            >
                                {{ cat.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-full relative">
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Cari nama menu produksi..." 
                        class="w-full text-xs h-10 pl-4 pr-10 bg-secondary/60 border-border/80 rounded-xl font-medium focus:ring-1 focus:ring-ring"
                    />
                    <span v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3.5 top-2.5 text-muted-foreground hover:text-foreground cursor-pointer text-base font-bold">&times;</span>
                </div>
            </div>

            <!-- Alert Bulk Delete -->
            <div 
                v-if="selectedIds.length > 0" 
                class="flex items-center justify-between rounded-2xl bg-destructive/10 px-5 py-3 border border-destructive/20 animate-in fade-in zoom-in-95 duration-200 shadow-xs"
            >
                <div class="flex items-center gap-2.5 text-xs">
                    <span class="inline-flex items-center justify-center h-6 px-2 rounded-lg bg-destructive text-destructive-foreground font-bold text-xs">
                        {{ selectedIds.length }}
                    </span>
                    <span class="font-semibold text-foreground">menu dipilih untuk dimodifikasi secara massal</span>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="selectedIds = []" class="text-muted-foreground hover:text-foreground text-xs font-semibold">
                        Batal
                    </button>
                    <Button variant="destructive" size="sm" class="h-8 rounded-xl text-xs font-bold px-4" @click="bulkDelete">
                        Hapus Terpilih
                    </Button>
                </div>
            </div>

            <!-- Banner Overhead Out of Sync -->
            <div v-if="showBanner" class="flex flex-col md:flex-row md:items-center justify-between gap-4 rounded-2xl bg-card px-6 py-4 border border-amber-500/30 shadow-xs">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white text-xs font-bold">⚠️</div>
                    <div class="space-y-0.5">
                        <h5 class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Perubahan Master Overhead Terdeteksi</h5>
                        <p class="text-xs text-muted-foreground">Total nominal biaya overhead aktif saat ini berubah menjadi <span class="font-bold text-foreground">{{ currency(masterOverheadTotal) }}</span>.</p>
                    </div>
                </div>
                <Button size="sm" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs h-9 rounded-xl px-4" :disabled="isSyncing" @click="handleSyncNow">
                    {{ isSyncing ? 'Syncing...' : 'Sync Sekarang' }}
                </Button>
            </div>

            <!-- Banner Recipe Out of Sync -->
            <div v-if="showRecipeBanner" class="flex flex-col md:flex-row md:items-center justify-between gap-4 rounded-2xl bg-card px-6 py-4 border border-amber-500/30 shadow-xs">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white text-xs font-bold">⚠️</div>
                    <div class="space-y-0.5">
                        <h5 class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Perubahan Harga Bahan Baku Terdeteksi</h5>
                        <p class="text-xs text-muted-foreground">Terdapat perubahan avg_cost pada master bahan baku yang belum disinkronkan ke resep menu.</p>
                    </div>
                </div>
                <Button size="sm" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs h-9 rounded-xl px-4" :disabled="isSyncingRecipe" @click="executeRecipeSync">
                    {{ isSyncingRecipe ? 'Syncing...' : 'Sync Resep Sekarang' }}
                </Button>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="mt-12 text-center text-muted-foreground text-xs font-medium">
                Memuat data menu...
            </div>

            <!-- Tabel Data Menu -->
            <div v-else-if="filteredAndSortedMenus.length > 0" class="w-full">
                <div class="overflow-x-auto rounded-2xl border border-border/70 bg-card shadow-xs w-full">
                    <table class="w-full text-sm text-left min-w-[750px]">
                        <thead class="bg-secondary/60 text-muted-foreground text-xs border-b border-border/70">
                            <tr>
                                <th class="px-5 py-3.5 w-10">
                                    <input type="checkbox" :checked="selectedIds.length === filteredAndSortedMenus.length && filteredAndSortedMenus.length > 0" @change="toggleSelectAll" class="rounded border-border accent-primary cursor-pointer" />
                                </th>
                                <th class="px-5 py-3.5 font-bold cursor-pointer select-none hover:text-foreground transition-colors" @click="toggleSort('name')">
                                    Nama Menu <span v-if="sortBy === 'name'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th class="px-5 py-3.5 font-bold">Kategori</th>
                                <th class="px-5 py-3.5 font-bold">Overhead Terpasang</th>
                                <th class="px-5 py-3.5 font-bold cursor-pointer select-none hover:text-foreground transition-colors" @click="toggleSort('hpp')">
                                    HPP <span v-if="sortBy === 'hpp'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th class="px-5 py-3.5 font-bold">Harga Jual Kanal (Margin)</th>
                                <th class="px-5 py-3.5 font-bold">Status</th>
                                <th class="px-5 py-3.5 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60 text-xs">
                            <tr v-for="menu in filteredAndSortedMenus" :key="menu.id" class="hover:bg-secondary/40 transition-colors">
                                <td class="px-5 py-4">
                                    <input type="checkbox" v-model="selectedIds" :value="menu.id" class="rounded border-border accent-primary cursor-pointer" />
                                </td>
                                <td class="px-5 py-4 font-bold text-foreground whitespace-nowrap">{{ menu.name }}</td>
                                <td class="px-5 py-4 text-muted-foreground">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="cat in menu.categories" :key="cat.id" class="px-2 py-0.5 rounded-lg bg-secondary text-secondary-foreground text-[10px] font-bold">
                                            {{ cat.name }}
                                        </span>
                                        <span v-if="!menu.categories || menu.categories.length === 0">-</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-bold whitespace-nowrap text-foreground font-mono">
                                    Rp {{ Number(menu.overhead_cost || 0).toLocaleString() }}
                                    <span v-if="Number(menu.overhead_cost) !== masterOverheadTotal" class="ml-2 inline-block text-[10px] bg-destructive/10 text-destructive px-2 py-0.5 rounded-md font-bold">
                                        Outdated
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-muted-foreground font-semibold whitespace-nowrap font-mono">{{ currency(menu.hpp) }}</td>
                                
                                <td class="px-5 py-4">
                                    <div v-if="Array.isArray(menu.prices) && menu.prices.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 w-full min-w-[280px]">
                                        <div v-for="price in (menu.prices as any[])" :key="price.id" class="px-3 py-2 rounded-xl border border-border/80 bg-secondary/40 text-[11px] flex flex-col justify-between shadow-2xs">
                                            <div class="flex items-center justify-between border-b border-border/60 pb-1">
                                                <span class="text-[9px] uppercase font-bold tracking-wider text-muted-foreground">{{ price.channel }}</span>
                                                <span class="text-[10px] font-bold text-foreground">{{ price.margin_percent }}%</span>
                                            </div>
                                            <div class="flex flex-col mt-2">
                                                <span class="font-extrabold text-xs tracking-tight text-foreground font-mono">{{ currency(price.selling_price) }}</span>
                                                <span class="text-[9px] text-muted-foreground mt-0.5 font-medium">Margin: {{ currency(price.nett_price * (price.margin_percent / 100)) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground italic">Belum disetting</span>
                                </td>

                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span :class="menu.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold' : 'bg-secondary text-muted-foreground font-semibold'" class="px-2.5 py-1 rounded-full text-[10px]">
                                        {{ menu.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="outline" size="sm" class="h-8 px-3 rounded-xl text-xs font-semibold" @click="openEdit(menu)">Edit</Button>
                                        <Button variant="destructive" size="sm" class="h-8 px-3 rounded-xl text-xs font-semibold" @click="handleDelete(menu)">Hapus</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-24 text-center border border-dashed rounded-3xl bg-card border-border/80 shadow-xs">
                <h3 class="text-sm font-bold text-foreground">Data tidak ditemukan</h3>
                <p class="mt-1 text-xs text-muted-foreground">Tidak ada menu produksi yang cocok dengan kriteria pencarian Anda.</p>
            </div>
        </div>
    </div>

    <MenuModal :show="showModal" :menu="activeMenu" :masterOverhead="masterOverheadTotal" @close="showModal = false" @saved="handleSaved" />
    <CategoryModal :show="showCategoryModal" @close="showCategoryModal = false" @updated="handleCategoryUpdated" />
    <CategorySortModal :show="showCategorySortModal" :category="selectedCategoryForSort" @close="showCategorySortModal = false" @updated="loadData" />
    <!-- Komponen Modal Konfirmasi Reusable -->
    <ConifrmModal 
        :show="isConfirmModalOpen"
        :title="confirmModalConfig.title"
        :message="confirmModalConfig.message"
        :confirmText="confirmModalConfig.confirmText"
        :loading="confirmModalConfig.loading"
        @close="isConfirmModalOpen = false"
        @confirm="confirmModalConfig.action"
    />
</template>