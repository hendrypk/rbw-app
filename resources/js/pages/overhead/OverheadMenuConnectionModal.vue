<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { useSwal } from '@/composables/useSwal';
import Modal from '@/components/ui/Modal.vue'; // Sesuaikan path
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import { Link as LinkIcon, Search } from '@lucide/vue'; // Menggunakan lucide-vue-next

interface Menu {
    id: string;
    name: string;
    code: string | null;
}

const props = defineProps<{
    show: boolean;
    overhead: any | null;
    outletId: string;
}>();

const emit = defineEmits(['close', 'saved']);
const { success, error: swalError } = useSwal();

const processing = ref(false);
const isLoadingMenus = ref(false);
const searchQuery = ref('');

const menus = ref<Menu[]>([]);
const selectedMenuIds = ref<string[]>([]);

// Ambil daftar menu saat modal dibuka
watch(() => props.show, async (newVal) => {
    if (newVal && props.overhead) {
        searchQuery.value = '';
        selectedMenuIds.value = props.overhead.menus?.map((m: any) => m.id) || [];
        await fetchMenus();
    }
});

const fetchMenus = async () => {
    isLoadingMenus.value = true;
    try {
        // Asumsi Anda memiliki endpoint ini untuk mengambil menu berdasarkan outlet
        const response = await axios.get('/api/menus', {
            params: { outlet_id: props.outletId, is_active: 1, limit: 100 }
        });
        menus.value = response.data.data || response.data;
    } catch (error) {
        console.error("Gagal memuat menu", error);
    } finally {
        isLoadingMenus.value = false;
    }
};

const toggleSelectAll = () => {
    if (selectedMenuIds.value.length === menus.value.length) {
        selectedMenuIds.value = []; // Deselect all
    } else {
        selectedMenuIds.value = menus.value.map(m => m.id); // Select all
    }
};

const submit = async () => {
    processing.value = true;
    try {
        // Asumsi Anda akan membuat endpoint POST/PUT untuk sync menu ke overhead
        await axios.post(`/api/overhead-costs/${props.overhead.id}/sync-menus`, {
            menu_ids: selectedMenuIds.value
        });

        success('Berhasil', 'Menu berhasil dihubungkan ke overhead cost.');
        emit('saved');
        emit('close');
    } catch (err: any) {
        swalError('Gagal', err.response?.data?.message || 'Gagal menghubungkan menu.');
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Modal :show="show" title="Hubungkan ke Menu" maxWidth="max-w-md" @close="$emit('close')">
        <template #icon>
            <LinkIcon class="w-5 h-5 text-primary" />
        </template>

        <div class="space-y-4">
            <div v-if="overhead" class="text-xs text-muted-foreground bg-slate-50 dark:bg-zinc-900 p-3 rounded-xl border border-slate-100 dark:border-zinc-800">
                Pilih menu apa saja yang akan dibebankan biaya
                <span class="font-bold text-foreground">{{ overhead.name }}</span>
                sebesar <span class="font-bold text-red-500">Rp {{ Number(overhead.amount).toLocaleString() }}</span>.
            </div>

            <!-- Fitur Pencarian & Pilih Semua -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-2.5 w-4 h-4 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Cari nama menu..." class="pl-9 h-9 rounded-xl text-xs" />
                </div>
                <Button variant="outline" size="sm" class="h-9 rounded-xl text-xs font-medium" @click="toggleSelectAll">
                    {{ selectedMenuIds.length === menus.length && menus.length > 0 ? 'Hapus Semua' : 'Pilih Semua' }}
                </Button>
            </div>

            <!-- List Menu -->
            <div class="border border-border/60 rounded-xl overflow-hidden flex flex-col h-[300px]">
                <div v-if="isLoadingMenus" class="flex-1 flex items-center justify-center text-xs text-muted-foreground">
                    Memuat daftar menu...
                </div>

                <div v-else-if="menus.length === 0" class="flex-1 flex items-center justify-center text-xs text-muted-foreground">
                    Tidak ada menu ditemukan.
                </div>

                <ul v-else class="flex-1 overflow-y-auto divide-y divide-border/60 text-xs">
                    <li v-for="menu in menus.filter(m => m.name.toLowerCase().includes(searchQuery.toLowerCase()))" :key="menu.id">
                        <label class="flex items-center gap-3 px-4 py-3 hover:bg-muted/30 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                :value="menu.id"
                                v-model="selectedMenuIds"
                                class="rounded border-slate-300 text-primary focus:ring-primary cursor-pointer w-4 h-4"
                            />
                            <div class="flex-1">
                                <p class="font-medium text-foreground">{{ menu.name }}</p>
                            </div>
                            <span v-if="menu.code" class="text-muted-foreground font-mono bg-secondary px-1.5 py-0.5 rounded text-[10px]">{{ menu.code }}</span>
                        </label>
                    </li>
                </ul>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-zinc-800 mt-2">
                <span class="text-xs font-semibold text-muted-foreground">
                    {{ selectedMenuIds.length }} Menu Terpilih
                </span>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-9 px-4 rounded-xl text-xs font-semibold" @click="$emit('close')">Batal</Button>
                    <Button type="button" size="sm" class="h-9 px-5 rounded-xl text-xs font-bold shadow-sm" :disabled="processing" @click="submit">
                        {{ processing ? 'Menyimpan...' : 'Simpan Relasi' }}
                    </Button>
                </div>
            </div>
        </div>
    </Modal>
</template>
