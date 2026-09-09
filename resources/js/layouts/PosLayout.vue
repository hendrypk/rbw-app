<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Toaster } from '@/components/ui/sonner';
import { ShoppingBag, ClipboardList, Receipt, ShoppingCart, LayoutGrid, Settings, MapPin, Wallet, Power } from '@lucide/vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { NavItem } from '@/types';
import { dashboard } from '@/routes';
import PrinterSetup from '@/components/PrinterSetup.vue';
import pos from '@/routes/pos';
import axios from 'axios';
import ShiftModal from '@/pages/posPage/ShiftModal.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'POS',
        href: pos.index(),
        icon: ShoppingCart,
    },
    {
        title: 'Pesanan (Unpaid)',
        href: pos.orders(),
        icon: ClipboardList,
    },
    {
        title: 'Riwayat Invoice',
        href: pos.invoices(),
        icon: Receipt,
    },
];

const currentDateTime = ref('');
const activeOutletName = ref('Belum Pilih Outlet');

// --- State Shift Kasir ---
const activeShift = ref<any>(null);
const isShiftModalOpen = ref(false);
const shiftModalMode = ref<'open' | 'close'>('open');
const isForcedShift = ref(false);
const totalCashInDrawer = ref(0);

const updateTime = () => {
    const now = new Date();
    
    const dateStr = now.toLocaleDateString('id-ID', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });

    const timeStr = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });

    currentDateTime.value = `${dateStr}  •  ${timeStr}`;
};

// Cek status shift kasir aktif di outlet saat ini
const checkShiftStatus = async () => {
    try {
        const activeOutletId = localStorage.getItem('active_outlet_id');
        
        if (!activeOutletId) {
            isForcedShift.value = true;
            shiftModalMode.value = 'open';
            isShiftModalOpen.value = true;
            return;
        }

        const response = await axios.get('/api/pos/shifts/active', {
            headers: { 'X-Outlet-ID': activeOutletId }
        });

        if (response.data.success) {
            if (!response.data.has_active_shift) {
                activeShift.value = null;
                localStorage.removeItem('active_cashier_shift_id');
                
                shiftModalMode.value = 'open';
                isForcedShift.value = true;
                isShiftModalOpen.value = true;
            } else {
                activeShift.value = response.data.data;
                localStorage.setItem('active_cashier_shift_id', response.data.data.id);
                
                isShiftModalOpen.value = false;
            }
        }
    } catch (error) {
        console.error("Gagal memeriksa status shift kasir", error);
        isForcedShift.value = true;
        shiftModalMode.value = 'open';
        isShiftModalOpen.value = true;
    }
};

const handleShiftSuccess = (shiftData: any) => {
    if (shiftModalMode.value === 'open') {
        activeShift.value = shiftData;
        localStorage.setItem('active_cashier_shift_id', shiftData.id);
        totalCashInDrawer.value = Number(shiftData.starting_cash || 0);

        isShiftModalOpen.value = false;
        isForcedShift.value = false;
    } else {
        // ⬅️ Tutup shift berhasil, reset state lalu paksa buka modal open shift baru
        activeShift.value = null;
        localStorage.removeItem('active_cashier_shift_id');
        totalCashInDrawer.value = 0;

        isShiftModalOpen.value = false;
        
        setTimeout(() => {
            shiftModalMode.value = 'open';
            isForcedShift.value = true;
            isShiftModalOpen.value = true;
        }, 150);
    }
};

const openCloseShiftModal = () => {
    if (!activeShift.value) return;
    shiftModalMode.value = 'close';
    isForcedShift.value = false;
    isShiftModalOpen.value = true;
};

const formatRupiah = (val: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};

const isPrinterModalOpen = ref(false);
let timeInterval: any;

onMounted(() => {
    const savedName = localStorage.getItem('active_outlet_name');
    if (savedName) {
        activeOutletName.value = savedName;
    }

    updateTime(); 
    timeInterval = setInterval(updateTime, 1000);
    checkShiftStatus();
});

onUnmounted(() => {
    clearInterval(timeInterval);
});

defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="h-screen w-screen flex flex-col bg-slate-100 dark:bg-zinc-950 text-slate-900 dark:text-zinc-50 overflow-hidden font-sans">
        <header class="h-14 sm:h-16 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800 flex items-center px-3 sm:px-6 justify-between shrink-0 shadow-xs z-10">
            <!-- Kiri: Brand & Outlet -->
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="p-1.5 sm:p-2 bg-primary text-primary-foreground rounded-xl shrink-0">
                    <ShoppingBag class="h-4 sm:h-5 w-4 sm:w-5" />
                </div>
                <div class="min-w-0">
                    <h1 class="font-bold text-xs sm:text-base leading-tight truncate">{{ title || 'Roti Bakar Wisuda' }}</h1>
                    <span class="text-[10px] sm:text-xs text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                        <MapPin class="h-3 w-3 text-emerald-600 shrink-0" />
                        <span class="font-bold text-slate-700 dark:text-zinc-300 truncate">{{ activeOutletName }}</span>
                    </span>
                </div>
            </div>

            <!-- Kanan: Aksi (Invoice berlabel, Close Shift icon Power Off merah) -->
            <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                <!-- Tombol Invoice (Pakai Label / Teks) -->
                <Link 
                    :href="pos.transactions()" 
                    class="px-2.5 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-emerald-600 dark:text-emerald-400 hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors flex items-center gap-1.5 text-xs font-bold"
                    title="Riwayat Invoice"
                >
                    <Receipt class="h-4 w-4 shrink-0" />
                    <span>Invoice</span>
                </Link>

                <!-- Tombol Close Shift (Ikon Power Off Merah) -->
                <button 
                    v-if="activeShift"
                    @click="openCloseShiftModal"
                    class="p-2 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded-xl hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors cursor-pointer flex items-center justify-center"
                    title="Close Shift"
                >
                    <Power class="h-4 w-4 shrink-0" />
                </button>

                <!-- Tombol Pengaturan -->
                <Link 
                    :href="pos.settings()" 
                    class="p-2 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors flex items-center justify-center"
                    title="Pengaturan"
                >
                    <Settings class="h-4 w-4 text-primary shrink-0" />
                </Link>
            </div>
        </header>

        <main class="flex-1 overflow-hidden relative">
            <slot />
        </main>

        <ShiftModal 
            :is-open="isShiftModalOpen"
            :mode="shiftModalMode"
            :active-shift="activeShift"
            :forced="isForcedShift"
            @close="isShiftModalOpen = false"
            @success="handleShiftSuccess"
        />

        <Toaster close-button position="top-center" />
        <PrinterSetup 
            :is-open="isPrinterModalOpen" 
            @close="isPrinterModalOpen = false" 
        />
    </div>
</template>