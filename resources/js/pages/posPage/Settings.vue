<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import PosLayout from '@/layouts/PosLayout.vue';
import { 
    LayoutGrid, 
    Utensils, 
    Truck, 
    Box, 
    ShoppingCart, 
    Calculator, 
    ClipboardList, 
    Receipt, 
    ArrowLeft,
    Settings as SettingsIcon, 
    Component
} from '@lucide/vue';
import { dashboard } from '@/routes';
import pos from '@/routes/pos';
import menus from '@/routes/menus';
import suppliers from '@/routes/suppliers';
import materials from '@/routes/materials';
import purchase from '@/routes/purchase';
import overhead from '@/routes/overhead';

// Import komponen modul menu
import MenuModule from '@/components/pos/MenuModule.vue';
import DashboardModule from '@/components/pos/DashboardModule.vue';

defineOptions({
    layout: PosLayout
});

const menuItems = [
    { key: 'dashboard', title: 'Dashboard Utama', component: DashboardModule, href: dashboard(), icon: LayoutGrid, desc: 'Panel kendali back office perusahaan.' },
    { key: 'orders', title: 'Pesanan (Unpaid)', href: pos.orders(), icon: ClipboardList, desc: 'Kelola pesanan tertunda kasir.' },
    { key: 'invoices', title: 'Riwayat Invoice', href: pos.invoices(), icon: Receipt, desc: 'Laporan transaksi yang sudah lunas.' },
    { key: 'menus', title: 'Menu', component: MenuModule, href: menus.index(), icon: Utensils, desc: 'Manajemen daftar menu makanan dan minuman.' },
    { key: 'suppliers', title: 'Supplier', href: suppliers.index(), icon: Truck, desc: 'Kelola data mitra pemasok bahan.' },
    { key: 'materials', title: 'Material', href: materials.index(), icon: Box, desc: 'Stok inventaris bahan baku.' },
    { key: 'purchase', title: 'Purchase', href: purchase.index(), icon: ShoppingCart, desc: 'Pembelian dan pengadaan barang.' },
    { key: 'overhead', title: 'Overhead', href: overhead.index(), icon: Calculator, desc: 'Biaya operasional dan pengeluaran.' },
];

const activeMenu = ref<string | null>(null);

const currentModule = computed(() => {
    return menuItems.find(m => m.key === activeMenu.value);
});

const selectMenu = (key: string) => {
    activeMenu.value = key;
};
</script>

<template>
    <Head title="Pengaturan & Menu POS" />

    <div class="flex h-full w-full overflow-hidden bg-slate-100 dark:bg-zinc-950">
        <!-- Sidebar Murni Khusus Halaman Settings -->
        <aside v-if="activeMenu" class="w-72 bg-white dark:bg-zinc-900 border-r border-slate-200 dark:border-zinc-800 flex flex-col shrink-0">

            <nav class="flex-1 overflow-y-auto p-4 space-y-1.5">
                <Link 
                    :href="pos.index()"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-primary bg-primary/5 hover:bg-primary/10 transition-colors mb-3"
                >
                    <ArrowLeft class="w-4 h-4" />
                    <span>Kembali ke Layar POS</span>
                </Link>


                <button 
                    v-for="item in menuItems" 
                    :key="item.key"
                    @click="selectMenu(item.key)"
                    :class="[
                        'w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all cursor-pointer text-left',
                        activeMenu === item.key 
                            ? 'bg-primary text-primary-foreground font-bold shadow-sm' 
                            : 'text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <component :is="item.icon" class="w-4 h-4" />
                    <span>{{ item.title }}</span>
                </button>
            </nav>
        </aside>

        <main class="flex-1 overflow-y-auto p-8">
            <div class="w-full mx-auto space-y-6">
                <template v-if="!activeMenu">
                    <div class="border-b border-slate-200 dark:border-zinc-800 pb-4">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white">Pengaturan & Pintasan Operasional POS</h1>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Pilih menu di samping atau melalui kartu pintasan di bawah untuk melihat rincian modul.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <button 
                            v-for="item in menuItems" 
                            :key="item.key"
                            @click="selectMenu(item.key)"
                            class="flex items-center gap-4 p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:border-primary shadow-xs transition-all group text-left cursor-pointer"
                        >
                            <div class="p-3 bg-primary/10 text-primary rounded-xl group-hover:scale-110 transition-transform">
                                <component :is="item.icon" class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-slate-900 dark:text-white">{{ item.title }}</h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ item.desc }}</p>
                            </div>
                        </button>
                    </div>
                </template>

                <!-- Jika salah satu menu dipilih -->
                <template v-else>

                    <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200 dark:border-zinc-800 p-6 shadow-xs">
                        <!-- Render Komponen Modul Terkait (Misal: MenuModule untuk 'menus') -->
                        <component :is="currentModule?.component" v-if="currentModule?.component" />
                        
                        <!-- Fallback jika modul lain belum memiliki komponen khusus -->
                        <div v-else class="text-center py-12 text-slate-400">
                            <p>Modul {{ currentModule?.title }} sedang dimuat...</p>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</template>