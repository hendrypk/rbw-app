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
import { useOutlet } from '@/composables/useOutlet.js';

defineOptions({ layout: AppSidebarLayout });

const { success, error } = useSwal();
// 1. Ekstrak 'meta' dari useMenus untuk data pagination (berasal dari API Laravel)
const { menus, isLoading, meta, fetchMenus } = useMenus();
const { fetchMaterialOptions } = useMaterials();
const { categories, fetchCategories } = useCategories();

// State Fungsionalitas Modal
const isCategoryOpen = ref(false);
const isChannelOpen = ref(false);
const showModal = ref(false);
const showCategoryModal = ref(false);
const showCategorySortModal = ref(false);
const activeMenu = ref<any>(null);
const selectedCategoryForSort = ref<any>(null);
const selectedChannelFilter = ref<string>('offline');
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

const { getOutletId, getOutletParam } = useOutlet();

// 2. Modifikasi loadData untuk menerima parameter halaman
const loadData = (page = 1) => {
    const params = {
        ...getOutletParam(),
        page, // Sisipkan parameter halaman ke API
    };
    fetchMenus(params);
    fetchCategories(getOutletParam());
    checkSyncStatus();
    checkRecipeSyncStatus();
};

// 3. Tambahkan fungsi changePage untuk tombol Next/Prev
const changePage = (page: number) => {
    if (page >= 1 && (!meta.value || page <= meta.value.last_page)) {
        loadData(page);
    }
};

const handleOutletChanged = () => {
    selectedOutletId.value = getOutletId() || 'all';
    loadData(1); // Reset ke halaman 1 saat outlet berubah
    checkSyncStatus();
    checkRecipeSyncStatus();
};

const handleCategoryUpdated = () => {
    loadData();
};

const sortedCategories = computed(() => {
    return [...categories.value].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));
});

const checkSyncStatus = async () => {
    try {
        const params = getOutletParam();
        const res = await axios.get('/api/menus/overhead-sync-status', { params });
        isOutOfSync.value = res.data.is_out_of_sync;
        if (res.data.is_out_of_sync) showBanner.value = true;
        masterOverheadTotal.value = res.data.master_total;
    } catch (err) {
        console.error('Gagal mengecek overhead', err);
    }
};

const handleSyncNow = async () => {
    const isConfirmed = await confirm('Sinkronkan Overhead?', `Nilai overhead di semua menu akan disesuaikan menjadi Rp ${masterOverheadTotal.value.toLocaleString()}.`);
    if (isConfirmed) {
        isSyncing.value = true;
        try {
            const payload = getOutletParam();
            await axios.post('/api/menus/overhead-sync', payload);
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

const checkRecipeSyncStatus = async () => {
    try {
        const params = getOutletParam();
        const res = await axios.get('/api/menus/recipe-sync-status', { params });
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
            const payload = getOutletParam();
            await axios.post('/api/menus/sync-recipes', payload);
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

    if (selectedChannelFilter.value !== 'all') {
        result = result.filter(menu => {
            if (!menu.prices || !Array.isArray(menu.prices)) return false;
            const channelPrice = menu.prices.find((p: any) => p.channel === selectedChannelFilter.value);
            return channelPrice && Number(channelPrice.selling_price) > 0;
        });
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

const toggleMenuStatus = async (menu: any) => {
    try {
        const newStatus = !menu.is_active;
        await axios.patch(`/api/menus/${menu.id}/status`, { is_active: newStatus });

        menu.is_active = newStatus;
        success('Berhasil', `Status menu "${menu.name}" berhasil diperbarui.`);
    } catch (e: any) {
        const message = e.response?.data?.message || 'Gagal memperbarui status menu.';
        error('Gagal', message);
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
<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground">Daftar Menu Produksi</h1>
            <p class="text-xs text-muted-foreground mt-0.5">
                Kelola data resep, kalkulasi overhead cost, dan optimasi harga jual multi-channel.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <!-- Sync Action (Muncul jika Out of Sync) -->
            <Button
                v-if="isOutOfSync"
                variant="ghost"
                size="sm"
                class="h-8 px-3 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 font-medium text-xs transition-all flex items-center gap-1.5"
                :disabled="isSyncing"
                @click="handleSyncNow"
            >
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                {{ isSyncing ? 'Syncing...' : 'Sync Overhead' }}
            </Button>

            <Button
                v-if="isRecipeOutOfSync"
                variant="ghost"
                size="sm"
                class="h-8 px-3 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 font-medium text-xs transition-all flex items-center gap-1.5"
                :disabled="isSyncingRecipe"
                @click="executeRecipeSync"
            >
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                {{ isSyncingRecipe ? 'Syncing...' : 'Sync Bahan & HPP' }}
            </Button>

            <!-- Kategori Button -->
            <Button
                variant="outline"
                size="sm"
                class="h-8 px-3 rounded-lg text-xs font-medium border-border/60 bg-transparent hover:bg-secondary/60 transition-all shadow-none"
                @click="showCategoryModal = true"
            >
                Kategori
            </Button>

            <!-- Primary Action Button (Apple Style: Solid Dark/Light dengan sudut melengkung proporsional) -->
            <Button
                size="sm"
                class="h-8 px-4 rounded-lg text-xs font-medium bg-foreground text-background hover:opacity-90 transition-all shadow-none"
                @click="openCreate"
            >
                + Menu Baru
            </Button>
        </div>
    </div>

    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 py-1">
            <div class="flex items-center gap-2 flex-wrap">
                <Select v-model="activeCategoryId" v-model:open="isCategoryOpen">
                    <SelectTrigger class="w-[160px] h-8 text-xs rounded-lg border-border/60 bg-transparent shadow-none font-medium flex items-center justify-between">
                        <SelectValue placeholder="Kategori" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-60 shrink-0 ml-1 transition-transform duration-200">
                            <path :d="isCategoryOpen ? 'm18 15-6-6-6 6' : 'm6 9 6 6 6-6'" />
                        </svg>
                    </SelectTrigger>
                    <SelectContent class="rounded-xl">
                        <SelectItem value="all" class="text-xs font-medium">Semua Kategori</SelectItem>
                        <SelectItem
                            v-for="cat in sortedCategories"
                            :key="cat.id"
                            :value="cat.id"
                            :class="['text-xs font-medium rounded-lg cursor-pointer py-1.5 px-2.5', !cat.is_visible ? 'opacity-50 line-through' : '']"
                        >
                            {{ cat.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="selectedChannelFilter" v-model:open="isChannelOpen">
                    <SelectTrigger class="w-[150px] h-8 text-xs rounded-lg border-border/60 bg-transparent shadow-none font-medium flex items-center justify-between">
                        <SelectValue placeholder="Channel" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-60 shrink-0 ml-1 transition-transform duration-200">
                            <path :d="isChannelOpen ? 'm18 15-6-6-6 6' : 'm6 9 6 6 6-6'" />
                        </svg>
                    </SelectTrigger>
                    <SelectContent class="rounded-xl">
                        <SelectItem value="all" class="text-xs font-medium">Semua Channel</SelectItem>
                        <SelectItem value="offline" class="text-xs font-medium">Offline</SelectItem>
                        <SelectItem value="gofood" class="text-xs font-medium">GoFood</SelectItem>
                        <SelectItem value="grabfood" class="text-xs font-medium">GrabFood</SelectItem>
                        <SelectItem value="shopeefood" class="text-xs font-medium">ShopeeFood</SelectItem>
                    </SelectContent>
                </Select>
            </div>

                <!-- Search Bar Minimalis -->
                <div class="w-full sm:w-64 relative">
                    <Input
                        v-model="searchQuery"
                        placeholder="Cari menu..."
                        class="w-full text-xs h-8 pl-3 pr-8 bg-secondary/40 border-border/60 rounded-lg font-medium focus:ring-1 focus:ring-ring shadow-none"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-2.5 top-2 text-muted-foreground hover:text-foreground text-xs font-semibold"
                    >
                        ✕
                    </button>
                </div>
            </div>

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

            <div v-if="isLoading" class="mt-12 text-center text-muted-foreground text-xs font-medium">
                Memuat data menu...
            </div>

            <!-- Tabel Data Menu -->
            <div v-else-if="filteredAndSortedMenus.length > 0" class="w-full space-y-4">
                <div class="overflow-x-auto rounded-2xl border border-border/70 bg-card shadow-xs w-full">
                    <table class="w-full text-sm text-left min-w-[750px]">
                        <thead class="border-b border-border/40">
                            <tr>
                                <th class="px-4 py-3 w-10 align-middle">
                                    <input type="checkbox" :checked="selectedIds.length === filteredAndSortedMenus.length && filteredAndSortedMenus.length > 0" @change="toggleSelectAll" class="rounded-[4px] border-border/60 text-primary focus:ring-1 focus:ring-primary/30 focus:ring-offset-0 cursor-pointer transition-all" />
                                </th>
                                <th class="px-4 py-3 align-middle text-[11px] font-medium text-muted-foreground uppercase tracking-wider cursor-pointer hover:text-foreground transition-colors" @click="toggleSort('name')">
                                    Nama Menu <span v-if="sortBy === 'name'" class="ml-1">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="px-4 py-3 align-middle text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Kategori</th>
                                <th class="px-4 py-3 align-middle text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Overhead</th>
                                <th class="px-4 py-3 align-middle text-[11px] font-medium text-muted-foreground uppercase tracking-wider cursor-pointer hover:text-foreground transition-colors" @click="toggleSort('hpp')">
                                    HPP <span v-if="sortBy === 'hpp'" class="ml-1">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="px-4 py-3 align-middle text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Harga Jual & Margin</th>
                                <th class="px-4 py-3 align-middle text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 align-middle text-[11px] font-medium text-muted-foreground uppercase tracking-wider text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">
                            <tr v-for="menu in filteredAndSortedMenus" :key="menu.id" class="group hover:bg-muted/20 transition-colors duration-200">
                                <td class="px-4 py-3 align-middle">
                                    <input type="checkbox" v-model="selectedIds" :value="menu.id" class="rounded-[4px] border-border/60 text-primary focus:ring-1 focus:ring-primary/30 focus:ring-offset-0 cursor-pointer transition-all opacity-70 group-hover:opacity-100" />
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <span class="text-[13px] font-medium text-foreground tracking-tight">{{ menu.name }}</span>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="cat in menu.categories" :key="cat.id" class="px-1.5 py-0.5 rounded-[5px] bg-secondary/50 text-muted-foreground text-[10px] font-medium border border-border/40">
                                            {{ cat.name }}
                                        </span>
                                        <span v-if="!menu.categories || menu.categories.length === 0" class="text-muted-foreground/50 text-xs">-</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[12px] text-foreground tracking-tight">Rp {{ Number(menu.overhead_cost || 0).toLocaleString() }}</span>
                                        <span v-if="Number(menu.overhead_cost) !== masterOverheadTotal" class="text-[9px] font-medium bg-red-500/10 text-red-600 px-1.5 py-0.5 rounded-[4px]">
                                            Outdated
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <span class="text-[12px] text-muted-foreground tracking-tight">{{ currency(menu.hpp) }}</span>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <div v-if="Array.isArray(menu.prices) && menu.prices.length > 0" class="flex flex-col gap-1 w-full max-w-[200px]">
                                        <div v-for="price in (menu.prices as any[]).filter(p => selectedChannelFilter === 'all' || p.channel === selectedChannelFilter)" :key="price.id" class="flex items-center justify-between text-[11px]">
                                            <span class="text-muted-foreground capitalize">{{ price.channel }}</span>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-foreground">{{ currency(price.selling_price) }}</span>
                                                <span class="text-muted-foreground/60 w-7 text-right">{{ price.margin_percent }}%</span>
                                            </div>
                                        </div>
                                        <span v-if="(menu.prices as any[]).filter(p => selectedChannelFilter === 'all' || p.channel === selectedChannelFilter).length === 0" class="text-[11px] text-muted-foreground/60 italic">Kosong</span>
                                    </div>
                                    <span v-else class="text-[11px] text-muted-foreground/60 italic">Belum disetting</span>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <button
                                        type="button"
                                        @click="toggleMenuStatus(menu)"
                                        class="inline-flex items-center gap-1.5 px-2 py-1 rounded-[6px] border border-border/40 bg-secondary/30 hover:bg-secondary/70 transition-all cursor-pointer group/btn"
                                        :title="menu.is_active ? 'Klik untuk menonaktifkan' : 'Klik untuk mengaktifkan'"
                                    >
                                        <span
                                            :class="menu.is_active ? 'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.4)]' : 'bg-muted-foreground/40'"
                                            class="w-1.5 h-1.5 rounded-full transition-all"
                                        ></span>
                                        <span class="text-[11px] font-medium" :class="menu.is_active ? 'text-foreground' : 'text-muted-foreground'">
                                            {{ menu.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </button>
                                </td>

                                <td class="px-4 py-3 align-middle text-right">
                                    <div class="flex items-center justify-end gap-1 duration-200">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 p-0 text-muted-foreground hover:text-foreground rounded-[6px]"
                                            title="Edit"
                                            @click="openEdit(menu)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="7" height="7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>
                                            </svg>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 p-0 text-destructive/70 hover:text-destructive rounded-[6px]"
                                            title="Hapus"
                                            @click="handleDelete(menu)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
                                            </svg>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="meta && meta.total > 0" class="bg-secondary/30">
                            <tr>
                                <td colspan="8" class="px-5 py-3 border-t border-border/70">
                                    <div class="flex items-center justify-between">
                                        <div class="text-[11px] font-medium text-muted-foreground">
                                            Menampilkan <span class="font-bold text-foreground">{{ meta.from || 0 }}</span> - <span class="font-bold text-foreground">{{ meta.to || 0 }}</span> dari <span class="font-bold text-foreground">{{ meta.total }}</span> menu
                                        </div>

                                        <div class="flex items-center gap-2" v-if="meta.last_page > 1">
                                            <button
                                                @click="changePage(meta.current_page - 1)"
                                                :disabled="meta.current_page === 1"
                                                class="h-7 w-7 flex items-center justify-center rounded-lg border border-border/80 bg-background text-foreground hover:bg-secondary disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-2xs"
                                                aria-label="Previous Page"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                                            </button>

                                            <span class="text-[11px] font-bold text-muted-foreground px-1.5">
                                                {{ meta.current_page }} <span class="text-border mx-0.5">/</span> {{ meta.last_page }}
                                            </span>

                                            <button
                                                @click="changePage(meta.current_page + 1)"
                                                :disabled="meta.current_page === meta.last_page"
                                                class="h-7 w-7 flex items-center justify-center rounded-lg border border-border/80 bg-background text-foreground hover:bg-secondary disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-2xs"
                                                aria-label="Next Page"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>

            <div v-else class="flex flex-col items-center justify-center py-24 text-center border border-dashed rounded-3xl bg-card border-border/80 shadow-xs">
                <h3 class="text-sm font-bold text-foreground">Data tidak ditemukan</h3>
                <p class="mt-1 text-xs text-muted-foreground">Tidak ada menu produksi yang cocok dengan kriteria pencarian Anda.</p>
            </div>
        </div>
    </div>

    <MenuModal :show="showModal" :menu="activeMenu" :masterOverhead="masterOverheadTotal" @close="showModal = false" @saved="handleSaved" />
    <CategoryModal :show="showCategoryModal" @close="showCategoryModal = false" @updated="handleCategoryUpdated" />
    <CategorySortModal :show="showCategorySortModal" :category="selectedCategoryForSort" @close="showCategorySortModal = false" @updated="loadData" />
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
