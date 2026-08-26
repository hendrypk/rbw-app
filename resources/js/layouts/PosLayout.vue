<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Toaster } from '@/components/ui/sonner';
import { ShoppingBag, ClipboardList, Receipt, ShoppingCart, LayoutGrid, Settings } from '@lucide/vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { NavItem } from '@/types';
import webPos from '@/routes/web-pos';
import { dashboard } from '@/routes';
import PrinterSetup from '@/components/PrinterSetup.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'POS',
        href: webPos.index(),
        icon: ShoppingCart,
    },
    {
        title: 'Pesanan (Unpaid)',
        href: webPos.orders(),
        icon: ClipboardList,
    },
    {
        title: 'Riwayat Invoice',
        href: webPos.invoices(),
        icon: Receipt,
    },
];

const currentDateTime = ref('');

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

const isPrinterModalOpen = ref(false);

let timeInterval: any;
onMounted(() => {
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
        
        <header class="h-16 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800 flex items-center px-6 justify-between shrink-0 shadow-sm z-10">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-primary text-primary-foreground rounded-lg">
                    <ShoppingBag class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-none">{{ title || 'Roti Bakar Wisuda' }}</h1>
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1 mt-0.5">
                        <span class="h-1.5 w-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                        Kasir Aktif
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Link 
                    :href="webPos.orders()" 
                    class="inline-flex items-center gap-2 text-sm font-bold px-3 py-2 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/20 rounded-lg transition-colors"
                >
                    <ClipboardList class="h-4 w-4" />
                    <span class="hidden md:inline">Pesanan (Unpaid)</span>
                </Link>

                <Link 
                    :href="webPos.transactions()" 
                    class="inline-flex items-center gap-2 text-sm font-bold px-3 py-2 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 rounded-lg transition-colors"
                >
                    <Receipt class="h-4 w-4" />
                    <span class="hidden md:inline">Riwayat Invoice</span>
                </Link>
                <button 
                    @click="isPrinterModalOpen = true"
                    class="relative p-2 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors shadow-2xs flex items-center gap-1.5 px-3 text-xs font-bold cursor-pointer"
                    title="Pengaturan Printer"
                >
                    <Settings class="h-4 w-4 text-primary" />
                    <span class="hidden sm:inline">Printer</span>
                </button>

                <div class="h-6 w-[1px] bg-slate-200 dark:bg-zinc-800 hidden sm:block"></div>
               
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-medium text-slate-500 dark:text-zinc-400">
                        {{ currentDateTime }}
                    </div>
                </div>

            </div>
        </header>

        <main class="flex-1 overflow-hidden relative">
            <slot />
        </main>

        <Toaster close-button position="top-center" />
        <PrinterSetup 
        :is-open="isPrinterModalOpen" 
        @close="isPrinterModalOpen = false" 
    />
    </div>
</template>