<script setup lang="ts">
/**
 * POS Index View Component
 * Handles product browsing, cart manipulation, and payment interactions.
 */
import { ref, onMounted, computed, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import PosLayout from '@/layouts/PosLayout.vue';
import { Plus, Minus, Tag, Percent, Receipt, FileText, X } from '@lucide/vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import QrcodeVue from 'qrcode.vue';
import { usePosCheckout } from '@/composables/usePosCheckout'; // Adjust path if needed

defineOptions({
    layout: PosLayout
});

// Import centralized business logic composable
const {
    isPaymentModalOpen, isQrisModalOpen, isGeneratingQris,
    customerName, orderNote, discountInput, transactionFee, paymentMethod, amountPaidInput,
    cart, qrisData, qrisPaymentStatus,
    cartSubtotal, finalTotal,
    openPaymentModal, closePaymentModal, closeQrisModal: baseCloseQrisModal,
    submitCheckout, handleQrisCheckout: baseHandleQrisCheckout
} = usePosCheckout();

// ==========================================
// COUNTDOWN TIMER STATE & LOGIC
// ==========================================
const remainingSeconds = ref<number>(900); // 900 seconds = 15 minutes
let timerInterval: any = null;

// Formats remaining seconds into MM:SS format
const formattedCountdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// Checks if the QRIS validity period has expired
const isExpired = computed(() => remainingSeconds.value <= 0);

// Starts the 15-minute countdown timer
const startCountdown = () => {
    remainingSeconds.value = 900;
    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        if (remainingSeconds.value > 0) {
            remainingSeconds.value--;
        } else {
            qrisPaymentStatus.value = 'FAILED';
            if (timerInterval) clearInterval(timerInterval);
        }
    }, 1000);
};

// Extended handler to trigger timer alongside Qris Checkout
const handleQrisCheckout = async () => {
    await baseHandleQrisCheckout();
    if (isQrisModalOpen.value) {
        startCountdown();
    }
};

// Extended close modal to clear the timer interval safely
const closeQrisModal = () => {
    if (timerInterval) clearInterval(timerInterval);
    baseCloseQrisModal();
};

// Clear timer on component unmount
onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// ==========================================
// CATALOG & FILTER STATES
// ==========================================
const menus = ref<any[]>([]);
const categories = ref<any[]>([]);
const isLoading = ref<boolean>(true);
const searchQuery = ref<string>('');
const selectedCategory = ref<string>('all');

/**
 * Fetch menus and extract categories on mount
 */
const fetchData = async () => {
    try {
        isLoading.value = true;
        const menuResponse = await axios.get('/api/menus');
        menus.value = menuResponse.data.data || menuResponse.data;

        const uniqueCategories = new Map();
        menus.value.forEach(menu => {
            if (menu.category) {
                uniqueCategories.set(menu.category.id, menu.category.name);
            }
        });
        
        categories.value = Array.from(uniqueCategories, ([id, name]) => ({ id, name }));
    } catch (error) {
        console.error('Failed to fetch POS menu data:', error);
        toast.error('Gagal mengambil data dari server');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchData();
});

// ==========================================
// FILTER COMPUTED PROPERTIES
// ==========================================
const filteredMenus = computed(() => {
    return menus.value.filter(menu => {
        const matchesSearch = menu.name.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCategory = selectedCategory.value === 'all' || menu.category_id === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});

const getOfflinePriceObject = (menu: any) => {
    if (!menu.prices) return null;
    return menu.prices.find((p: any) => p.channel === 'offline' && p.is_active);
};

// ==========================================
// CART MANAGEMENT LOGIC
// ==========================================
const addToCart = (menu: any) => {
    const existingItem = cart.value.find(item => item.menu_id === menu.id);
    const offlinePriceData = getOfflinePriceObject(menu);
    const activePrice = offlinePriceData ? Number(offlinePriceData.selling_price) : 0;

    if (existingItem) {
        existingItem.quantity += 1;
        existingItem.subtotal = existingItem.quantity * existingItem.price;
    } else {
        cart.value.push({
            menu_id: menu.id,
            name: menu.name,
            quantity: 1,
            price: activePrice,
            subtotal: activePrice,
            image_path: menu.image_path
        });
    }
    toast.success(`${menu.name} ditambahkan`);
};

const updateQuantity = (menuId: string, amount: number) => {
    const item = cart.value.find(item => item.menu_id === menuId);
    if (!item) return;

    item.quantity += amount;
    if (item.quantity <= 0) {
        removeFromCart(menuId);
    } else {
        item.subtotal = item.quantity * item.price;
    }
};

const removeFromCart = (menuId: string) => {
    cart.value = cart.value.filter(item => item.menu_id !== menuId);
};

// Helper to generate initials for avatar placeholder
const getInitials = (name: string) => {
    return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
};
</script>

<template>
    <Head title="Aplikasi Kasir (POS)" />

    <div class="h-full w-full flex flex-col md:flex-row overflow-hidden">
        
        <!-- ========================================================= -->
        <!-- LEFT PANEL: CATALOG & CATEGORY FILTER                     -->
        <!-- ========================================================= -->
        <div class="flex-1 h-full overflow-y-auto p-4 space-y-4 custom-scrollbar">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="w-full sm:w-72">
                    <input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Cari menu makanan / minuman..." 
                        class="w-full px-3 py-1.5 text-sm bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-lg focus:outline-none focus:border-primary text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-zinc-500"
                    />
                </div>
            </div>

            <!-- Categories Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none border-b border-slate-100 dark:border-zinc-800/60">
                <button 
                    @click="selectedCategory = 'all'"
                    :class="[
                        'px-4 py-1.5 text-xs font-semibold rounded-full whitespace-nowrap border transition-all',
                        selectedCategory === 'all' 
                            ? 'bg-primary border-primary text-primary-foreground shadow-sm' 
                            : 'bg-white border-slate-200 text-slate-600 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:border-zinc-700 hover:border-slate-300'
                    ]"
                >
                    Semua Kategori
                </button>
                <button 
                    v-for="cat in categories" 
                    :key="cat.id"
                    @click="selectedCategory = cat.id"
                    :class="[
                        'px-4 py-1.5 text-xs font-semibold rounded-full whitespace-nowrap border transition-all',
                        selectedCategory === cat.id 
                            ? 'bg-primary border-primary text-primary-foreground shadow-sm' 
                            : 'bg-white border-slate-200 text-slate-600 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:border-zinc-700 hover:border-slate-300'
                    ]"
                >
                    {{ cat.name }}
                </button>
            </div>

            <!-- Skeleton Loading Loader -->
            <div v-if="isLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div v-for="i in 10" :key="i" class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-slate-200 dark:border-zinc-800 animate-pulse h-40"></div>
            </div>

            <div v-else-if="filteredMenus.length === 0" class="text-center py-12 text-sm text-slate-400 dark:text-zinc-500">
                Menu tidak ditemukan.
            </div>

            <!-- Menu Grid Layout -->
            <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div 
                    v-for="menu in filteredMenus" 
                    :key="menu.id" 
                    @click="addToCart(menu)"
                    class="bg-white dark:bg-zinc-900 p-3.5 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm cursor-pointer hover:border-primary dark:hover:border-primary active:scale-[0.97] transition-all flex flex-col justify-between aspect-3/4 group"
                >
                    <div class="w-full aspect-square bg-slate-100 dark:bg-zinc-800 rounded-xl overflow-hidden relative shrink-0 shadow-inner">
                        <img 
                            v-if="menu.image_path" 
                            :src="menu.image_path.startsWith('http') ? menu.image_path : `/storage/${menu.image_path}`" 
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                            alt="Menu"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center bg-primary/5 text-primary dark:bg-primary/10 font-black text-xl tracking-wider">
                            {{ getInitials(menu.name) }}
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col justify-between pt-3">
                        <h3 class="font-black text-base text-slate-900 dark:text-zinc-50 line-clamp-2 leading-tight tracking-tight group-hover:text-primary transition-colors">
                            {{ menu.name }}
                        </h3>
                        
                        <div class="flex items-end justify-between mt-2 pt-1 border-t border-slate-50 dark:border-zinc-800/50">
                            <span class="text-xs text-slate-400 dark:text-zinc-500 font-semibold truncate max-w-20">
                                {{ menu.category?.name || 'Umum' }}
                            </span>
                            <span class="font-black text-base text-primary whitespace-nowrap">
                                Rp {{ Number(getOfflinePriceObject(menu)?.selling_price || 0).toLocaleString('id-ID') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- RIGHT PANEL: CART & CHECKOUT CONTAINER                    -->
        <!-- ========================================================= -->
        <div class="w-full md:w-115 xl:w-130 h-full bg-white dark:bg-zinc-900 border-t md:border-t-0 md:border-l border-slate-200 dark:border-zinc-800 flex flex-col shrink-0 shadow-xl md:shadow-none">
            
            <!-- Cart Header -->
            <div class="p-4 border-b border-slate-200 dark:border-zinc-800 bg-slate-50/80 dark:bg-zinc-900/40">
                <h3 class="font-bold text-base text-slate-900 dark:text-zinc-50">Detail Transaksi</h3>
                <input 
                    v-model="customerName"
                    type="text" 
                    placeholder="Nama Pelanggan (Opsional)" 
                    class="w-full mt-3 px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none focus:border-primary text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-zinc-500"
                />
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar">
                <div v-if="cart.length === 0" class="text-center py-16 text-sm text-slate-400 dark:text-zinc-500">
                    Keranjang kosong. Klik menu di samping untuk menambahkan.
                </div>
                
                <div v-for="item in cart" :key="item.menu_id" class="flex flex-col gap-2 p-3.5 bg-slate-50 dark:bg-zinc-800/40 rounded-xl border border-slate-100 dark:border-zinc-800/80">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-extrabold text-base text-slate-900 dark:text-zinc-100 leading-tight truncate">
                                {{ item.name }}
                            </h4>
                            <span class="text-sm font-medium text-slate-500 dark:text-zinc-400 mt-1 block">
                                @Rp {{ Number(item.price).toLocaleString('id-ID') }}
                            </span>
                        </div>

                        <div class="text-right font-black text-base text-slate-900 dark:text-zinc-50 shrink-0 pl-2">
                            Rp {{ Number(item.subtotal).toLocaleString('id-ID') }}
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-200/60 dark:border-zinc-700/50">
                        <span class="text-xs text-slate-400 dark:text-zinc-500 font-medium">Atur Jumlah:</span>
                        
                        <div class="flex items-center gap-3 shrink-0">
                            <button 
                                @click="updateQuantity(item.menu_id, -1)" 
                                class="p-1.5 bg-white border border-slate-200 rounded-lg text-slate-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400 transition-colors shadow-sm"
                            >
                                <Minus class="h-4 w-4" />
                            </button>
                            <span class="font-black text-base w-6 text-center text-slate-900 dark:text-zinc-100">
                                {{ item.quantity }}
                            </span>
                            <button 
                                @click="updateQuantity(item.menu_id, 1)" 
                                class="p-1.5 bg-white border border-slate-200 rounded-lg text-slate-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors shadow-sm"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes, Discount, & Fee Inputs -->
            <div class="p-4 border-t border-slate-100 dark:border-zinc-800/80 space-y-3 bg-slate-50/50 dark:bg-zinc-900/20">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600 dark:text-zinc-400 flex items-center gap-1.5">
                        <FileText class="h-3.5 w-3.5 text-slate-400" /> Catatan Masakan
                    </label>
                    <textarea 
                        v-model="orderNote"
                        rows="2" 
                        placeholder="Contoh: Pedas manis, es dipisah, no bawang..." 
                        class="w-full text-sm p-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none resize-none focus:border-primary text-slate-800 dark:text-zinc-200 placeholder:text-slate-400 dark:placeholder:text-zinc-500"
                    ></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-600 dark:text-zinc-400 flex items-center gap-1.5">
                            <Percent class="h-3.5 w-3.5 text-red-500" /> Diskon (Rp)
                        </label>
                        <input 
                            v-model.number="discountInput"
                            type="number" 
                            placeholder="0"
                            class="w-full text-sm px-3 py-2 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none focus:border-primary text-slate-900 dark:text-zinc-50 font-semibold"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-600 dark:text-zinc-400 flex items-center gap-1.5">
                            <Receipt class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-500" /> Biaya (Rp)
                        </label>
                        <input 
                            v-model.number="transactionFee"
                            type="number" 
                            placeholder="0"
                            class="w-full text-sm px-3 py-2 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none focus:border-primary text-slate-900 dark:text-zinc-50 font-semibold"
                        />
                    </div>
                </div>
            </div>

            <!-- Cart Footer Summary & Checkout Triggers -->
            <div class="p-5 border-t border-slate-200/60 dark:border-zinc-800 space-y-4 bg-white dark:bg-zinc-900">
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-slate-500 dark:text-zinc-400">
                        <span>Subtotal</span>
                        <span class="font-medium text-slate-700 dark:text-zinc-300">Rp {{ cartSubtotal.toLocaleString('id-ID') }}</span>
                    </div>
                    <div v-if="discountInput > 0" class="flex justify-between text-red-500 dark:text-red-400">
                        <span>Diskon</span>
                        <span>-Rp {{ discountInput.toLocaleString('id-ID') }}</span>
                    </div>
                    <div v-if="transactionFee > 0" class="flex justify-between text-slate-500 dark:text-zinc-400">
                        <span>Biaya Tambahan</span>
                        <span class="font-medium text-slate-700 dark:text-zinc-300">+Rp {{ transactionFee.toLocaleString('id-ID') }}</span>
                    </div>
                    
                    <div class="border-t border-slate-100 dark:border-zinc-800/80 pt-3 flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-800 dark:text-zinc-200">Total Akhir</span>
                        <span class="text-xl font-bold text-slate-900 dark:text-zinc-50 tracking-tight">
                            Rp {{ finalTotal.toLocaleString('id-ID') }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button 
                        @click="submitCheckout('save')"
                        :disabled="cart.length === 0"
                        class="bg-white hover:bg-slate-50 text-slate-700 dark:bg-zinc-900 dark:text-zinc-300 dark:border-zinc-700 dark:hover:bg-zinc-800/80 py-3 rounded-xl text-xs font-bold border border-slate-200 shadow-sm transition-all disabled:opacity-40"
                    >
                        Simpan (Unpaid)
                    </button>
                    
                    <button 
                        @click="openPaymentModal"
                        :disabled="cart.length === 0"
                        class="bg-slate-900 hover:bg-slate-800 text-white dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white py-3 rounded-xl text-xs font-black shadow-sm transition-all disabled:opacity-40"
                    >
                        Proses Pembayaran
                    </button>
                </div>
            </div>
            
            <!-- ========================================================= -->
            <!-- PAYMENT METHOD SELECTION MODAL                            -->
            <!-- ========================================================= -->
            <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm transition-opacity animate-fade-in">
                <div class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col">
                    
                    <!-- Modal Header -->
                    <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/40 shrink-0">
                        <div>
                            <h4 class="font-black text-base text-slate-900 dark:text-zinc-100 tracking-tight">Penyelesaian Transaksi</h4>
                            <p class="text-xs text-slate-400 font-medium mt-0.5">Pilih metode pembayaran yang digunakan</p>
                        </div>
                        <button @click="closePaymentModal" class="p-1.5 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar">
                        
                        <!-- Box Total Tagihan -->
                        <div class="p-4 bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 rounded-2xl flex items-center justify-between shadow-sm">
                            <span class="text-xs font-semibold opacity-80 uppercase tracking-wider">Total Tagihan Bersih:</span>
                            <span class="text-xl font-black tracking-tight font-mono">Rp {{ finalTotal.toLocaleString('id-ID') }}</span>
                        </div>

                        <!-- Pilihan Metode Pembayaran (Hanya Cash & QRIS dengan Grid 2 Kolom) -->
                        <div class="space-y-2">
                            <span class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider block">Metode Pembayaran</span>
                            <div class="grid grid-cols-2 gap-3">
                                <button 
                                    @click="paymentMethod = 'cash'"
                                    type="button"
                                    :class="[
                                        'py-3.5 px-4 text-xs font-black rounded-2xl border uppercase transition-all flex items-center justify-center gap-2', 
                                        paymentMethod === 'cash' 
                                            ? 'bg-slate-900 border-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-md scale-[1.02]' 
                                            : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400 hover:border-slate-300'
                                    ]"
                                >
                                    💵 Tunai (Cash)
                                </button>
                                <button 
                                    @click="paymentMethod = 'qris'"
                                    type="button"
                                    :class="[
                                        'py-3.5 px-4 text-xs font-black rounded-2xl border uppercase transition-all flex items-center justify-center gap-2', 
                                        paymentMethod === 'qris' 
                                            ? 'bg-blue-600 border-blue-600 text-white shadow-md scale-[1.02]' 
                                            : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400 hover:border-slate-300'
                                    ]"
                                >
                                    📱 QRIS Dinamis
                                </button>
                            </div>
                        </div>

                        <!-- Box Kalkulator Tunai (Hanya muncul jika pilih Cash) -->
                        <div v-if="paymentMethod === 'cash'" class="p-4 bg-slate-50 dark:bg-zinc-800/50 rounded-2xl space-y-3 border border-slate-200/60 dark:border-zinc-700 animate-fade-in">
                            <div class="grid grid-cols-4 gap-2 text-xs">
                                <button @click="amountPaidInput = finalTotal" type="button" class="py-2 bg-white dark:bg-zinc-900 hover:border-slate-400 border border-slate-200/80 dark:border-zinc-700 rounded-xl font-bold text-slate-700 dark:text-zinc-200 shadow-2xs">Uang Pas</button>
                                <button @click="amountPaidInput = 25000" type="button" class="py-2 bg-white dark:bg-zinc-900 hover:border-slate-400 border border-slate-200/80 dark:border-zinc-700 rounded-xl font-bold text-slate-700 dark:text-zinc-200 shadow-2xs">25k</button>
                                <button @click="amountPaidInput = 50000" type="button" class="py-2 bg-white dark:bg-zinc-900 hover:border-slate-400 border border-slate-200/80 dark:border-zinc-700 rounded-xl font-bold text-slate-700 dark:text-zinc-200 shadow-2xs">50k</button>
                                <button @click="amountPaidInput = 100000" type="button" class="py-2 bg-white dark:bg-zinc-900 hover:border-slate-400 border border-slate-200/80 dark:border-zinc-700 rounded-xl font-bold text-slate-700 dark:text-zinc-200 shadow-2xs">100k</button>
                            </div>
                            
                            <div class="flex items-center justify-between gap-3 text-xs pt-1">
                                <span class="font-semibold text-slate-500 dark:text-zinc-400">Nominal Diterima:</span>
                                <input v-model.number="amountPaidInput" type="number" class="w-40 text-right font-black px-3 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl focus:outline-none text-slate-900 dark:text-white font-mono text-sm" />
                            </div>
                            
                            <div v-if="amountPaidInput >= finalTotal" class="flex justify-between items-center text-xs text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-950/30 p-3 rounded-xl border border-emerald-100 dark:border-emerald-900/50">
                                <span>Kembalian:</span>
                                <span class="font-mono font-black text-sm">Rp {{ (amountPaidInput - finalTotal).toLocaleString('id-ID') }}</span>
                            </div>
                        </div>

                        <!-- Catatan & Diskon Tambahan (Opsional/Ringkas) -->
                        <div class="space-y-3 pt-2 border-t border-slate-100 dark:border-zinc-800">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nama Pelanggan</span>
                                    <input v-model="customerName" type="text" placeholder="Umum" class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white" />
                                </div>
                                <div class="space-y-1">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Diskon (Rp)</span>
                                    <input v-model.number="discountInput" type="number" placeholder="0" class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-red-500 font-bold" />
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Modal Footer Actions -->
                    <div class="p-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 grid grid-cols-2 gap-3 shrink-0">
                        <button @click="closePaymentModal" type="button" class="py-3 bg-white border border-slate-200 text-slate-700 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300 font-bold rounded-2xl text-xs transition-all hover:bg-slate-50">
                            Kembali
                        </button>
                        
                        <!-- Tombol QRIS dengan Proteksi Anti Double-Click -->
                        <button 
                            v-if="paymentMethod === 'qris'"
                            @click="handleQrisCheckout" 
                            :disabled="isGeneratingQris"
                            type="button"
                            class="py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-2xl text-xs shadow-md transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="isGeneratingQris" class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                            {{ isGeneratingQris ? 'Memproses QR...' : 'Generate QRIS' }}
                        </button>

                        <!-- Tombol Tunai / Cash -->
                        <button 
                            v-else
                            @click="submitCheckout('pay')" 
                            type="button"
                            class="py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-2xl text-xs shadow-md transition-all active:scale-95 flex items-center justify-center gap-2"
                        >
                            ✓ Eksekusi & Cetak
                        </button>
                    </div>

                </div>
            </div>

            <!-- ========================================================= -->
            <!-- DOKU QRIS DYNAMIC DISPLAY MODAL                           -->
            <!-- ========================================================= -->
            <div v-if="isQrisModalOpen" class="fixed inset-0 z-100 flex items-center justify-center p-4 sm:p-8 bg-slate-900/90 dark:bg-black/90 backdrop-blur-md transition-all duration-300">
                <div class="bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-4xl border border-slate-200/60 dark:border-zinc-800 shadow-2xl overflow-hidden flex flex-col relative">
                    
                    <!-- Modal Header -->
                    <div class="bg-slate-50 dark:bg-zinc-800/50 p-5 sm:px-8 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                        <h4 class="font-extrabold text-base sm:text-lg text-slate-800 dark:text-zinc-100 uppercase tracking-widest">Pembayaran QRIS</h4>
                        <button @click="closeQrisModal" class="p-2 bg-white dark:bg-zinc-700 rounded-full text-slate-400 hover:text-slate-700 dark:hover:text-zinc-100 border border-slate-200 dark:border-zinc-600 shadow-sm transition-all hover:scale-105">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- STATE 1: PENDING (QR CODE DISPLAYED) -->
                    <div v-if="qrisPaymentStatus === 'PENDING'" class="p-6 sm:p-10 flex flex-col items-center">
                        
                        <div class="w-full bg-slate-50 dark:bg-zinc-800/50 rounded-3xl p-6 mb-8 border border-slate-100 dark:border-zinc-700/50 space-y-4 shadow-sm">
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-2 pb-5 border-b border-slate-200 dark:border-zinc-700 border-dashed">
                                <span class="text-sm sm:text-base font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Total Tagihan</span>
                                <span class="text-lg sm:text-xl font-black text-slate-900 dark:text-zinc-50 tracking-tight text-center">
                                    Rp {{ finalTotal.toLocaleString('id-ID') }}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <div class="flex justify-between sm:justify-start items-center gap-3">
                                    <span class="text-sm font-medium text-slate-500 dark:text-zinc-400">Invoice:</span>
                                    <span class="font-bold text-sm text-slate-700 dark:text-zinc-200 font-mono bg-slate-200 dark:bg-zinc-700 px-2.5 py-1 rounded-md">{{ qrisData.invoiceNo }}</span>
                                </div>
                                <div class="flex justify-between sm:justify-end items-center gap-3">
                                    <span class="text-sm font-medium text-slate-500 dark:text-zinc-400">Pelanggan:</span>
                                    <span class="font-bold text-sm sm:text-base text-slate-800 dark:text-zinc-100">{{ customerName || 'Pelanggan Umum' }}</span>
                                </div>
                            </div>

                            <!-- COUNTDOWN TIMER DISPLAY -->
                            <div class="pt-3 border-t border-slate-200/60 dark:border-zinc-700 flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400">Batas Waktu Bayar:</span>
                                <span class="text-sm font-black font-mono px-3 py-1 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-lg border border-red-200 dark:border-red-900/50">
                                    ⏱️ {{ formattedCountdown }}
                                </span>
                            </div>
                        </div>

                        <!-- QR Code Container -->
                        <div class="p-5 sm:p-6 bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.1)] border-2 border-slate-100 dark:border-zinc-700 mb-8 relative">
                            <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-blue-600 text-white px-5 py-1.5 rounded-full text-xs sm:text-sm font-black tracking-widest uppercase shadow-lg border-2 border-white dark:border-zinc-900 whitespace-nowrap">
                                Scan Untuk Bayar
                            </div>
                            <qrcode-vue 
                                :value="qrisData.qrContent" 
                                :size="360"
                                level="H"
                                foreground="#0f172a" 
                                class="w-full h-auto max-w-90 aspect-square object-contain"
                            />
                        </div>
                        
                        <!-- Polling Status Indicator -->
                        <div class="flex items-center gap-3 justify-center py-2.5 px-6 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 rounded-full text-sm font-bold animate-pulse border border-amber-200 dark:border-amber-800/50 shadow-sm">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            Menunggu Pembayaran Pelanggan...
                        </div>
                    </div>

                    <!-- STATE 2: SUCCESS -->
                    <div v-else-if="qrisPaymentStatus === 'SUCCESS'" class="py-16 px-8 space-y-6 flex flex-col items-center text-center">
                        <div class="w-28 h-28 bg-emerald-100 dark:bg-emerald-950/50 rounded-full flex items-center justify-center text-emerald-500 text-6xl shadow-inner border-8 border-emerald-50 dark:border-emerald-900/30">
                            ✓
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-black text-3xl text-slate-900 dark:text-zinc-50">Pembayaran Sukses!</h3>
                            <p class="text-base text-slate-500 dark:text-zinc-400 leading-relaxed max-w-md mx-auto">
                                Tagihan sebesar <b class="text-slate-800 dark:text-zinc-200">Rp {{ finalTotal.toLocaleString('id-ID') }}</b> atas nama <b class="text-slate-800 dark:text-zinc-200">{{ customerName || 'Pelanggan' }}</b> telah lunas.
                            </p>
                        </div>
                        
                        <button @click="closeQrisModal" class="mt-8 w-full sm:w-2/3 py-4 bg-slate-900 hover:bg-slate-800 dark:bg-zinc-100 dark:hover:bg-white dark:text-zinc-900 text-white font-black rounded-2xl text-base shadow-xl transition-all">
                            Cetak Struk & Tutup
                        </button>
                    </div>

                    <!-- STATE 3: FAILED -->
                    <div v-else class="py-16 px-8 space-y-6 flex flex-col items-center text-center">
                        <div class="w-28 h-28 bg-red-100 dark:bg-red-950/50 rounded-full flex items-center justify-center text-red-500 text-6xl shadow-inner border-8 border-red-50 dark:border-red-900/30">
                            ✕
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-black text-3xl text-slate-900 dark:text-zinc-50">Transaksi Gagal</h3>
                            <p class="text-base text-slate-500 dark:text-zinc-400 max-w-md mx-auto">Waktu pembayaran untuk tagihan ini telah habis atau dibatalkan oleh sistem.</p>
                        </div>
                        
                        <button @click="closeQrisModal" class="mt-8 w-full sm:w-2/3 py-4 bg-white border-2 border-slate-200 text-slate-700 hover:bg-slate-50 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-700 font-black rounded-2xl text-base shadow-sm transition-all">
                            Tutup Jendela
                        </button>
                    </div>

                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>