<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import AppearanceTabs from './AppearanceTabs.vue';
import { Store, Wallet, Power } from '@lucide/vue';

import Select from '@/components/ui/select/Select.vue';
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue';
import SelectValue from '@/components/ui/select/SelectValue.vue';
import SelectContent from '@/components/ui/select/SelectContent.vue';
import SelectItem from '@/components/ui/select/SelectItem.vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const outlets = ref<Array<{ id: string; name: string }>>([]);
const selectedOutletId = ref<string>('all');
const activeShift = ref<any>(null);

const fetchOutlets = async () => {
    try {
        const response = await axios.get('/api/outlets');
        if (response.data.success || response.data.data) {
            outlets.value = response.data.data || response.data;
        }
    } catch (error) {
        console.error("Gagal mengambil daftar outlet", error);
    }
};

// Cek status shift aktif untuk outlet yang dipilih
const checkActiveShift = async (outletId: string) => {
    if (!outletId || outletId === 'all') {
        activeShift.value = null;
        return;
    }

    try {
        const response = await axios.get('/api/pos/shifts/active', {
            headers: { 'X-Outlet-ID': outletId }
        });
        if (response.data.success && response.data.has_active_shift) {
            activeShift.value = response.data.data;
            localStorage.setItem('active_cashier_shift_id', response.data.data.id);
        } else {
            activeShift.value = null;
            localStorage.removeItem('active_cashier_shift_id');
        }
    } catch (error) {
        activeShift.value = null;
    }
};

// Handle perubahan dropdown outlet
const handleOutletChange = async (newId: any) => {
    if (!newId) return;
    const outletIdStr = String(newId);

    if (outletIdStr === 'all') {
        localStorage.removeItem('active_outlet_id');
        localStorage.removeItem('active_outlet_name');
        activeShift.value = null;
    } else {
        const selected = outlets.value.find(o => o.id === outletIdStr);
        if (selected) {
            localStorage.setItem('active_outlet_id', selected.id);
            localStorage.setItem('active_outlet_name', selected.name);
            await checkActiveShift(selected.id);
        }
    }
    
    window.dispatchEvent(new Event('outlet-changed'));
    window.dispatchEvent(new Event('shift-status-changed'));
};

const syncFromStorage = async () => {
    const savedId = localStorage.getItem('active_outlet_id');
    selectedOutletId.value = savedId || 'all';
    if (savedId) {
        await checkActiveShift(savedId);
    }
};

// Trigger buka/tutup shift modal di layout/halaman utama
const triggerShiftModal = (mode: 'open' | 'close') => {
    window.dispatchEvent(new CustomEvent('request-shift-modal', { detail: { mode } }));
};

onMounted(async () => {
    await fetchOutlets();
    await syncFromStorage();
    window.addEventListener('outlet-changed', syncFromStorage);
    window.addEventListener('shift-status-changed', syncFromStorage);
});

onUnmounted(() => {
    window.removeEventListener('outlet-changed', syncFromStorage);
    window.removeEventListener('shift-status-changed', syncFromStorage);
});
</script>

<template>
<header
    class="flex h-16 shrink-0 items-center justify-between border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 bg-white dark:bg-zinc-950"
>
    <!-- Kiri: Sidebar Toggle & Breadcrumbs -->
    <div class="flex items-center gap-2">
        <SidebarTrigger class="-ml-1" />
        <template v-if="breadcrumbs && breadcrumbs.length > 0">
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </template>
    </div>

    <!-- Kanan: Active Cashier Badge, Switcher & Theme -->
    <div class="flex items-center gap-3">
        
        <!-- Indikator Active Cashier / Shift Status -->
        <div v-if="selectedOutletId !== 'all'" class="flex items-center gap-1.5">
            <button 
                @click="activeShift ? triggerShiftModal('close') : triggerShiftModal('open')"
                :class="[
                    'px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all shadow-2xs cursor-pointer border',
                    activeShift 
                        ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 hover:bg-emerald-100' 
                        : 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20 hover:bg-rose-100'
                ]"
                :title="activeShift ? 'Shift Aktif (Klik untuk Tutup Kasir)' : 'Kasir Tutup (Klik untuk Buka Kasir)'"
            >
                <span :class="['w-2 h-2 rounded-full animate-pulse', activeShift ? 'bg-emerald-500' : 'bg-rose-500']"></span>
                <Wallet class="w-3.5 h-3.5 shrink-0" />
                <span class="hidden sm:inline">{{ activeShift ? 'Kasir Buka' : 'Kasir Tutup' }}</span>
            </button>
        </div>

        <!-- Outlet Switcher (Dropdown) -->
        <Select v-model="selectedOutletId" @update:modelValue="handleOutletChange">
            <SelectTrigger class="h-9 rounded-xl border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 px-3 text-xs font-bold hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors w-[180px] shadow-sm">
                <div class="flex items-center gap-2 truncate text-slate-700 dark:text-zinc-200">
                    <Store class="h-3.5 w-3.5 text-primary shrink-0" />
                    <SelectValue placeholder="Pilih Outlet" class="truncate" />
                </div>
            </SelectTrigger>
            
            <SelectContent class="rounded-xl border-slate-200 dark:border-zinc-800 shadow-xl">
                <SelectItem value="all" class="text-xs font-bold cursor-pointer">
                    Semua Outlet (Pusat)
                </SelectItem>
                <SelectItem 
                    v-for="outlet in outlets" 
                    :key="outlet.id" 
                    :value="outlet.id" 
                    class="text-xs font-semibold cursor-pointer"
                >
                    {{ outlet.name }}
                </SelectItem>
            </SelectContent>
        </Select>

        <!-- Theme Toggle -->
        <AppearanceTabs />
    </div>
</header>
</template>