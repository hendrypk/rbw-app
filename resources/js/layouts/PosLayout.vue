<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Toaster } from '@/components/ui/sonner';
import { ShoppingBag, ClipboardList, Receipt, ShoppingCart, LayoutGrid, Settings, MapPin, Wallet, Power, Store } from '@lucide/vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { NavItem } from '@/types';
import { dashboard } from '@/routes';
import PrinterSetup from '@/components/PrinterSetup.vue';
import pos from '@/routes/pos';
import axios from 'axios';
import ShiftModal from '@/pages/posPage/ShiftModal.vue';

const page = usePage();

const currentDateTime = ref('');
const activeOutletName = ref('Memuat Outlet...');
const activeOutletId = ref<string>('');
const outlets = ref<Array<{ id: string; name: string }>>([]);
const isOutletModalOpen = ref(false);

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

// Ambil daftar outlet yang tersedia untuk user
const fetchOutlets = async () => {
    try {
        const response = await axios.get('/api/outlets');
        if (response.data) {
            outlets.value = Array.isArray(response.data) ? response.data : (response.data.data || []);
        }
    } catch (error) {
        console.error("Gagal memuat daftar outlet", error);
    }
};

// Cek status shift kasir aktif spesifik berdasarkan outlet yang dipilih
const checkShiftStatus = async (outletId: string) => {
    if (!outletId) {
        isForcedShift.value = true;
        shiftModalMode.value = 'open';
        isShiftModalOpen.value = true;
        return;
    }

    try {
        const response = await axios.get('/api/pos/shifts/active', {
            headers: { 'X-Outlet-ID': outletId }
        });

        if (response.data.success) {
            if (!response.data.has_active_shift) {
                // Jika di outlet ini belum ada shift aktif, paksa buka shift baru untuk outlet ini
                activeShift.value = null;
                localStorage.removeItem('active_cashier_shift_id');
                
                shiftModalMode.value = 'open';
                isForcedShift.value = true;
                isShiftModalOpen.value = true;
            } else {
                // ⬅️ Jika di outlet tersebut sudah ada shift yang berjalan, otomatis resume shift milik outlet itu!
                activeShift.value = response.data.data;
                localStorage.setItem('active_cashier_shift_id', response.data.data.id);
                totalCashInDrawer.value = Number(response.data.data.starting_cash || 0);
                
                isShiftModalOpen.value = false;
                isForcedShift.value = false;
            }
        }
    } catch (error) {
        console.error("Gagal memeriksa status shift kasir", error);
        isForcedShift.value = true;
        shiftModalMode.value = 'open';
        isShiftModalOpen.value = true;
    }
};

// Handle ganti outlet dari modal/dropdown
const handleSelectOutlet = async (outletId: string, outletName: string) => {
    activeOutletId.value = outletId;
    activeOutletName.value = outletName;
    
    localStorage.setItem('active_outlet_id', outletId);
    localStorage.setItem('active_outlet_name', outletName);

    isOutletModalOpen.value = false;

    // Trigger event ganti outlet untuk modul lain jika diperlukan
    window.dispatchEvent(new Event('outlet-changed'));

    // Cek shift kasir di outlet baru ini (apakah sudah ada yang menggantung/aktif sebelumnya)
    await checkShiftStatus(outletId);
};

const handleShiftSuccess = (shiftData: any) => {
    if (shiftModalMode.value === 'open') {
        activeShift.value = shiftData;
        localStorage.setItem('active_cashier_shift_id', shiftData.id);
        totalCashInDrawer.value = Number(shiftData.starting_cash || 0);

        isShiftModalOpen.value = false;
        isForcedShift.value = false;
    } else {
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

const isPrinterModalOpen = ref(false);
let timeInterval: any;

onMounted(async () => {
    await fetchOutlets();

    const savedId = localStorage.getItem('active_outlet_id');
    const savedName = localStorage.getItem('active_outlet_name');

    if (savedId && savedName) {
        activeOutletId.value = savedId;
        activeOutletName.value = savedName;
        await checkShiftStatus(savedId);
    } else if (outlets.value.length > 0) {
        // Default ke outlet pertama jika belum pernah set
        await handleSelectOutlet(outlets.value[0].id, outlets.value[0].name);
    } else {
        isOutletModalOpen.value = true;
    }

    updateTime(); 
    timeInterval = setInterval(updateTime, 1000);
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
            <!-- Kiri: Brand & Tombol Ganti Outlet -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="p-1.5 sm:p-2 bg-primary text-primary-foreground rounded-xl shrink-0">
                    <ShoppingBag class="h-4 sm:h-5 w-4 sm:w-5" />
                </div>
                <div class="min-w-0 flex items-center gap-3">
                    <div>
                        <h1 class="font-bold text-xs sm:text-base leading-tight truncate">{{ title || 'Roti Bakar Wisuda' }}</h1>
                    </div>

                    <!-- Tombol Ganti Outlet Cepat di Header -->

                </div>
            </div>

            <!-- Kanan: Aksi (Invoice, Close Shift, Settings) -->
            <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                    <button 
                        @click="isOutletModalOpen = true"
                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold rounded-xl transition-colors cursor-pointer flex items-center gap-1.5 shrink-0 border border-slate-200 dark:border-zinc-700"
                    >
                        <Store class="h-3.5 w-3.5 text-primary" />
                            <span class="font-bold text-slate-700 dark:text-zinc-300 truncate">{{ activeOutletName }}</span>

                    </button>
                <Link 
                    :href="pos.transactions()" 
                    class="px-2.5 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-emerald-600 dark:text-emerald-400 hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors flex items-center gap-1.5 text-xs font-bold"
                    title="Riwayat Invoice"
                >
                    <Receipt class="h-4 w-4 shrink-0" />
                    <span>Invoice</span>
                </Link>

                <button 
                    v-if="activeShift"
                    @click="openCloseShiftModal"
                    class="p-2 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded-xl hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors cursor-pointer flex items-center justify-center"
                    title="Close Shift"
                >
                    <Power class="h-4 w-4 shrink-0" />
                </button>

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

        <div v-if="isOutletModalOpen" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-zinc-950 rounded-[2rem] max-w-md w-full p-6 border border-slate-200 dark:border-zinc-800 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center gap-3 border-b border-slate-100 dark:border-zinc-900 pb-4">
                    <div class="p-3 bg-primary/10 text-primary rounded-2xl">
                        <Store class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-zinc-50">Pilih Outlet Kasir</h3>
                        <p class="text-xs text-slate-400">Peralihan outlet akan otomatis memuat shift kasir yang aktif pada outlet tersebut.</p>
                    </div>
                </div>

                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    <button
                        v-for="outlet in outlets"
                        :key="outlet.id"
                        @click="handleSelectOutlet(outlet.id, outlet.name)"
                        :class="[
                            'w-full p-3.5 rounded-2xl border text-left transition-all flex items-center justify-between cursor-pointer font-bold text-xs',
                            activeOutletId === outlet.id 
                                ? 'bg-primary text-primary-foreground border-primary shadow-xs' 
                                : 'bg-slate-50 dark:bg-zinc-900 border-slate-200 dark:border-zinc-800 text-slate-700 dark:text-zinc-200 hover:border-primary/50'
                        ]"
                    >
                        <span class="truncate">{{ outlet.name }}</span>
                        <span v-if="activeOutletId === outlet.id" class="text-[10px] uppercase px-2 py-0.5 bg-white/20 rounded-md">Aktif</span>
                    </button>
                </div>

                <div class="pt-2 flex justify-end">
                    <button 
                        v-if="activeOutletId"
                        @click="isOutletModalOpen = false" 
                        class="w-full py-3 bg-slate-900 text-white dark:bg-white dark:text-slate-900 font-bold rounded-xl text-xs cursor-pointer hover:opacity-90"
                    >
                        Lanjutkan
                    </button>
                </div>
            </div>
        </div>

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