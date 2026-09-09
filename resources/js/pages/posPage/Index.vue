<script setup lang="ts">
/**
 * POS Index View Component
 * Handles product browsing, cart manipulation, and payment interactions.
 */

// ==========================================
// 1. IMPORTS
// ==========================================
// Vue & Core
import { ref, onMounted, computed, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';

// Icons
import { Plus, Minus, Tag, Percent, Menu as MenuIcon, Receipt, FileText, X, LayoutGrid, List } from '@lucide/vue';

// Plugins & Utils
import axios from 'axios';
import { toast } from 'vue-sonner';

// Composables
import { usePosCheckout } from '@/composables/usePosCheckout';
import { useThermalPrinter } from '@/composables/useThermalPrinter';
import { mapTransactionToReceiptData } from '@/composables/useReceiptFormatter';
import { formatCashierReceipt, formatKitchenReceipt } from '@/composables/useReceiptBuilder';

// Components & Layout
import PosLayout from '@/layouts/PosLayout.vue';
import PaymentModal from '@/components/pos/PaymentModal.vue';
import CustomerSelectModal from '@/components/pos/CustomerSelectModal.vue';
import DiscountModal from '@/components/pos/DiscountModal.vue';
import CustomerAddModal from '@/components/pos/CustomerAddModal.vue';
import OutletSelectModal from './OutletSelectModal.vue';

// ==========================================
// 2. OPTIONS & PROPS
// ==========================================
defineOptions({ layout: PosLayout });

const props = defineProps<{
    outlets?: any[];
    activeOutletId?: string | null;
}>();

// ==========================================
// 3. COMPOSABLES
// ==========================================
const { print } = useThermalPrinter();

const {
    isPaymentModalOpen, isQrisModalOpen, isGeneratingQris, isCustomerModalOpen, isDiscountModalOpen, isCustomerAddModalOpen,
    customerName, customerId, orderNote, discountInput, transactionFee, paymentMethod, amountPaidInput,
    cart, qrisData, lastCompletedOrder, cartSubtotal,
    finalTotal, isSuccessModalOpen, paymentStatus, closeSuccessModal, openCustomerModal, openDiscountModal, openCustomerAddModal,
    vouchers, appliedVoucher, isLoadingVouchers, fetchVouchers, validateAndApplyVoucher, removeVoucher, getCartValidationItems,
    openPaymentModal, closePaymentModal, closeQrisModal: baseCloseQrisModal,
    submitCheckout, handleQrisCheckout: baseHandleQrisCheckout
} = usePosCheckout();

// ==========================================
// 4. STATE: OUTLET MANAGEMENT
// ==========================================
const currentOutletId = ref<string | null>(localStorage.getItem('active_outlet_id') || props.activeOutletId || null);
const isOutletModalOpen = ref<boolean>(!currentOutletId.value);

// ==========================================
// 5. STATE: CATALOG & MENUS
// ==========================================
const menus = ref<any[]>([]);
const categories = ref<any[]>([]);
const isLoading = ref<boolean>(true);
const searchQuery = ref<string>('');
const selectedCategory = ref<string>('all');

// ==========================================
// 6. STATE: UI & LAYOUT (Resizing, Touch, View Mode)
// ==========================================
const windowWidth = ref<number>(window.innerWidth);

// Resizable Column (Landscape)
const catalogWidth = ref<number>(20);
const isDragging = ref<boolean>(false);

// Swipe Up / Toggle Cart (Portrait)
const isCartExpanded = ref<boolean>(false);
const touchStartY = ref<number>(0);

// View Mode (Grid / List)
const isViewDropdownOpen = ref<boolean>(false);
let dropdownTimer: any = null;
const viewMode = ref<'grid' | 'list'>('grid');
const gridScale = ref<number>(2);

// ==========================================
// 7. STATE: PAYMENT COUNTDOWN
// ==========================================
const remainingSeconds = ref<number>(900);
let timerInterval: any = null;

// ==========================================
// 8. COMPUTED PROPERTIES
// ==========================================
const filteredMenus = computed(() => {
    return menus.value.filter(menu => {
        const matchesSearch = menu.name.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCategory = selectedCategory.value === 'all' || 
            (menu.categories && menu.categories.some((cat: any) => cat.id === selectedCategory.value)) ||
            menu.category_id === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});

const gridColumnsClass = computed(() => {
    switch (gridScale.value) {
        case 1: return 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6';
        case 2: return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5';
        case 3: return 'grid-cols-2 sm:grid-cols-2 lg:grid-cols-3';
        case 4: return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-2';
        default: return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5';
    }
});

const formattedCountdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// ==========================================
// 9. METHODS: DATA FETCHING & OUTLET
// ==========================================
const handleOutletSelect = (outlet: any) => {
    if (!outlet || !outlet.id) {
        toast.error('Data outlet tidak valid. Gagal memilih outlet.');
        return; 
    }
    localStorage.setItem('active_outlet_id', outlet.id);
    localStorage.setItem('active_outlet_name', outlet.name || 'Outlet'); 
    currentOutletId.value = outlet.id;
    isOutletModalOpen.value = false;
    window.location.reload(); 
};

const fetchData = async () => {
    try {
        isLoading.value = true;
        const [menuResponse, categoryResponse] = await Promise.all([
            axios.get('/api/menus', { params: { outlet_id: currentOutletId.value } }),
            axios.get('/api/categories', { params: { outlet_id: currentOutletId.value } })
        ]);

        menus.value = menuResponse.data.data || menuResponse.data;
        const allCategories = categoryResponse.data.data || categoryResponse.data;
        categories.value = allCategories.filter((cat: any) => cat.is_visible ?? true);
    } catch (error) {
        console.error('Failed to fetch POS menu data:', error);
        toast.error('Gagal mengambil data dari server');
    } finally {
        isLoading.value = false;
    }
};

// ==========================================
// 10. METHODS: CART LOGIC
// ==========================================
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
    // toast.success(`${menu.name} ditambahkan`);
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

// ==========================================
// 11. METHODS: PRINTING
// ==========================================
const handlePrintReceipt = async () => {
    if (lastCompletedOrder.value && lastCompletedOrder.value.orderNumber !== '-') {
        const formattedData = mapTransactionToReceiptData(lastCompletedOrder.value);
        
        // 1. Cetak Struk Kasir
        const textStruk = formatCashierReceipt(formattedData);
        await print(textStruk);

        // Jeda 2 detik agar RawBT selesai
        await new Promise(resolve => setTimeout(resolve, 2000));

        // 2. Cetak Tiket Dapur / Bill
        const textDapur = formatKitchenReceipt(formattedData);
        await print(textDapur);

        toast.success("Struk kasir dan bill dapur berhasil dicetak.");
    } else {
        toast.error("Data transaksi tidak ditemukan untuk dicetak.");
    }
    closeSuccessModal();
};

// ==========================================
// 12. METHODS: UI & LAYOUT INTERACTIONS
// ==========================================
const updateWindowWidth = () => windowWidth.value = window.innerWidth;

// Drag Resize
const startDrag = () => {
    isDragging.value = true;
    window.addEventListener('mousemove', onDrag);
    window.addEventListener('mouseup', stopDrag);
};
const onDrag = (e: MouseEvent) => {
    if (!isDragging.value) return;
    const totalWidth = window.innerWidth;
    const newWidth = (e.clientX / totalWidth) * 100;
    if (newWidth >= 20 && newWidth <= 40) catalogWidth.value = newWidth;
};
const stopDrag = () => {
    isDragging.value = false;
    window.removeEventListener('mousemove', onDrag);
    window.removeEventListener('mouseup', stopDrag);
};

// Touch Gestures
const handleTouchStart = (e: TouchEvent) => touchStartY.value = e.touches[0].clientY;
const handleTouchEnd = (e: TouchEvent) => {
    const diff = touchStartY.value - e.changedTouches[0].clientY;
    if (diff > 50) isCartExpanded.value = true;
    else if (diff < -50) isCartExpanded.value = false;
};

// Dropdown
const delayedCloseDropdown = () => {
    if (dropdownTimer) clearTimeout(dropdownTimer);
    dropdownTimer = setTimeout(() => { isViewDropdownOpen.value = false; }, 2000);
};
const cancelCloseDropdown = () => {
    if (dropdownTimer) clearTimeout(dropdownTimer);
};
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.view-dropdown-container')) isViewDropdownOpen.value = false;
};

// ==========================================
// 13. METHODS: PAYMENT & UTILS
// ==========================================
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
    if (isQrisModalOpen.value) startCountdown();
};

const closeQrisModal = () => {
    if (timerInterval) clearInterval(timerInterval);
    baseCloseQrisModal();
};

const getInitials = (name: string) => name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();

// State untuk Floating Category Button & Modal
const isCategoryModalOpen = ref<boolean>(false);
const floatingPos = ref({ x: 20, y: 90 }); // Posisi awal dari kanan dan bawah (dalam pixel)
let isDraggingCategory = ref(false);
let dragStartPos = { x: 0, y: 0 };

const startCategoryDrag = (e: MouseEvent | TouchEvent) => {
    isDraggingCategory.value = true;
    const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
    const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
    
    dragStartPos = {
        x: clientX + floatingPos.value.x,
        y: clientY + floatingPos.value.y
    };

    window.addEventListener('mousemove', onCategoryDrag);
    window.addEventListener('mouseup', stopCategoryDrag);
    window.addEventListener('touchmove', onCategoryDrag);
    window.addEventListener('touchend', stopCategoryDrag);
};

const onCategoryDrag = (e: MouseEvent | TouchEvent) => {
    if (!isDraggingCategory.value) return;
    const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
    const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;

    const newX = dragStartPos.x - clientX;
    const newY = dragStartPos.y - clientY;

    // Batasi area geser di dalam layar
    if (newX >= 10 && newX <= window.innerWidth - 80) floatingPos.value.x = newX;
    if (newY >= 10 && newY <= window.innerHeight - 150) floatingPos.value.y = newY;
};

const stopCategoryDrag = () => {
    isDraggingCategory.value = false;
    window.removeEventListener('mousemove', onCategoryDrag);
    window.removeEventListener('mouseup', stopCategoryDrag);
    window.removeEventListener('touchmove', onCategoryDrag);
    window.removeEventListener('touchend', stopCategoryDrag);
};

// ==========================================
// 14. LIFECYCLE HOOKS
// ==========================================
onMounted(() => {
    if (currentOutletId.value) {
        localStorage.setItem('active_outlet_id', currentOutletId.value);
        fetchData();
    }
    window.addEventListener('resize', updateWindowWidth);
    window.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
    window.removeEventListener('resize', updateWindowWidth);
    window.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <Head title="RBW POS" />
    <div class="h-full w-full flex flex-col md:flex-row overflow-hidden relative select-none">
        
        <!-- ========================================================= -->
        <!-- LEFT PANEL: CART & CHECKOUT CONTAINER (SEKARANG DI KIRI)  -->
        <!-- ========================================================= -->
        <div 
            class="fixed lg:relative bottom-0 left-0 right-0 z-30 bg-white dark:bg-zinc-950 border-t lg:border-t-0 border-slate-200 dark:border-zinc-800 flex flex-col shadow-[0_-15px_40px_rgba(0,0,0,0.08)] lg:shadow-none transition-all duration-300 ease-out overflow-hidden"
            :style="{ width: windowWidth >= 1024 ? `${catalogWidth}%` : '100%' }"
            :class="[
                isCartExpanded ? 'h-[85vh] lg:h-full' : 'h-auto lg:h-full',
                'lg:flex-initial'
            ]"
        >
            <!-- Mobile Swipe Handle (Pemicu Buka/Tutup Keranjang) -->
            <div 
                @click="isCartExpanded = !isCartExpanded" 
                @touchstart="handleTouchStart"
                @touchend="handleTouchEnd"
                class="lg:hidden w-full flex flex-col items-center pt-3 pb-2 cursor-pointer shrink-0 bg-white dark:bg-zinc-950 z-10 relative"
            >
                <div class="w-12 h-1.5 bg-slate-200 dark:bg-zinc-800 rounded-full mb-1"></div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    {{ isCartExpanded ? 'Tutup Keranjang' : 'Buka Keranjang' }}
                </span>
            </div>

            <!-- ========================================== -->
            <!-- AREA EXPANDABLE: Pelanggan, Item, Catatan  -->
            <!-- (Tampil saat isCartExpanded true di mobile)-->
            <!-- ========================================== -->
            <div :class="[isCartExpanded ? 'flex' : 'hidden lg:flex', 'flex-col flex-1 min-h-0 overflow-hidden bg-white dark:bg-zinc-950']">
                
                <!-- Header: Customer & Clear -->
                <div class="px-5 py-3 flex items-center justify-between shrink-0 border-b border-slate-100 dark:border-zinc-900">
                    <button @click="openCustomerModal" class="flex items-center gap-3 group text-left max-w-[70%]">
                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-600 dark:text-zinc-300 shrink-0 group-hover:bg-slate-200 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider leading-none">Pelanggan</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white truncate leading-tight mt-0.5">{{ customerName || 'Pilih Pelanggan' }}</span>
                        </div>
                    </button>

                    <button @click="cart = []" :disabled="cart.length === 0" class="text-xs font-bold text-red-500 hover:text-red-600 disabled:opacity-30 transition-colors">
                        Kosongkan
                    </button>
                </div>

                <!-- Cart Items List -->
                <div class="flex-1 overflow-y-auto custom-scrollbar px-3 py-2">
                    <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-slate-400 gap-3 opacity-60 min-h-[150px]">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        <span class="text-sm font-medium">Keranjang kosong</span>
                    </div>
                    
                    <div class="space-y-1">
                        <div v-for="item in cart" :key="item.menu_id" class="p-3 hover:bg-slate-50 dark:hover:bg-zinc-900 rounded-2xl transition-colors flex flex-col gap-3">
                            <div class="flex justify-between items-start gap-3">
                                <h4 class="font-bold text-sm text-slate-900 dark:text-zinc-100 leading-snug">{{ item.name }}</h4>
                                <span class="font-bold text-sm text-slate-900 dark:text-zinc-100 shrink-0">Rp {{ Number(item.subtotal).toLocaleString('id-ID') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-400 font-medium">Rp {{ Number(item.price).toLocaleString('id-ID') }} / pcs</span>
                                <div class="flex items-center bg-slate-100 dark:bg-zinc-800 rounded-full p-1">
                                    <button @click="updateQuantity(item.menu_id, -1)" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white dark:hover:bg-zinc-700 shadow-sm transition-all text-slate-700 dark:text-zinc-200"><Minus class="w-3.5 h-3.5" /></button>
                                    <span class="w-8 text-center font-bold text-sm text-slate-900 dark:text-white">{{ item.quantity }}</span>
                                    <button @click="updateQuantity(item.menu_id, 1)" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white dark:hover:bg-zinc-700 shadow-sm transition-all text-slate-700 dark:text-zinc-200"><Plus class="w-3.5 h-3.5" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Catatan -->
                <div class="px-5 py-3 border-t border-slate-50 dark:border-zinc-900/50 shrink-0">
                    <input v-model="orderNote" type="text" placeholder="Catatan (opsional)..." class="w-full text-sm py-2 bg-transparent border-b border-slate-200 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 focus:outline-none focus:border-slate-400 transition-colors placeholder:text-slate-400" />
                </div>
            </div>

            <!-- ========================================== -->
            <!-- FOOTER SELALU TAMPIL (Sticky Bottom)       -->
            <!-- (Total Rp, Qty, Diskon, Simpan, Bayar)     -->
            <!-- ========================================== -->
            <div class="shrink-0 bg-white dark:bg-zinc-950 px-5 pt-3 pb-5 border-t border-slate-200 dark:border-zinc-800 shadow-[0_-5px_15px_rgba(0,0,0,0.02)]">
                
                <!-- Ringkasan Angka & Diskon -->
                <div class="flex items-end justify-between mb-4">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 mb-0.5">
                            {{ cart.length }} Item &bull; {{ cart.reduce((acc, item) => acc + item.quantity, 0) }} Pcs
                        </span>
                        <span class="text-2xl font-black text-slate-900 dark:text-white tracking-tight leading-none">
                            Rp {{ finalTotal.toLocaleString('id-ID') }}
                        </span>
                    </div>

                    <!-- Tombol Diskon Kapsul -->
                    <button 
                        @click="openDiscountModal" 
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all active:scale-95"
                        :class="appliedVoucher ? 'bg-orange-100 text-orange-600 dark:bg-orange-900/40 dark:text-orange-400' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700'"
                    >
                        <Percent class="w-3.5 h-3.5" />
                        <span v-if="appliedVoucher">-{{ appliedVoucher.discount_amount.toLocaleString('id-ID') }}</span>
                        <span v-else>Diskon</span>
                    </button>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button 
                        @click="submitCheckout('save')"
                        :disabled="cart.length === 0"
                        class="w-1/3 py-3.5 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-900 dark:text-white font-bold rounded-2xl text-sm transition-all disabled:opacity-50 flex flex-col items-center justify-center gap-0.5"
                    >
                        <span>Simpan</span>
                    </button>
                    
                    <button 
                        @click="openPaymentModal"
                        :disabled="cart.length === 0"
                        class="w-2/3 py-3.5 bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-slate-200 dark:text-slate-900 text-white font-bold rounded-2xl text-sm transition-all disabled:opacity-50 flex justify-center items-center gap-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                        <span>Bayar</span>
                    </button>
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
            <div class="sticky top-0 z-20 pt-1 pb-1 shrink-0 bg-slate-100 dark:bg-zinc-950">
                <div class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-zinc-800 shadow-2xs">
                    <input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Cari menu..." 
                        class="w-full py-1 text-sm bg-transparent border-none focus:outline-none text-slate-900 dark:text-white placeholder:text-slate-400 font-semibold"
                    />
                    <div class="relative view-dropdown-container shrink-0">
                        <button 
                            @click="isViewDropdownOpen = !isViewDropdownOpen"
                            class="p-1.5 rounded-lg text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer"
                            title="Tampilan Menu"
                        >
                            <LayoutGrid class="w-4 h-4 text-primary" />
                        </button>

                        <!-- Dropdown Tampilan Menu -->
                        <div 
                            v-if="isViewDropdownOpen" 
                            class="absolute right-0 mt-1 w-48 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl shadow-lg p-2.5 z-50 space-y-2 text-xs"
                        >
                            <div 
                                @click="viewMode = 'grid'; isViewDropdownOpen = false"
                                class="flex items-center justify-between cursor-pointer py-1 font-bold text-slate-800 dark:text-zinc-200"
                            >
                                <span>Grid Mode</span>
                                <span v-if="viewMode === 'grid'" class="text-emerald-600">✓</span>
                            </div>

                            <div v-if="viewMode === 'grid'" class="space-y-1 pt-1 border-t border-slate-100 dark:border-zinc-800" @mousedown.stop @touchstart.stop>
                                <span class="text-[10px] text-slate-400 block">Zoom Grid</span>
                                <input 
                                    type="range" 
                                    v-model.number="gridScale" 
                                    min="1" 
                                    max="4" 
                                    step="1" 
                                    class="w-full cursor-pointer accent-primary h-4"
                                />
                            </div>

                            <div 
                                @click="viewMode = 'list'; isViewDropdownOpen = false"
                                class="flex items-center justify-between cursor-pointer pt-1 border-t border-slate-100 dark:border-zinc-800 font-bold text-slate-800 dark:text-zinc-200"
                            >
                                <span>List Mode</span>
                                <span v-if="viewMode === 'list'" class="text-emerald-600">✓</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. FLOATING DRAGGABLE CATEGORY BUTTON      -->
            <!-- ========================================== -->
            <div 
                class="fixed z-40 select-none cursor-grab active:cursor-grabbing touch-none"
                :style="{ right: floatingPos.x + 'px', bottom: floatingPos.y + 'px' }"
                @mousedown="startCategoryDrag"
                @touchstart="startCategoryDrag"
            >
                <button 
                    @click="isCategoryModalOpen = true"
                    class="w-14 h-14 bg-slate-900 hover:bg-slate-800 text-white dark:bg-white dark:text-slate-900 rounded-full shadow-2xl flex items-center justify-center border-2 border-white/20 dark:border-zinc-900 transition-transform active:scale-95"
                    title="Filter Kategori"
                >
                    <List class="w-6 h-6" />
                    <span v-if="selectedCategory !== 'all'" class="absolute -top-1 -right-1 w-4 h-4 bg-primary rounded-full border-2 border-white dark:border-zinc-900"></span>
                </button>
            </div>

            <!-- Modal Pilihan Kategori (Popup saat tombol floating ditekan) -->
            <div v-if="isCategoryModalOpen" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4">
                <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-md rounded-t-3xl sm:rounded-2xl p-6 space-y-4 max-h-[80vh] overflow-y-auto shadow-2xl animate-fade-in">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3">
                        <h3 class="text-lg font-black">Pilih Kategori Menu</h3>
                        <button @click="isCategoryModalOpen = false" class="p-1 rounded-full hover:bg-slate-100 dark:hover:bg-zinc-800">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="space-y-2">
                        <button 
                            @click="selectedCategory = 'all'; isCategoryModalOpen = false"
                            class="w-full text-left px-4 py-3 rounded-xl font-bold text-base transition-colors flex items-center justify-between"
                            :class="selectedCategory === 'all' ? 'bg-primary text-primary-foreground' : 'bg-slate-50 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300'"
                        >
                            <span>Semua Menu</span>
                            <span v-if="selectedCategory === 'all'">✓</span>
                        </button>

                        <button 
                            v-for="cat in categories" 
                            :key="cat.id"
                            @click="selectedCategory = cat.id; isCategoryModalOpen = false"
                            class="w-full text-left px-4 py-3 rounded-xl font-bold text-base transition-colors flex items-center justify-between"
                            :class="selectedCategory === cat.id ? 'bg-primary text-primary-foreground' : 'bg-slate-50 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300'"
                        >
                            <span>{{ cat.name }}</span>
                            <span v-if="selectedCategory === cat.id">✓</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Menu Layout: GRID VIEW -->
            <div v-if="viewMode === 'grid'" :class="['grid gap-3 pb-24 md:pb-4 transition-all duration-200', gridColumnsClass]">
                <div 
                    v-for="menu in filteredMenus" 
                    :key="menu.id" 
                    @click="addToCart(menu)"
                    class="bg-white dark:bg-zinc-900 p-4 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-xs cursor-pointer hover:border-primary active:scale-[0.97] transition-all flex flex-col justify-between min-h-[110px] group relative overflow-hidden"
                >
                    <!-- Aksen Garis Kiri Minimalis -->
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-primary/40 group-hover:bg-primary transition-colors"></div>

                    <!-- Nama Menu (Sangat Jelas & Besar) -->
                    <h3 class="font-black text-base sm:text-lg text-slate-900 dark:text-zinc-50 line-clamp-2 leading-snug group-hover:text-primary transition-colors pl-2">
                        {{ menu.name }}
                    </h3>

                    <!-- Kategori & Harga -->
                    <div class="flex items-end justify-between mt-3 pt-2 border-t border-slate-100 dark:border-zinc-800 pl-2">
                        <span class="text-xs text-slate-400 font-bold truncate max-w-[50%] uppercase tracking-wider">{{ menu.category?.name || 'Umum' }}</span>
                        <span class="font-black text-base sm:text-lg text-primary whitespace-nowrap font-mono">
                            Rp {{ Number(getOfflinePriceObject(menu)?.selling_price || 0).toLocaleString('id-ID') }}
                        </span>
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

    <!-- ========================================================= -->
    <!-- MODALS                                                    -->
    <!-- ========================================================= -->
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

    <OutletSelectModal 
        :is-open="isOutletModalOpen"
        :outlets="outlets || []"
        @select="handleOutletSelect"
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