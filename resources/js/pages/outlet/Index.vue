<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { useOutlet } from '@/composables/useOutlet';
import { useSwal } from '@/composables/useSwal';
import OutletModal from './OutletModal.vue';

// 1. Ekstrak 'meta' (dan isLoading jika ada) dari composable useOutlet
const { outlets: globalOutlets, meta, fetchOutlets } = useOutlet();
const { success, error } = useSwal();

// State Pencarian (dibuat ref biasa agar reaktif)
const search = ref('');

// State Modal
const isOpenModal = ref(false);
const activeOutlet = ref<any>(null);

// 2. Buat loadData untuk menangani fetch dengan parameter halaman (dan pencarian jika via API)
const loadData = async (page = 1) => {
    const params = {
        page,
        // Jika backend Anda mendukung pencarian via parameter, aktifkan baris di bawah:
        // search: search.value
    };
    await fetchOutlets(params);
};

// 3. Fungsi untuk tombol navigasi halaman (Next/Prev)
const changePage = (page: number) => {
    // Pastikan halaman valid berdasarkan meta pagination dari Laravel
    if (page >= 1 && (!meta?.value || page <= meta.value.last_page)) {
        loadData(page);
    }
};

onMounted(() => {
    loadData(1);
});

// 4. Computed untuk menampilkan data secara aman (termasuk filter pencarian lokal)
const displayOutlets = computed(() => {
    let result = globalOutlets.value || [];

    // Filter lokal (Frontend) jika pencarian tidak dilempar ke backend
    if (search.value.trim()) {
        const query = search.value.toLowerCase().trim();
        result = result.filter((outlet: any) =>
            (outlet.name && outlet.name.toLowerCase().includes(query)) ||
            (outlet.code && outlet.code.toLowerCase().includes(query)) ||
            (outlet.phone && outlet.phone.toLowerCase().includes(query))
        );
    }

    return result;
});

const openCreateModal = () => {
    activeOutlet.value = null;
    isOpenModal.value = true;
};

const openEditModal = (outlet: any) => {
    activeOutlet.value = outlet;
    isOpenModal.value = true;
};

const handleSaved = (type: 'simpan' | 'ubah') => {
    isOpenModal.value = false;

    // Refresh data dan kembalikan ke halaman pertama setelah penambahan/perubahan
    loadData(1);

    if (type === 'ubah') {
        success('Berhasil', 'Data outlet berhasil diubah!');
    } else {
        success('Berhasil', 'Data outlet berhasil disimpan!');
    }
};

const deleteOutlet = (outlet: any) => {
    if (confirm(`Apakah Anda yakin ingin menghapus outlet "${outlet.name}"?`)) {
        router.delete(`/api/outlets/${outlet.id}`, {
            onSuccess: () => {
                loadData(); // Muat ulang data saat ini setelah dihapus
                success('Berhasil', 'Outlet berhasil dihapus.');
            },
            onError: () => {
                error('Gagal', 'Terjadi kesalahan saat menghapus outlet.');
            }
        });
    }
};
</script>

<template>
    <div class="">
        <div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Header Section -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground">Daftar Outlet</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Kelola cabang outlet, kode unik, dan status operasional secara terpusat.
                    </p>
                </div>

                <Button
                    size="sm"
                    class="h-8 px-4 rounded-lg text-xs font-medium bg-foreground text-background hover:opacity-90 transition-all shadow-none"
                    @click="openCreateModal"
                >
                    + Outlet Baru
                </Button>
            </div>

            <!-- Toolbar / Search -->
            <div class="flex items-center justify-between gap-2.5 py-1">
                <div class="w-full sm:w-72 relative">
                    <Input
                        v-model="search"
                        placeholder="Cari nama, kode, atau telepon..."
                        class="w-full text-xs h-8 pl-3 pr-8 bg-secondary/40 border-border/60 rounded-lg font-medium focus:ring-1 focus:ring-ring shadow-none"
                    />
                    <button
                        v-if="search"
                        @click="search = ''"
                        class="absolute right-2.5 top-2 text-muted-foreground hover:text-foreground text-xs font-semibold"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-card border border-border/60 rounded-2xl overflow-hidden shadow-xs backdrop-blur-md">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border/60 bg-secondary/30 text-muted-foreground text-[11px] font-semibold uppercase tracking-wider">
                                <th class="px-4 py-3">Nama Outlet</th>
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Telepon</th>
                                <th class="px-4 py-3">Alamat</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40 text-xs font-medium">
                            <!-- PERBAIKAN: Gunakan displayOutlets hasil computed -->
                            <tr v-for="outlet in displayOutlets" :key="outlet.id" class="hover:bg-secondary/20 transition-colors group">
                                <td class="px-4 py-3 font-semibold text-foreground">{{ outlet.name }}</td>
                                <td class="px-4 py-3 font-mono text-muted-foreground">{{ outlet.code }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ outlet.phone || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground max-w-xs truncate">{{ outlet.address || '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <span :class="outlet.is_active ? 'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.4)]' : 'bg-muted-foreground/40'" class="w-1.5 h-1.5 rounded-full"></span>
                                        <span :class="outlet.is_active ? 'text-foreground' : 'text-muted-foreground'">
                                            {{ outlet.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        <!-- Edit Button -->
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 p-0 text-muted-foreground hover:text-foreground rounded-[6px]"
                                            title="Edit"
                                            @click="openEditModal(outlet)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                        </Button>
                                        <!-- Delete Button -->
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 p-0 text-destructive/70 hover:text-destructive rounded-[6px]"
                                            title="Hapus"
                                            @click="deleteOutlet(outlet)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <!-- PERBAIKAN: Kondisi v-if sekarang jauh lebih bersih dan aman -->
                            <tr v-if="displayOutlets.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                    Belum ada data outlet yang tersedia.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <OutletModal
                :show="isOpenModal"
                :outlet="activeOutlet"
                @close="isOpenModal = false"
                @saved="handleSaved"
            />
        </div>
    </div>
</template>
