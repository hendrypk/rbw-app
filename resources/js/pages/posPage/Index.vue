<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PosLayout from '@/layouts/PosLayout.vue';
import { Plus, Minus, Trash2, Tag, Percent, Receipt, FileText, X } from '@lucide/vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

defineOptions({
    layout: PosLayout
});

// ==========================================
// STATE MANAGEMENT
// ==========================================
const menus = ref<any[]>([]);
const categories = ref<any[]>([]);
const isLoading = ref<boolean>(true);
const searchQuery = ref<string>('');
const selectedCategory = ref<string>('all'); // Default: semua kategori

// State Form Tambahan di Cart
const orderNote = ref<string>('');
const discountInput = ref<number>(0);
const transactionFee = ref<number>(0);
const paymentMethod = ref<string>('cash');
const customerName = ref<string>('');

// State Keranjang Belanja (Cart)
const cart = ref<any[]>([]);
const isPaymentModalOpen = ref(false);
const openPaymentModal = () => {
    amountPaidInput.value = finalTotal.value;
    isPaymentModalOpen.value = true;
};
const closePaymentModal = () => {
    isPaymentModalOpen.value = false;
};

// ==========================================
// FETCH DATA
// ==========================================
const fetchData = async () => {
    try {
        isLoading.value = true;
        // Ambil data menu
        const menuResponse = await axios.get('/api/menus');
        menus.value = menuResponse.data.data || menuResponse.data;

        // Ekstrak kategori unik dari data menu untuk dijadikan filter tabs
        const uniqueCategories = new Map();
        menus.value.forEach(menu => {
            if (menu.category) {
                uniqueCategories.set(menu.category.id, menu.category.name);
            }
        });
        
        categories.value = Array.from(uniqueCategories, ([id, name]) => ({ id, name }));
    } catch (error) {
        console.error('Gagal memuat data POS:', error);
        toast.error('Gagal mengambil data dari server');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchData();
});

// ==========================================
// LOGIKAFILTER & SEARCH
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
// LOGIKA CART (KERANJANG)
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

// ==========================================
// PERHITUNGAN KEUANGAN (COMPUTED)
// ==========================================
const amountPaidInput = ref<number>(0);
const cartSubtotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.subtotal, 0);
});

const taxAmount = computed(() => {
    return cartSubtotal.value * 0.11; // PPN 11%
});

const finalTotal = computed(() => {
    const total = (cartSubtotal.value + taxAmount.value + Number(transactionFee.value)) - Number(discountInput.value);
    return total < 0 ? 0 : total;
});

// Helper mendapatkan inisial nama
const getInitials = (name: string) => {
    return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
};

// ==========================================
// PROSES CHECKOUT TO BACKEND
// ==========================================
const submitCheckout = async (type: 'save' | 'pay') => {
    if (cart.value.length === 0) return;

    try {
        const payload = {
            customer_name: customerName.value,
            payment_method: paymentMethod.value,
            discount: discountInput.value,
            transaction_fee: transactionFee.value,
            notes: orderNote.value,
            items: cart.value.map(item => ({ 
                menu_id: item.menu_id, 
                quantity: item.quantity 
            })),
            action_type: type,
            amount_paid: type === 'pay' ? amountPaidInput.value : 0
        };

        const response = await axios.post('/api/pos/checkout', payload);
        toast.success(`Transaksi ${response.data.data.order_number} berhasil diproses!`);

        // ==========================================
        // AUTO CLOSE MODAL JIKA BERHASIL BAYAR/SIMPAN
        // ==========================================
        isPaymentModalOpen.value = false;

        // Reset semua state keranjang belanjaan
        cart.value = [];
        orderNote.value = '';
        discountInput.value = 0;
        transactionFee.value = 0;
        customerName.value = '';
        amountPaidInput.value = 0;

    } catch (error: any) {
        const errMsg = error.response?.data?.message || 'Gagal memproses transaksi';
        toast.error(errMsg);
    }
};
</script>

<template>
    <Head title="Aplikasi Kasir (POS)" />

    <div class="h-full w-full flex flex-col md:flex-row overflow-hidden">
        
        <!-- ========================================================= -->
        <!-- BAGIAN KIRI: KATALOG PRODUK & FILTER                      -->
        <!-- ========================================================= -->
        <div class="flex-1 h-full overflow-y-auto p-4 space-y-4">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="w-full sm:w-72">
                    <input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Cari menu makanan / minuman..." 
                        class="w-full px-3 py-1.5 text-sm bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-lg focus:outline-none focus:border-primary"
                    />
                </div>
            </div>

            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none border-b border-slate-100 dark:border-zinc-800/60">
                <button 
                    @click="selectedCategory = 'all'"
                    :class="[
                        'px-4 py-1.5 text-xs font-semibold rounded-full whitespace-nowrap border transition-all',
                        selectedCategory === 'all' 
                            ? 'bg-primary border-primary text-primary-foreground shadow-sm' 
                            : 'bg-white dark:bg-zinc-900 border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 hover:border-slate-300'
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
                            : 'bg-white dark:bg-zinc-900 border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 hover:border-slate-300'
                    ]"
                >
                    {{ cat.name }}
                </button>
            </div>

            <div v-if="isLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div v-for="i in 10" :key="i" class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-slate-200 dark:border-zinc-800 animate-pulse h-40"></div>
            </div>

            <div v-else-if="filteredMenus.length === 0" class="text-center py-12 text-sm text-muted-foreground">
                Menu tidak ditemukan.
            </div>

            <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div 
                    v-for="menu in filteredMenus" 
                    :key="menu.id" 
                    @click="addToCart(menu)"
                    class="bg-white dark:bg-zinc-900 p-3.5 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm cursor-pointer hover:border-primary active:scale-[0.97] transition-all flex flex-col justify-between aspect-[3/4] group"
                >
                    <div class="w-full aspect-square bg-slate-100 dark:bg-zinc-800 rounded-xl overflow-hidden relative shrink-0 shadow-inner">
                        <img 
                            v-if="menu.image_path" 
                            :src="menu.image_path.startsWith('http') ? menu.image_path : `/storage/${menu.image_path}`" 
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                            alt="Menu"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center bg-primary/5 text-primary font-black text-xl tracking-wider">
                            {{ getInitials(menu.name) }}
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col justify-between pt-3">
                        <h3 class="font-black text-base text-slate-900 dark:text-zinc-50 line-clamp-2 leading-tight tracking-tight group-hover:text-primary transition-colors">
                            {{ menu.name }}
                        </h3>
                        
                        <div class="flex items-end justify-between mt-2 pt-1 border-t border-slate-50 dark:border-zinc-800/50">
                            <span class="text-xs text-slate-400 font-semibold truncate max-w-[80px]">
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
        <!-- BAGIAN KANAN: PERLEBAR WIDTH CART CONTAINER                -->
        <!-- ========================================================= -->
        <!-- UBAH DI SINI: md:w-[460px] xl:w-[520px] untuk memperlebar area keranjang -->
        <div class="w-full md:w-[460px] xl:w-[520px] h-full bg-white dark:bg-zinc-900 border-t md:border-t-0 md:border-l border-slate-200 dark:border-zinc-800 flex flex-col shrink-0">
            
            <div class="p-4 border-b border-slate-200 dark:border-zinc-800 bg-slate-50/80 dark:bg-zinc-900/50">
                <h3 class="font-bold text-base text-slate-900 dark:text-zinc-50">Detail Transaksi</h3>
                <input 
                    v-model="customerName"
                    type="text" 
                    placeholder="Nama Pelanggan (Opsional)" 
                    class="w-full mt-3 px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none focus:border-primary placeholder:text-slate-400"
                />
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <div v-if="cart.length === 0" class="text-center py-16 text-sm text-slate-400">
                    Keranjang kosong. Klik menu di samping untuk menambahkan.
                </div>
                
                <div v-for="item in cart" :key="item.menu_id" class="flex flex-col gap-2 p-3.5 bg-slate-50 dark:bg-zinc-800/60 rounded-xl border border-slate-100 dark:border-zinc-800">
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
                        <span class="text-xs text-slate-400 font-medium">Atur Jumlah:</span>
                        
                        <div class="flex items-center gap-3 shrink-0">
                            <button 
                                @click="updateQuantity(item.menu_id, -1)" 
                                class="p-1.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30 transition-colors shadow-sm"
                            >
                                <Minus class="h-4 w-4" />
                            </button>
                            <span class="font-black text-base w-6 text-center text-slate-900 dark:text-zinc-100">
                                {{ item.quantity }}
                            </span>
                            <button 
                                @click="updateQuantity(item.menu_id, 1)" 
                                class="p-1.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors shadow-sm"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100 dark:border-zinc-800 space-y-3 bg-slate-50/50">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600 dark:text-zinc-400 flex items-center gap-1.5">
                        <FileText class="h-3.5 w-3.5" /> Catatan Masakan
                    </label>
                    <textarea 
                        v-model="orderNote"
                        rows="2" 
                        placeholder="Contoh: Pedas manis, es dipisah, no bawang..." 
                        class="w-full text-sm p-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none resize-none focus:border-primary text-slate-800 dark:text-zinc-200"
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
                            <Receipt class="h-3.5 w-3.5 text-emerald-600" /> Biaya (Rp)
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

            <div class="p-5 border-t border-slate-200/60 dark:border-zinc-800 space-y-4">
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Subtotal</span>
                        <span class="font-medium text-slate-700 dark:text-zinc-300">Rp {{ cartSubtotal.toLocaleString('id-ID') }}</span>
                    </div>
                    <div v-if="discountInput > 0" class="flex justify-between text-red-500">
                        <span>Diskon</span>
                        <span>-Rp {{ discountInput.toLocaleString('id-ID') }}</span>
                    </div>
                    <div v-if="transactionFee > 0" class="flex justify-between text-slate-500">
                        <span>Biaya Tambahan</span>
                        <span class="font-medium text-slate-700 dark:text-zinc-300">+Rp {{ transactionFee.toLocaleString('id-ID') }}</span>
                    </div>
                    
                    <div class="border-t border-slate-100 dark:border-zinc-800 pt-3 flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-800 dark:text-zinc-200">Total Akhir</span>
                        <span class="text-xl font-bold text-slate-900 dark:text-zinc-50 tracking-tight">
                            Rp {{ finalTotal.toLocaleString('id-ID') }}
                        </span>
                    </div>
                </div>

                
                <div class="grid grid-cols-2 gap-3">
                    <!-- Tombol 1: Simpan (Langsung kirim status Unpaid) -->
                    <button 
                        @click="submitCheckout('save')"
                        :disabled="cart.length === 0"
                        class="bg-white hover:bg-slate-50 dark:bg-zinc-900 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-300 py-3 rounded-xl text-xs font-bold border border-slate-200 dark:border-zinc-700 shadow-sm transition-all disabled:opacity-40"
                    >
                        Simpan (Unpaid)
                    </button>
                    
                    <!-- Tombol 2: Proses (Membuka Dialog Modal Kasir) -->
                    <button 
                        @click="openPaymentModal"
                        :disabled="cart.length === 0"
                        class="bg-slate-900 hover:bg-slate-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white text-white py-3 rounded-xl text-xs font-black shadow-sm transition-all disabled:opacity-40"
                    >
                        Proses Pembayaran
                    </button>
                </div>
            </div>
            
            <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity">
                <div class="bg-white dark:bg-zinc-900 w-full max-w-4xl rounded-2xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150 flex flex-col max-h-screen">
                    
                    <div class="p-4 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/50 shrink-0">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-zinc-100">Penyelesaian Transaksi</h4>
                        <button @click="closePaymentModal" class="p-1 rounded-md text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                            
                            <div class="space-y-4">
                                <div class="space-y-3">
                                    <div class="space-y-1">
                                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nama Pelanggan</span>
                                        <input v-model="customerName" type="text" placeholder="Masukkan nama (opsional)" class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none focus:border-slate-400" />
                                    </div>
                                    <div class="space-y-1">
                                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Catatan Dapur</span>
                                        <textarea v-model="orderNote" rows="3" placeholder="Contoh: jangan pakai daun bawang, sambal dipisah..." class="w-full text-xs p-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg focus:outline-none resize-none focus:border-slate-400"></textarea>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-1">
                                    <div class="space-y-1">
                                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Diskon (Rp)</span>
                                        <input v-model.number="discountInput" type="number" placeholder="0" class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg text-red-500 font-bold focus:outline-none" />
                                    </div>
                                    <div class="space-y-1">
                                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Biaya Lain (Rp)</span>
                                        <input v-model.number="transactionFee" type="number" placeholder="0" class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg text-slate-800 dark:text-zinc-200 font-bold focus:outline-none" />
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4 bg-slate-50/50 dark:bg-zinc-900/30 p-4 rounded-xl border border-slate-100 dark:border-zinc-800/80">
                                <div class="p-3.5 bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 rounded-xl flex items-center justify-between shadow-sm">
                                    <span class="text-xs font-semibold opacity-80">Total Tagihan Bersih:</span>
                                    <span class="text-lg font-black tracking-tight">Rp {{ finalTotal.toLocaleString('id-ID') }}</span>
                                </div>

                                <div class="space-y-2">
                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Metode Pembayaran</span>
                                    <div class="grid grid-cols-3 gap-2">
                                        <button v-for="method in ['cash', 'qris', 'edc']" :key="method" @click="paymentMethod = method" :class="['py-2 text-xs font-bold rounded-lg border uppercase transition-all', paymentMethod === method ? 'bg-slate-900 border-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-white border-slate-200 text-slate-500 dark:bg-zinc-800 dark:border-zinc-700']">
                                            {{ method }}
                                        </button>
                                    </div>
                                </div>

                                <div v-if="paymentMethod === 'cash'" class="p-3 bg-white dark:bg-zinc-800 rounded-xl space-y-3 border border-slate-200/60 dark:border-zinc-700">
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-[11px]">
                                        <button @click="amountPaidInput = finalTotal" class="py-1.5 bg-slate-50 dark:bg-zinc-900 hover:border-slate-400 border border-slate-200/80 dark:border-zinc-700 rounded-md font-semibold text-slate-700 dark:text-zinc-200">Uang Pas</button>
                                        <button @click="amountPaidInput = 25000" class="py-1.5 bg-slate-50 dark:bg-zinc-900 hover:border-slate-400 border border-slate-200/80 dark:border-zinc-700 rounded-md font-semibold text-slate-700 dark:text-zinc-200">25k</button>
                                        <button @click="amountPaidInput = 50000" class="py-1.5 bg-slate-50 dark:bg-zinc-900 hover:border-slate-400 border border-slate-200/80 dark:border-zinc-700 rounded-md font-semibold text-slate-700 dark:text-zinc-200">50k</button>
                                        <button @click="amountPaidInput = 100000" class="py-1.5 bg-slate-50 dark:bg-zinc-900 hover:border-slate-400 border border-slate-200/80 dark:border-zinc-700 rounded-md font-semibold text-slate-700 dark:text-zinc-200">100k</button>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 text-xs pt-1">
                                        <span class="font-medium text-slate-500">Nominal Bayar:</span>
                                        <input v-model.number="amountPaidInput" type="number" class="w-32 text-right font-bold px-2 py-1 bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-md focus:outline-none" />
                                    </div>
                                    <div v-if="amountPaidInput > finalTotal" class="flex justify-between items-center text-xs text-emerald-600 font-bold bg-emerald-50 dark:bg-emerald-950/20 p-2 rounded-md">
                                        <span>Kembalian:</span>
                                        <span>Rp {{ (amountPaidInput - finalTotal).toLocaleString('id-ID') }}</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="p-4 border-t border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/50 grid grid-cols-2 gap-3 shrink-0">
                        <button @click="closePaymentModal" class="py-2.5 bg-white dark:bg-zinc-800 hover:bg-slate-50 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-300 font-semibold rounded-xl text-xs transition-colors">
                            Kembali
                        </button>
                        <button @click="submitCheckout('pay')" class="py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-sm transition-colors">
                            Eksekusi & Cetak
                        </button>
                    </div>

                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
/* Menghilangkan scrollbar bawaan di filter kategori agar rapi */
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>