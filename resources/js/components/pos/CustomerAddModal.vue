<script setup lang="ts">
import { ref } from 'vue';
import { X } from '@lucide/vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', customer: any): void; // Mengembalikan data customer yang baru dibuat
    (e: 'back'): void;
}>();

const name = ref('');
const phone = ref('');
const email = ref('');
const address = ref('');
const isLoading = ref(false);

const handleSave = async () => {
    if (!name.value.trim()) {
        toast.error('Nama pelanggan wajib diisi');
        return;
    }

    try {
        isLoading.value = true;
        // Kirim data ke backend Laravel API
        const response = await axios.post('/api/customers', {
            name: name.value,
            phone: phone.value,
            email: email.value || null,
            shipping_address: address.value // Menyesuaikan dengan fillable model Customer
        });

        toast.success('Pelanggan baru berhasil ditambahkan');
        
        // Kirim event 'saved' beserta data customer baru, lalu tutup/kembali
        emit('saved', response.data.data);
        emit('close');
        
        // Reset form
        name.value = '';
        phone.value = '';
        email.value = '';
        address.value = '';
    } catch (error: any) {
        console.error('Gagal menyimpan customer:', error);
        const errorMsg = error.response?.data?.message || 'Gagal menyimpan data pelanggan';
        toast.error(errorMsg);
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col">
            <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-800/50">
                <h4 class="font-black text-base text-slate-900 dark:text-zinc-100">Tambah Pelanggan Baru</h4>
                <button @click="emit('close')" class="p-1 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
            </div>
            
            <div class="p-6 space-y-4 text-xs">
                <div class="space-y-1">
                    <label class="font-bold text-emerald-600 dark:text-emerald-400">Nama Pelanggan *</label>
                    <input v-model="name" type="text" placeholder="Masukkan nama..." class="w-full px-3.5 py-2.5 border border-emerald-500 dark:bg-zinc-800 dark:text-white rounded-xl focus:outline-none" />
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-500 dark:text-zinc-400">Nomor Hp/Whatsapp</label>
                    <input v-model="phone" type="text" placeholder="08xxxxxxxxxx" class="w-full px-3.5 py-2.5 border border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-xl focus:outline-none" />
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-500 dark:text-zinc-400">Email</label>
                    <input v-model="email" type="email" placeholder="email@domain.com" class="w-full px-3.5 py-2.5 border border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-xl focus:outline-none" />
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-500 dark:text-zinc-400">Alamat</label>
                    <textarea v-model="address" rows="2" placeholder="Alamat lengkap..." class="w-full px-3.5 py-2.5 border border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-xl focus:outline-none"></textarea>
                </div>
            </div>

            <div class="p-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 flex items-center justify-between">
                <button @click="emit('back')" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer">Lihat Daftar</button>
                <button 
                    @click="handleSave" 
                    :disabled="isLoading"
                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-2xl text-xs shadow-md disabled:opacity-50 cursor-pointer transition-all"
                >
                    {{ isLoading ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </div>
        </div>
    </div>
</template>