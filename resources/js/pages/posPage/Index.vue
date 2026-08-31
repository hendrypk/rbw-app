<script setup lang="ts">
/**
 * POS Index View Component
 * Handles product browsing, cart manipulation, and payment interactions.
 */
import { ref, onMounted, computed, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import PosLayout from '@/layouts/PosLayout.vue';
import { Plus, Minus, Tag, Percent, Menu as MenuIcon, Receipt, FileText, X, LayoutGrid, List } from '@lucide/vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { usePosCheckout } from '@/composables/usePosCheckout';
import { useThermalPrinter } from '@/composables/useThermalPrinter';
import { 
    mapTransactionToReceiptData, 
} from '@/composables/useReceiptFormatter';
import { formatCashierReceipt, formatKitchenReceipt } from '@/composables/useReceiptBuilder';
import PaymentModal from '@/components/pos/PaymentModal.vue';
import CustomerSelectModal from '@/components/pos/CustomerSelectModal.vue';
import DiscountModal from '@/components/pos/DiscountModal.vue';
import CustomerAddModal from '@/components/pos/CustomerAddModal.vue';

defineOptions({
    layout: PosLayout
});

const { print } = useThermalPrinter();

const windowWidth = ref<number>(window.innerWidth);

const updateWindowWidth = () => {
    windowWidth.value = window.innerWidth;
};

// Composable Bisnis Logika POS
const {
    isPaymentModalOpen, isQrisModalOpen, isGeneratingQris, isCustomerModalOpen, isDiscountModalOpen, isCustomerAddModalOpen,
    customerName, customerId, orderNote, discountInput, transactionFee, paymentMethod, amountPaidInput,
    cart, qrisData, lastCompletedOrder, cartSubtotal,
    finalTotal, isSuccessModalOpen, paymentStatus, closeSuccessModal, openCustomerModal, openDiscountModal, openCustomerAddModal,
    vouchers, appliedVoucher, isLoadingVouchers, fetchVouchers, validateAndApplyVoucher, removeVoucher, getCartValidationItems,
    openPaymentModal, closePaymentModal, closeQrisModal: baseCloseQrisModal,
    submitCheckout, handleQrisCheckout: baseHandleQrisCheckout
} = usePosCheckout();

// Fungsi Cetak Struk
// const handlePrintReceipt = async () => {
//     if (lastCompletedOrder.value && lastCompletedOrder.value.orderNumber !== '-') {
//         const formattedData = mapTransactionToReceiptData(lastCompletedOrder.value);
//         const textStruk = formatCashierReceipt(formattedData);
//         await print(textStruk);
//         toast.success("Struk kasir dicetak.");
//     } else {
//         toast.error("Data transaksi tidak ditemukan untuk dicetak.");
//     }
//     closeSuccessModal();
// };


// Fungsi Cetak Struk Kasir & Bill (Dapur) Sekaligus
// Fungsi Cetak Struk Kasir & Bill (Dapur) dengan jeda aman
const handlePrintReceipt = async () => {
    if (lastCompletedOrder.value && lastCompletedOrder.value.orderNumber !== '-') {
        console.log("📌 [Cetak] Memulai proses persiapan data transaksi...", lastCompletedOrder.value);
        
        const formattedData = mapTransactionToReceiptData(lastCompletedOrder.value);
        
        // 1. Cetak Struk Kasir
        const textStruk = formatCashierReceipt(formattedData);
        console.group("🖨️ [Cetak - 1] Payload Struk Kasir");
        console.log(textStruk);
        console.groupEnd();

        await print(textStruk);
        console.log("✅ [Cetak - 1] Struk kasir berhasil dilempar ke antrean printer.");

        // Berikan jeda 2 detik (2000ms) agar RawBT selesai meluncurkan printer pertama
        console.log("⏳ [Cetak] Menunggu jeda cooldown 2 detik...");
        await new Promise(resolve => setTimeout(resolve, 2000));

        // 2. Cetak Tiket Dapur / Bill
        const textDapur = formatKitchenReceipt(formattedData);
        console.group("📋 [Cetak - 2] Payload Tiket Dapur / Bill");
        console.log(textDapur);
        console.groupEnd();

        await print(textDapur);
        console.log("✅ [Cetak - 2] Tiket dapur berhasil dilempar ke antrean printer.");

        toast.success("Struk kasir dan bill dapur berhasil dicetak.");
    } else {
        console.warn("⚠️ [Cetak] Gagal: Data transaksi tidak ditemukan atau nomor nota kosong.", lastCompletedOrder.value);
        toast.error("Data transaksi tidak ditemukan untuk dicetak.");
    }
    closeSuccessModal();
};

// ==========================================
// 1. FITUR RESIZABLE COLUMN (LANDSCAPE DRAG)
// ==========================================
const catalogWidth = ref<number>(20); // Persentase lebar area katalog default (65%)
const isDragging = ref<boolean>(false);

const startDrag = () => {
    isDragging.value = true;
    window.addEventListener('mousemove', onDrag);
    window.addEventListener('mouseup', stopDrag);
};

const onDrag = (e: MouseEvent) => {
    if (!isDragging.value) return;
    const totalWidth = window.innerWidth;
    const newWidth = (e.clientX / totalWidth) * 100;
    // Batasi rentang lebar antara 40% sampai 80%
    if (newWidth >= 20 && newWidth <= 40) {
        catalogWidth.value = newWidth;
    }
};

const stopDrag = () => {
    isDragging.value = false;
    window.removeEventListener('mousemove', onDrag);
    window.removeEventListener('mouseup', stopDrag);
};

// ==========================================
// 2. FITUR SWIPE UP / TOGGLE CART (PORTRAIT)
// ==========================================
const isCartExpanded = ref<boolean>(false);
const touchStartY = ref<number>(0);

const handleTouchStart = (e: TouchEvent) => {
    touchStartY.value = e.touches[0].clientY;
};

const handleTouchEnd = (e: TouchEvent) => {
    const touchEndY = e.changedTouches[0].clientY;
    const diff = touchStartY.value - touchEndY;
    if (diff > 50) {
        isCartExpanded.value = true; // Swipe Up
    } else if (diff < -50) {
        isCartExpanded.value = false; // Swipe Down
    }
};

// ==========================================
// 3. FITUR UBAH TAMPILAN ICON / LAYOUT MENU
// ==========================================
// State untuk Dropdown Kontrol Tampilan Menu
const isViewDropdownOpen = ref<boolean>(false);
let dropdownTimer: any = null;

// Fungsi untuk menutup dropdown dengan delay 2 detik
const delayedCloseDropdown = () => {
    if (dropdownTimer) clearTimeout(dropdownTimer);
    dropdownTimer = setTimeout(() => {
        isViewDropdownOpen.value = false;
    }, 2000); // 2000 ms = 2 detik
};

// Fungsi saat mouse/touch masuk ke dalam dropdown (membatalkan timer tutup)
const cancelCloseDropdown = () => {
    if (dropdownTimer) clearTimeout(dropdownTimer);
};


const viewMode = ref<'grid' | 'list'>('grid');
const gridScale = ref<number>(2);
const gridColumnsClass = computed(() => {
    switch (gridScale.value) {
        case 1: return 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6'; // Sangat kecil (Zoom Out maksimal)
        case 2: return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5'; // Sedang (Default)
        case 3: return 'grid-cols-2 sm:grid-cols-2 lg:grid-cols-3'; // Besar 
        case 4: return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-2'; // Sangat besar (Zoom In maksimal)
        default: return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5';
    }
});

// ==========================================
// COUNTDOWN TIMER STATE & LOGIC
// ==========================================
const remainingSeconds = ref<number>(900);
let timerInterval: any = null;

const formattedCountdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const startCountdown = () => {
    remainingSeconds.value = 900;
    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        if (remainingSeconds.value > 0) {
            remainingSeconds.value--;
        } else {
            paymentStatus.value = 'FAILED';
            if (timerInterval) clearInterval(timerInterval);
        }
    }, 1000);
};

const handleQrisCheckout = async () => {
    await baseHandleQrisCheckout();
    if (isQrisModalOpen.value) {
        startCountdown();
    }
};

const closeQrisModal = () => {
    if (timerInterval) clearInterval(timerInterval);
    baseCloseQrisModal();
};

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// ==========================================
// CATALOG & CART MANAGEMENT
// ==========================================
const menus = ref<any[]>([]);
const categories = ref<any[]>([]);
const isLoading = ref<boolean>(true);
const searchQuery = ref<string>('');
const selectedCategory = ref<string>('all');

const fetchData = async () => {
    try {
        isLoading.value = true;
        
        // Ambil data menu dan kategori secara paralel dari endpoint masing-masing
        const [menuResponse, categoryResponse] = await Promise.all([
            axios.get('/api/menus'),
            axios.get('/api/categories')
        ]);

        // Masukkan data menu (sesuaikan dengan format wrapper response API)
        menus.value = menuResponse.data.data || menuResponse.data;

        // Ambil data kategori langsung dari endpoint /api/categories (filter hanya yang visible jika ada properti is_visible)
        const allCategories = categoryResponse.data.data || categoryResponse.data;
        categories.value = allCategories.filter((cat: any) => cat.is_visible ?? true);

    } catch (error) {
        console.error('Failed to fetch POS menu data:', error);
        toast.error('Gagal mengambil data dari server');
    } finally {
        isLoading.value = false;
    }
};

const filteredMenus = computed(() => {
    return menus.value.filter(menu => {
        const matchesSearch = menu.name.toLowerCase().includes(searchQuery.value.toLowerCase());
        
        // Mendukung multi-kategori (melalui array categories) ataupun kategori tunggal
        const matchesCategory = selectedCategory.value === 'all' || 
            (menu.categories && menu.categories.some((cat: any) => cat.id === selectedCategory.value)) ||
            menu.category_id === selectedCategory.value;

        return matchesSearch && matchesCategory;
    });
});

const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    // Cek apakah yang diklik berada di luar elemen dropdown menu
    if (!target.closest('.view-dropdown-container')) {
        isViewDropdownOpen.value = false;
    }
};

onMounted(() => {
    fetchData();
    window.addEventListener('resize', updateWindowWidth);
    window.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
    window.removeEventListener('resize', updateWindowWidth);
    window.addEventListener('click', handleClickOutside);
});

// const filteredMenus = computed(() => {
//     return menus.value.filter(menu => {
//         const matchesSearch = menu.name.toLowerCase().includes(searchQuery.value.toLowerCase());
//         const matchesCategory = selectedCategory.value === 'all' || menu.category_id === selectedCategory.value;
//         return matchesSearch && matchesCategory;
//     });
// });

const getOfflinePriceObject = (menu: any) => {
    if (!menu.prices) return null;
    return menu.prices.find((p: any) => p.channel === 'offline' && p.is_active);
};

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

const getInitials = (name: string) => {
    return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
};
</script>

<template>
    <Head title="RBW POS" />
    <div class="h-full w-full flex flex-col md:flex-row overflow-hidden relative select-none">
        
        <!-- ========================================================= -->
        <!-- LEFT PANEL: CART & CHECKOUT CONTAINER (SEKARANG DI KIRI)  -->
        <!-- ========================================================= -->
<div 
            class="fixed lg:relative bottom-0 left-0 right-0 z-30 bg-white dark:bg-zinc-900 border-t lg:border-t-0 border-slate-200 dark:border-zinc-800 flex flex-col shadow-2xl lg:shadow-none transition-all duration-300 ease-out overflow-hidden"
            :style="{ width: windowWidth >= 1024 ? `${catalogWidth}%` : '100%' }"
            :class="[
                isCartExpanded ? 'h-[90vh]' : 'h-auto lg:h-full',
                'lg:flex-initial'
            ]"
            @touchstart="handleTouchStart"
            @touchend="handleTouchEnd"
        >
            <!-- Handle Bar untuk Swipe Up di Mobile & Tablet Portrait -->
            <div @click="isCartExpanded = !isCartExpanded" class="lg:hidden w-full flex flex-col items-center pt-2 pb-1 bg-slate-50 dark:bg-zinc-900 border-b border-slate-100 dark:border-zinc-800 cursor-pointer shrink-0">
                <div class="w-10 h-1.5 bg-slate-300 dark:bg-zinc-700 rounded-full mb-1"></div>
                <div class="flex items-center justify-between w-full px-4 text-md font-bold text-slate-600 dark:text-zinc-300">
                    <span>{{ cart.length }} Item di Keranjang</span>
                    <span class="text-primary font-black">Rp {{ finalTotal.toLocaleString('id-ID') }}</span>
                </div>
            </div>

            <!-- Cart Header -->
            <div class="p-3 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between bg-white dark:bg-zinc-900 shrink-0 gap-2">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <button 
                        @click="openCustomerModal" 
                        type="button"
                        class="text-md font-bold text-slate-800 dark:text-zinc-100 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 px-3 py-1.5 rounded-xl transition-all truncate text-left w-full flex items-center justify-between cursor-pointer"
                    >
                        <span class="truncate">{{ customerName || 'Pilih Pelanggan' }}</span>
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider ml-1 shrink-0">Ganti</span>
                    </button>
                </div>

                <button 
                    @click="cart = []" 
                    :disabled="cart.length === 0"
                    class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/30 dark:hover:bg-red-900/50 text-red-600 dark:text-red-400 rounded-xl text-md font-bold transition-all disabled:opacity-30 disabled:cursor-not-allowed shrink-0 cursor-pointer"
                    title="Kosongkan Keranjang"
                >
                    Clear All
                </button>
            </div>

            <!-- Cart Items List (Tampil saat expanded di mobile/tablet portrait) -->
            <div :class="[isCartExpanded ? 'flex-1' : 'hidden lg:block lg:flex-1', 'overflow-y-auto divide-y divide-slate-100 dark:divide-zinc-800 custom-scrollbar p-2']">
                <div v-if="cart.length === 0" class="text-center py-16 text-md text-slate-400">
                    Keranjang kosong. Klik menu untuk menambah.
                </div>
                
                <div 
                    v-for="item in cart" 
                    :key="item.menu_id" 
                    class="p-3 hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 transition-colors flex items-center justify-between gap-3 rounded-xl"
                >
                    <div class="space-y-0.5 flex-1 min-w-0">
                        <h4 class="font-bold text-md sm:text-sm text-slate-900 dark:text-zinc-100 truncate">
                            {{ item.name }}
                        </h4>
                        <span class="text-md text-slate-400 font-mono">Rp {{ Number(item.price).toLocaleString('id-ID') }}</span>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="updateQuantity(item.menu_id, -1)" class="p-1 bg-slate-100 dark:bg-zinc-800 rounded-md text-slate-600 dark:text-zinc-300 hover:bg-red-100 hover:text-red-600 transition-colors">
                            <Minus class="h-3.5 w-3.5" />
                        </button>
                        <span class="font-bold text-md w-5 text-center text-slate-900 dark:text-zinc-100">{{ item.quantity }}</span>
                        <button @click="updateQuantity(item.menu_id, 1)" class="p-1 bg-slate-100 dark:bg-zinc-800 rounded-md text-slate-600 dark:text-zinc-300 hover:bg-slate-200 transition-colors">
                            <Plus class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div class="text-right shrink-0 font-black text-md sm:text-sm text-slate-900 dark:text-zinc-100 font-mono w-20">
                        Rp {{ Number(item.subtotal).toLocaleString('id-ID') }}
                    </div>
                </div>
            </div>

            <!-- Catatan Pesanan -->
            <div :class="[isCartExpanded ? 'block' : 'hidden lg:block', 'px-3 py-1.5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/50 shrink-0']">
                <input 
                    v-model="orderNote"
                    type="text" 
                    placeholder="Tambah catatan pesanan..." 
                    class="w-full text-md px-3 py-1 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg text-slate-700 dark:text-zinc-300 focus:outline-none"
                />
            </div>

            <!-- Footer Summary & Checkout Actions -->
            <div class="p-3 border-t border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 space-y-2 shrink-0">
                <div class="space-y-1 px-1">
                    <div class="flex items-center justify-between text-md text-slate-500 dark:text-zinc-400">
                        <span>Subtotal</span>
                        <span class="font-mono">Rp {{ cartSubtotal.toLocaleString('id-ID') }}</span>
                    </div>

                    <div v-if="appliedVoucher" class="flex items-center justify-between text-md text-emerald-600 dark:text-emerald-400 font-semibold">
                        <span class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                            Voucher ({{ appliedVoucher.code }})
                        </span>
                        <span class="font-mono">- Rp {{ appliedVoucher.discount_amount.toLocaleString('id-ID') }}</span>
                    </div>

                    <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 dark:border-zinc-800">
                        <span class="text-md uppercase font-bold text-slate-400 tracking-wider">Total</span>
                        <span class="text-sm sm:text-base font-black text-slate-900 dark:text-zinc-50 font-mono">
                            Rp {{ finalTotal.toLocaleString('id-ID') }}
                        </span>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="flex items-center gap-2 pt-0.5">
                    <button 
                        @click="openDiscountModal" 
                        class="w-13 h-13 rounded-xl bg-orange-50 hover:bg-orange-100 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 flex items-center justify-center transition-all border border-orange-200/60 dark:border-orange-900/50 cursor-pointer relative shrink-0"
                        title="Beri Diskon / Voucher"
                    >
                        <Percent class="w-4 h-4" />
                        <span v-if="appliedVoucher" class="absolute -top-1 -right-1 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white dark:border-zinc-950"></span>
                    </button>

                    <div class="flex items-center gap-2 flex-1">
                        <button 
                            @click="submitCheckout('save')"
                            :disabled="cart.length === 0"
                            class="flex-1 py-3.5 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-md shadow-xs transition-all disabled:opacity-40 cursor-pointer text-center"
                        >
                            Simpan
                        </button>
                        
                        <button 
                            @click="openPaymentModal"
                            :disabled="cart.length === 0"
                            class="flex-1 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl text-md shadow-md transition-all disabled:opacity-40 cursor-pointer text-center"
                        >
                            Bayar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- ========================================================= -->
        <!-- DRAGGABLE RESIZER BAR                                     -->
        <!-- ========================================================= -->
        <div 
            @mousedown="startDrag"
            class="hidden md:flex w-1.5 bg-slate-200 dark:bg-zinc-800 hover:bg-primary cursor-col-resize items-center justify-center transition-colors shrink-0 z-10"
            title="Tarik untuk mengatur lebar kolom"
        >
            <div class="w-0.5 h-8 bg-slate-400 dark:bg-zinc-600 rounded-full"></div>
        </div>

        <!-- ========================================================= -->
        <!-- RIGHT PANEL: CATALOG & CATEGORY FILTER (SEKARANG DI KANAN)-->
        <!-- ========================================================= -->
        <div class="flex-1 h-full overflow-y-auto p-4 space-y-4 custom-scrollbar flex flex-col transition-all duration-75">
            <div class="sticky top-0 z-20 pt-2 pb-2 space-y-3 shrink-0">
                <!-- Search & View Dropdown -->
                <div class="flex items-center justify-between gap-3 bg-white dark:bg-zinc-900 p-3 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-xs">
                    <div class="flex-1 max-w-md">
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Cari menu makanan / minuman..." 
                            class="w-full px-3.5 py-2 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-primary text-slate-900 dark:text-white placeholder:text-slate-400"
                        />
                    </div>
                    <div class="relative view-dropdown-container">
                        <button 
                            @click="isViewDropdownOpen = !isViewDropdownOpen"
                            class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-md font-bold transition-all border bg-slate-50 border-slate-200 text-slate-700 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-200 hover:border-primary cursor-pointer shadow-2xs"
                        >
                            <LayoutGrid class="w-4 h-4 text-primary" />
                            <span>Tampilan Menu</span>
                        </button>

                        <div 
                            v-if="isViewDropdownOpen" 
                            class="absolute right-0 mt-2 w-64 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl shadow-xl p-4 z-50 space-y-4 animate-fade-in"
                        >
                            <div 
                                @click="viewMode = 'grid'; isViewDropdownOpen = false"
                                class="flex items-center justify-between cursor-pointer group py-1"
                            >
                                <span class="text-md font-bold text-slate-800 dark:text-zinc-200 group-hover:text-primary transition-colors">
                                    Mode Gambar
                                </span>
                                <span v-if="viewMode === 'grid'" class="text-emerald-600 font-black text-sm">✓</span>
                            </div>

                            <div v-if="viewMode === 'grid'" class="space-y-2.5 pt-2 border-t border-slate-100 dark:border-zinc-800" @mousedown.stop @touchstart.stop>
                                <span class="text-[11px] font-bold text-slate-400 block">Zoom in / Zoom out</span>
                                
                                <div class="relative py-2 px-1">
                                    <div class="absolute inset-x-2 top-1/2 -translate-y-1/2 h-1 bg-emerald-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                        <div 
                                            class="h-full bg-emerald-500 transition-all duration-150"
                                            :style="{ width: `${((gridScale - 1) / 3) * 100}%` }"
                                        ></div>
                                    </div>

                                    <div class="absolute inset-x-2.5 top-1/2 -translate-y-1/2 flex items-center justify-between pointer-events-none">
                                        <div class="w-2 h-2 rounded-full" :class="gridScale >= 1 ? 'bg-emerald-600' : 'bg-emerald-300'"></div>
                                        <div class="w-2 h-2 rounded-full" :class="gridScale >= 2 ? 'bg-emerald-600' : 'bg-emerald-300'"></div>
                                        <div class="w-2 h-2 rounded-full" :class="gridScale >= 3 ? 'bg-emerald-600' : 'bg-emerald-300'"></div>
                                        <div class="w-2 h-2 rounded-full" :class="gridScale >= 4 ? 'bg-emerald-600' : 'bg-emerald-300'"></div>
                                    </div>

                                    <input 
                                        type="range" 
                                        v-model.number="gridScale" 
                                        min="1" 
                                        max="4" 
                                        step="1" 
                                        class="w-full opacity-0 cursor-pointer relative z-20 h-6 block"
                                    />
                                </div>
                            </div>

                            <div 
                                @click="viewMode = 'list'; isViewDropdownOpen = false"
                                class="flex items-center justify-between cursor-pointer group pt-2 border-t border-slate-100 dark:border-zinc-800"
                            >
                                <span class="text-md font-bold text-slate-800 dark:text-zinc-200 group-hover:text-primary transition-colors">
                                    Mode Daftar
                                </span>
                                <span v-if="viewMode === 'list'" class="text-emerald-600 font-black text-sm">✓</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Horizontal Scroll Kategori -->
                <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none border-b border-slate-100 dark:border-zinc-800/60">
                    <button 
                        @click="selectedCategory = 'all'"
                        :class="[
                            'px-4 py-1.5 text-md font-semibold rounded-full whitespace-nowrap border transition-all',
                            selectedCategory === 'all' 
                                ? 'bg-primary border-primary text-primary-foreground shadow-sm' 
                                : 'bg-white border-slate-200 text-slate-600 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 hover:border-slate-300'
                        ]"
                    >
                        Semua Kategori
                    </button>
                    <button 
                        v-for="cat in categories" 
                        :key="cat.id"
                        @click="selectedCategory = cat.id"
                        :class="[
                            'px-4 py-1.5 text-md font-semibold rounded-full whitespace-nowrap border transition-all',
                            selectedCategory === cat.id 
                                ? 'bg-primary border-primary text-primary-foreground shadow-sm' 
                                : 'bg-white border-slate-200 text-slate-600 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 hover:border-slate-300'
                        ]"
                    >
                        {{ cat.name }}
                    </button>
                </div>
            </div>

            <!-- Menu Layout: GRID VIEW -->
            <div v-if="viewMode === 'grid'" :class="['grid gap-4 pb-20 md:pb-4 transition-all duration-200', gridColumnsClass]">
                <div 
                    v-for="menu in filteredMenus" 
                    :key="menu.id" 
                    @click="addToCart(menu)"
                    class="bg-white dark:bg-zinc-900 p-3.5 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-xs cursor-pointer hover:border-primary active:scale-[0.97] transition-all flex flex-col justify-between aspect-3/4 group"
                >
                    <div class="w-full aspect-square bg-slate-100 dark:bg-zinc-800 rounded-xl overflow-hidden relative shrink-0 shadow-inner">
                        <img 
                            v-if="menu.image_path" 
                            :src="menu.image_path.startsWith('http') ? menu.image_path : `/storage/${menu.image_path}`" 
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                            alt="Menu"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center bg-primary/5 text-primary font-black text-xl">
                            {{ getInitials(menu.name) }}
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col justify-between pt-3">
                        <h3 class="font-black text-md sm:text-sm text-slate-900 dark:text-zinc-50 line-clamp-2 leading-tight group-hover:text-primary transition-colors">
                            {{ menu.name }}
                        </h3>
                        <div class="flex items-end justify-between mt-2 pt-1 border-t border-slate-50 dark:border-zinc-800/50">
                            <span class="text-[10px] text-slate-400 font-semibold truncate max-w-20">{{ menu.category?.name || 'Umum' }}</span>
                            <span class="font-black text-md sm:text-sm text-primary whitespace-nowrap">
                                Rp {{ Number(getOfflinePriceObject(menu)?.selling_price || 0).toLocaleString('id-ID') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menu Layout: LIST VIEW -->
            <div v-else class="space-y-2 pb-20 md:pb-4">
                <div 
                    v-for="menu in filteredMenus" 
                    :key="menu.id" 
                    @click="addToCart(menu)"
                    class="bg-white dark:bg-zinc-900 p-3 rounded-xl border border-slate-200 dark:border-zinc-800 shadow-xs cursor-pointer hover:border-primary transition-all flex items-center justify-between gap-4"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-slate-100 dark:bg-zinc-800 rounded-lg overflow-hidden shrink-0">
                            <img v-if="menu.image_path" :src="menu.image_path.startsWith('http') ? menu.image_path : `/storage/${menu.image_path}`" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-md font-bold text-primary">{{ getInitials(menu.name) }}</div>
                        </div>
                        <div>
                            <h4 class="font-bold text-sm text-slate-900 dark:text-zinc-100">{{ menu.name }}</h4>
                            <span class="text-md text-slate-400">{{ menu.category?.name || 'Umum' }}</span>
                        </div>
                    </div>
                    <span class="font-black text-sm text-primary font-mono">
                        Rp {{ Number(getOfflinePriceObject(menu)?.selling_price || 0).toLocaleString('id-ID') }}
                    </span>
                </div>
            </div>

            <div v-if="isLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <div v-for="i in 10" :key="i" class="bg-white dark:bg-zinc-900 p-3 rounded-2xl border border-slate-200 dark:border-zinc-800 animate-pulse h-40"></div>
            </div>

            <div v-else-if="filteredMenus.length === 0" class="text-center py-16 text-sm text-slate-400">
                Menu tidak ditemukan.
            </div>
        </div>

    </div>

    <PaymentModal 
        :is-payment-modal-open="isPaymentModalOpen"
        :is-qris-modal-open="isQrisModalOpen"
        :is-success-modal-open="isSuccessModalOpen"
        :final-total="finalTotal"
        :is-generating-qris="isGeneratingQris"
        v-model:customer-name="customerName"
        v-model:discount-input="discountInput"
        v-model:amount-paid-input="amountPaidInput"
        :qris-data="qrisData"
        :qris-payment-status="paymentStatus"
        :payment-status="paymentStatus"
        :formatted-countdown="formattedCountdown"
        @close-payment-modal="closePaymentModal"
        @close-qris-modal="closeQrisModal"
        @close-success-modal="closeSuccessModal"
        @submit-cash="submitCheckout('pay')"
        @handle-qris-checkout="handleQrisCheckout"
        @handle-print-receipt="handlePrintReceipt"
    />

    <CustomerSelectModal 
        v-if="isCustomerModalOpen"
        :is-open="isCustomerModalOpen"
        v-model:customer-name="customerName"
        @select="(customer: any) => { 
            customerName = customer.name; 
            customerId = customer.id; 
        }"
        @close="isCustomerModalOpen = false"
        @open-add="isCustomerModalOpen = false; isCustomerAddModalOpen = true;"
    />
<CustomerAddModal 
        v-if="isCustomerAddModalOpen"
        :is-open="isCustomerAddModalOpen"
        @back="isCustomerAddModalOpen = false; isCustomerModalOpen = true;"
        @saved="(newCust: any) => { 
            customerName = newCust.name; 
            customerId = newCust.id;
        }"
        @close="isCustomerAddModalOpen = false"
    />

<DiscountModal 
        v-if="isDiscountModalOpen"
        :is-open="isDiscountModalOpen"
        :current-discount="discountInput"
        :vouchers="vouchers"
        :applied-voucher="appliedVoucher"
        :is-loading-vouchers="isLoadingVouchers"
        :fetch-vouchers="fetchVouchers"
        :validate-and-apply-voucher="validateAndApplyVoucher"
        :remove-voucher="removeVoucher"
        :get-cart-validation-items="getCartValidationItems"
        :menus="menus"
        @apply-manual="(amount: number) => { discountInput = amount; isDiscountModalOpen = false; }"
        @close="isDiscountModalOpen = false"
    />
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