<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { X, Search, UserPlus, Trash2, Check } from '@lucide/vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

const props = defineProps<{
    isOpen: boolean;
    customerName?: string; // Menerima v-model:customer-name dari Index.vue
}>();

const emit = defineEmits<{
    (e: 'update:customerName', value: string): void;
    (e: 'select', customer: { id: string; name: string }): void; 
    (e: 'close'): void;
    (e: 'open-add'): void;
}>();

const searchQuery = ref('');
const customers = ref<any[]>([]);
const isLoading = ref<boolean>(false);

const fetchCustomers = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/customers');
        customers.value = response.data.data || response.data;
    } catch (error) {
        console.error('Gagal mengambil data pelanggan:', error);
        toast.error('Gagal memuat daftar pelanggan');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchCustomers();
});

const filteredCustomers = computed(() => {
    return customers.value.filter(c => 
        c.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const selectCustomer = (cust: any) => {
    emit('update:customerName', cust.name);
    emit('select', { id: cust.id, name: cust.name });
    emit('close');
};

const clearCustomer = () => {
    emit('update:customerName', '');
    emit('select', { id: '', name: '' }); 
    emit('close');
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col">
            
            <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-800/50">
                <h4 class="font-black text-base text-slate-900 dark:text-zinc-100">Pilih Pelanggan</h4>
                <button @click="emit('close')" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                    <X class="h-4 w-4" />
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div class="relative">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Cari pelanggan di sini..." 
                        class="w-full pl-10 pr-4 py-2.5 text-xs bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-primary text-slate-900 dark:text-white" 
                    />
                </div>

                <div class="max-h-60 overflow-y-auto divide-y divide-slate-100 dark:divide-zinc-800 custom-scrollbar">
                    <div v-if="isLoading" class="text-center py-8 text-xs text-slate-400">Memuat data pelanggan...</div>
                    <div v-else-if="filteredCustomers.length === 0" class="text-center py-8 text-xs text-slate-400">Pelanggan tidak ditemukan.</div>

                    <!-- Pengecekan selected menggunakan props customerName -->

                    <div 
                        v-for="cust in filteredCustomers" 
                        :key="cust.id" 
                        @click="selectCustomer(cust)" 
                        :class="[
                            'py-3 flex items-center justify-between cursor-pointer px-3 rounded-xl transition-colors',
                            customerName === cust.name 
                                ? 'bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900' 
                                : 'hover:bg-slate-50 dark:hover:bg-zinc-800/50'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-xs shrink-0">
                                {{ cust.name.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <h5 class="font-bold text-xs text-slate-800 dark:text-zinc-200">{{ cust.name }}</h5>
                                <span class="text-[10px] text-slate-400 font-mono">{{ cust.phone || cust.whatsapp || '-' }}</span>
                            </div>
                        </div>

                        <!-- Indikator Centang Hijau -->
                        <span v-if="customerName === cust.name" class="flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                            <Check class="w-4 h-4" /> Terpilih
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 flex items-center justify-between">
                <button @click="clearCustomer" class="text-xs font-bold text-red-500 hover:underline flex items-center gap-1 cursor-pointer">
                    <Trash2 class="h-3.5 w-3.5" /> Hapus Pelanggan
                </button>
                <button @click="emit('open-add')" class="px-5 py-2.5 bg-emerald-600 text-white font-black rounded-2xl text-xs shadow-md flex items-center gap-2 cursor-pointer hover:bg-emerald-500 transition-all">
                    <UserPlus class="h-4 w-4" /> Buat Baru
                </button>
            </div>
        </div>
    </div>
</template>