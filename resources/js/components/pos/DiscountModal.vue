<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { X, Tag, Check, Trash2, Plus, ArrowLeft } from '@lucide/vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

const props = defineProps<{
    isOpen: boolean;
    currentDiscount: number;
    vouchers: any[];
    appliedVoucher: any;
    isLoadingVouchers: boolean;
    fetchVouchers: () => void;
    validateAndApplyVoucher: (code: string, items: any[]) => Promise<boolean>;
    removeVoucher: () => void;
    getCartValidationItems: () => any[];
    menus: any[]; // Daftar menu untuk pilihan voucher spesifik
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'applyManual', amount: number): void;
}>();

const mode = ref<'voucher' | 'manual'>('voucher');
const manualAmount = ref(props.currentDiscount || 0);
const codeInput = ref('');

// State untuk Modal Tambah Voucher Baru
const isAddVoucherModalOpen = ref(false);
const newVoucher = ref({
    code: '',
    name: '',
    type: 'fixed' as 'fixed' | 'percentage',
    value: 0,
    min_spend: 0,
    max_discount: null as number | null,
    usage_limit: null as number | null,
    started_at: '',
    expired_at: '',
    menu_ids: [] as string[],
    is_active: true
});
const isSubmittingNew = ref(false);
const isDeleteModalOpen = ref(false);
const voucherToDeleteId = ref<number | string | null>(null);
const isDeleting = ref(false);

onMounted(() => {
    props.fetchVouchers();
});

const handleApplyManual = () => {
    props.removeVoucher();
    emit('applyManual', Number(manualAmount.value));
    emit('close');
};

const handleSelectVoucher = async (code: string) => {
    const success = await props.validateAndApplyVoucher(code, props.getCartValidationItems());
    if (success) {
        emit('close');
    }
};

const handleApplyInputCode = async () => {
    if (!codeInput.value) return;
    const success = await props.validateAndApplyVoucher(codeInput.value, props.getCartValidationItems());
    if (success) {
        emit('close');
    }
};

const confirmDeleteVoucher = (id: number | string) => {
    voucherToDeleteId.value = id;
    isDeleteModalOpen.value = true;
};

// Fungsi Menyimpan Voucher Baru ke Backend
const handleCreateVoucher = async () => {
    if (!newVoucher.value.code || !newVoucher.value.name || newVoucher.value.value <= 0) {
        toast.error('Mohon lengkapi data voucher dengan benar.');
        return;
    }

    isSubmittingNew.value = true;
    try {
        const payload = {
            ...newVoucher.value,
            code: newVoucher.value.code.toUpperCase(),
            started_at: newVoucher.value.started_at || null,
            expired_at: newVoucher.value.expired_at || null,
            max_discount: newVoucher.value.type === 'percentage' ? newVoucher.value.max_discount : null,
            menu_ids: newVoucher.value.menu_ids.length > 0 ? newVoucher.value.menu_ids : null,
        };

        const res = await axios.post('/api/vouchers', payload);
        toast.success(res.data.message || 'Voucher baru berhasil dibuat!');
        
        // Refresh daftar voucher & tutup form tambah
        props.fetchVouchers();
        isAddVoucherModalOpen.value = false;
        
        // Reset form
        newVoucher.value = {
            code: '', name: '', type: 'fixed', value: 0, min_spend: 0,
            max_discount: null, usage_limit: null, started_at: '', expired_at: '',
            menu_ids: [], is_active: true
        };
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Gagal membuat voucher baru');
    } finally {
        isSubmittingNew.value = false;
    }
};

const handleDeleteVoucher = async () => {
    if (!voucherToDeleteId.value) return;

    isDeleting.value = true;
    try {
        const res = await axios.delete(`/api/vouchers/${voucherToDeleteId.value}`);
        
        if (res.data && res.data.success) {
            toast.success(res.data.message || 'Voucher berhasil dihapus.');
            props.fetchVouchers();
            isDeleteModalOpen.value = false;
            voucherToDeleteId.value = null;
        }
    } catch (err: any) {
        console.error('Gagal menghapus voucher:', err);
        toast.error(err.response?.data?.message || 'Terjadi kesalahan saat menghapus voucher.');
    } finally {
        isDeleting.value = false;
    }
};
</script>

<template>
    <!-- MODAL UTAMA DISKON & VOUCHER -->
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
            
            <!-- Header -->
            <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-800/50">
                <h4 class="font-black text-base text-slate-900 dark:text-zinc-100 flex items-center gap-2">
                    <Tag class="w-5 h-5 text-emerald-600" /> Diskon & Voucher Promo
                </h4>
                <button @click="emit('close')" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                    <X class="h-4 w-4" />
                </button>
            </div>
            
            <!-- Tab Switcher & Tombol Tambah Voucher -->
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900 px-5 pt-3">
                <div class="flex gap-3">
                    <button 
                        @click="mode = 'voucher'" 
                        :class="['pb-2.5 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer', mode === 'voucher' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-400']"
                    >
                        Pilih Voucher / Promo
                    </button>
                    <button 
                        @click="mode = 'manual'" 
                        :class="['pb-2.5 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer', mode === 'manual' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-400']"
                    >
                        Diskon Manual
                    </button>
                </div>

                <!-- Tombol Buka Modal Tambah Voucher -->
                <button 
                    v-if="mode === 'voucher'"
                    @click="isAddVoucherModalOpen = true"
                    class="mb-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all shadow-xs cursor-pointer"
                >
                    <Plus class="w-3.5 h-3.5" /> Buat Voucher
                </button>
            </div>

            <!-- Content Area -->
            <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1 custom-scrollbar">
                
                <!-- Status Voucher Aktif -->
                <div v-if="appliedVoucher" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 rounded-2xl flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block">Voucher Terpakai</span>
                        <h5 class="font-black text-sm text-emerald-900 dark:text-emerald-200">{{ appliedVoucher.name }} ({{ appliedVoucher.code }})</h5>
                        <span class="text-xs text-emerald-700 dark:text-emerald-400 font-mono">Potongan: Rp {{ appliedVoucher.discount_amount.toLocaleString('id-ID') }}</span>
                    </div>
                    <button @click="removeVoucher" class="p-2 bg-red-100 hover:bg-red-200 text-red-600 rounded-xl cursor-pointer" title="Lepas Voucher">
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>

                <!-- MODE 1: VOUCHER / PROMO -->
                <div v-if="mode === 'voucher'" class="space-y-4">
                    <div class="flex gap-2">
                        <input 
                            v-model="codeInput" 
                            type="text" 
                            placeholder="Ketik kode voucher (misal: PROMO50)..." 
                            class="flex-1 px-3.5 py-2.5 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold uppercase text-slate-900 dark:text-white" 
                        />
                        <button @click="handleApplyInputCode" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl cursor-pointer">
                            Terapkan
                        </button>
                    </div>

                    <div class="space-y-2 pt-2">
                        <span class="font-bold text-slate-400 uppercase tracking-wider text-[10px]">Daftar Voucher Tersedia</span>
                        
                        <div v-if="isLoadingVouchers" class="text-center py-8 text-slate-400">Memuat daftar voucher...</div>
                        <div v-else-if="vouchers.length === 0" class="text-center py-8 text-slate-400">Tidak ada voucher tersedia. Buat voucher baru melalui tombol di atas.</div>

                        <div 
                            v-for="v in vouchers" 
                            :key="v.id"
                            class="p-3.5 rounded-2xl border border-slate-200 dark:border-zinc-800 hover:border-emerald-500 bg-slate-50/50 dark:bg-zinc-800/40 transition-all flex items-center justify-between gap-3"
                        >
                            <div @click="handleSelectVoucher(v.code)" class="cursor-pointer flex-1">
                                <h5 class="font-black text-slate-900 dark:text-zinc-100">{{ v.name }} <span class="text-xs font-mono text-emerald-600">({{ v.code }})</span></h5>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Potongan: <span class="font-bold text-slate-700 dark:text-zinc-300">{{ v.type === 'fixed' ? 'Rp ' + Number(v.value).toLocaleString('id-ID') : v.value + '%' }}</span>
                                    <span v-if="v.min_spend > 0"> • Min. belanja Rp {{ Number(v.min_spend).toLocaleString('id-ID') }}</span>
                                    <span v-if="!v.started_at && !v.expired_at" class="text-emerald-600 font-semibold"> • Permanent</span>
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <span @click="handleSelectVoucher(v.code)" class="px-3 py-1.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-black rounded-xl text-[11px] cursor-pointer">
                                    Pilih
                                </span>
                                <button 
                                    @click.stop="confirmDeleteVoucher(v.id)" 
                                    class="p-2 bg-red-100 dark:bg-red-950/50 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900 rounded-xl transition cursor-pointer"
                                    title="Hapus Voucher"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                            
                        </div>
                    </div>
                </div>

                <!-- MODE 2: DISKON MANUAL -->
                <div v-if="mode === 'manual'" class="space-y-4 pt-2">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Nominal Potongan Diskon (Rp)</label>
                        <input 
                            v-model.number="manualAmount" 
                            type="number" 
                            placeholder="Contoh: 5000" 
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold font-mono text-sm text-slate-900 dark:text-white" 
                        />
                    </div>
                    <button @click="handleApplyManual" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl shadow-md cursor-pointer">
                        Terapkan Diskon Manual
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- SUB-MODAL: TAMBAH VOUCHER BARU -->
    <div v-if="isAddVoucherModalOpen" class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-lg rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
            
            <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-800/50">
                <div class="flex items-center gap-2">
                    <button @click="isAddVoucherModalOpen = false" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                        <ArrowLeft class="w-4 h-4" />
                    </button>
                    <h4 class="font-black text-base text-slate-900 dark:text-zinc-100">Buat Voucher / Promo Baru</h4>
                </div>
                <button @click="isAddVoucherModalOpen = false" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1 custom-scrollbar">
                <!-- Kode & Nama -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Kode Voucher *</label>
                        <input v-model="newVoucher.code" type="text" placeholder="MISAL: DISKON10" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold uppercase text-slate-900 dark:text-white" required />
                    </div>
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Nama Promo *</label>
                        <input v-model="newVoucher.name" type="text" placeholder="Diskon Spesial" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white" required />
                    </div>
                </div>

                <!-- Tipe & Nilai Potongan -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Tipe Diskon</label>
                        <select v-model="newVoucher.type" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white font-bold">
                            <option value="fixed">Nominal Tetap (Rp)</option>
                            <option value="percentage">Persentase (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Nilai Potongan *</label>
                        <input v-model.number="newVoucher.value" type="number" placeholder="5000 atau 10" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold font-mono text-slate-900 dark:text-white" required />
                    </div>
                </div>

                <!-- Min Spend & Max Discount (jika persen) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Minimum Belanja (Rp)</label>
                        <input v-model.number="newVoucher.min_spend" type="number" placeholder="0" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-mono text-slate-900 dark:text-white" />
                    </div>
                    <div v-if="newVoucher.type === 'percentage'">
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Maksimal Diskon (Rp)</label>
                        <input v-model.number="newVoucher.max_discount" type="number" placeholder="Batas max potong" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-mono text-slate-900 dark:text-white" />
                    </div>
                </div>

                <!-- Waktu Mulai & Berakhir (Permanent jika kosong) -->
                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Mulai Berlaku</label>
                        <input v-model="newVoucher.started_at" type="datetime-local" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-[11px] text-slate-900 dark:text-white" />
                        <span class="text-[10px] text-slate-400">Kosongkan jika langsung</span>
                    </div>
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Kedaluwarsa</label>
                        <input v-model="newVoucher.expired_at" type="datetime-local" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-[11px] text-slate-900 dark:text-white" />
                        <span class="text-[10px] text-slate-400">Kosongkan jika permanent</span>
                    </div>
                </div>

                <!-- Pilihan Menu Spesifik (Opsional) -->
                <div class="pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Berlaku Untuk Menu Tertentu (Opsional)</label>
                    <p class="text-[10px] text-slate-400 mb-2">Jika dikosongkan, voucher berlaku global untuk semua menu.</p>
                    
                    <div class="max-h-32 overflow-y-auto space-y-1 p-2 bg-slate-50 dark:bg-zinc-800/50 rounded-xl border border-slate-200 dark:border-zinc-700 custom-scrollbar">
                        <label v-for="menu in menus" :key="menu.id" class="flex items-center gap-2 cursor-pointer py-1 px-1 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded">
                            <input type="checkbox" :value="menu.id" v-model="newVoucher.menu_ids" class="accent-emerald-600 rounded" />
                            <span class="text-slate-800 dark:text-zinc-200">{{ menu.name }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="p-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 flex justify-end gap-2">
                <button @click="isAddVoucherModalOpen = false" class="px-4 py-2 border border-slate-200 dark:border-zinc-700 font-bold rounded-xl text-slate-600 dark:text-zinc-300 cursor-pointer">
                    Batal
                </button>
                <button @click="handleCreateVoucher" :disabled="isSubmittingNew" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl shadow-md cursor-pointer disabled:opacity-50">
                    {{ isSubmittingNew ? 'Menyimpan...' : 'Simpan Voucher' }}
                </button>
            </div>
        </div>
    </div>
    <div v-if="isAddVoucherModalOpen" class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-lg rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
            
            <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-800/50">
                <div class="flex items-center gap-2">
                    <button @click="isAddVoucherModalOpen = false" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                        <ArrowLeft class="w-4 h-4" />
                    </button>
                    <h4 class="font-black text-base text-slate-900 dark:text-zinc-100">Buat Voucher / Promo Baru</h4>
                </div>
                <button @click="isAddVoucherModalOpen = false" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1 custom-scrollbar">
                <!-- Kode & Nama -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Kode Voucher *</label>
                        <input v-model="newVoucher.code" type="text" placeholder="MISAL: DISKON10" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold uppercase text-slate-900 dark:text-white" required />
                    </div>
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Nama Promo *</label>
                        <input v-model="newVoucher.name" type="text" placeholder="Diskon Spesial" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white" required />
                    </div>
                </div>

                <!-- Tipe & Nilai Potongan -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Tipe Diskon</label>
                        <select v-model="newVoucher.type" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white font-bold">
                            <option value="fixed">Nominal Tetap (Rp)</option>
                            <option value="percentage">Persentase (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Nilai Potongan *</label>
                        <input v-model.number="newVoucher.value" type="number" placeholder="5000 atau 10" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-bold font-mono text-slate-900 dark:text-white" required />
                    </div>
                </div>

                <!-- Min Spend & Max Discount (jika persen) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Minimum Belanja (Rp)</label>
                        <input v-model.number="newVoucher.min_spend" type="number" placeholder="0" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-mono text-slate-900 dark:text-white" />
                    </div>
                    <div v-if="newVoucher.type === 'percentage'">
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Maksimal Diskon (Rp)</label>
                        <input v-model.number="newVoucher.max_discount" type="number" placeholder="Batas max potong" class="w-full px-3 py-2 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl font-mono text-slate-900 dark:text-white" />
                    </div>
                </div>

                <!-- Waktu Mulai & Berakhir (Permanent jika kosong) -->
                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Mulai Berlaku</label>
                        <input v-model="newVoucher.started_at" type="datetime-local" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-[11px] text-slate-900 dark:text-white" />
                        <span class="text-[10px] text-slate-400">Kosongkan jika langsung</span>
                    </div>
                    <div>
                        <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Kedaluwarsa</label>
                        <input v-model="newVoucher.expired_at" type="datetime-local" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-[11px] text-slate-900 dark:text-white" />
                        <span class="text-[10px] text-slate-400">Kosongkan jika permanent</span>
                    </div>
                </div>

                <!-- Pilihan Menu Spesifik (Opsional) -->
                <div class="pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <label class="font-bold block mb-1 text-slate-700 dark:text-zinc-300">Berlaku Untuk Menu Tertentu (Opsional)</label>
                    <p class="text-[10px] text-slate-400 mb-2">Jika dikosongkan, voucher berlaku global untuk semua menu.</p>
                    
                    <div class="max-h-32 overflow-y-auto space-y-1 p-2 bg-slate-50 dark:bg-zinc-800/50 rounded-xl border border-slate-200 dark:border-zinc-700 custom-scrollbar">
                        <label v-for="menu in menus" :key="menu.id" class="flex items-center gap-2 cursor-pointer py-1 px-1 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded">
                            <input type="checkbox" :value="menu.id" v-model="newVoucher.menu_ids" class="accent-emerald-600 rounded" />
                            <span class="text-slate-800 dark:text-zinc-200">{{ menu.name }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="p-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 flex justify-end gap-2">
                <button @click="isAddVoucherModalOpen = false" class="px-4 py-2 border border-slate-200 dark:border-zinc-700 font-bold rounded-xl text-slate-600 dark:text-zinc-300 cursor-pointer">
                    Batal
                </button>
                <button @click="handleCreateVoucher" :disabled="isSubmittingNew" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl shadow-md cursor-pointer disabled:opacity-50">
                    {{ isSubmittingNew ? 'Menyimpan...' : 'Simpan Voucher' }}
                </button>
            </div>
        </div>
    </div>

    <!-- SUB-MODAL: KONFIRMASI HAPUS VOUCHER -->
    <div v-if="isDeleteModalOpen" class="fixed inset-0 z-70 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-sm rounded-3xl border border-slate-100 dark:border-zinc-800 p-6 shadow-2xl text-center">
            
            <div class="w-12 h-12 bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <h3 class="text-lg font-black text-slate-900 dark:text-zinc-100 mb-1">
                Hapus Voucher?
            </h3>
            
            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6 leading-relaxed">
                Tindakan ini tidak dapat dibatalkan. Voucher akan dihapus secara permanen dari sistem.
            </p>

            <div class="flex items-center gap-3">
                <button 
                    type="button" 
                    @click="isDeleteModalOpen = false" 
                    :disabled="isDeleting"
                    class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 transition active:scale-[0.98] cursor-pointer disabled:opacity-50"
                >
                    Batal
                </button>

                <button 
                    type="button" 
                    @click="handleDeleteVoucher" 
                    :disabled="isDeleting"
                    class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-white bg-red-600 hover:bg-red-700 transition active:scale-[0.98] shadow-md shadow-red-600/20 cursor-pointer disabled:opacity-50"
                >
                    {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}
                </button>
            </div>

        </div>
        </div>
</template>