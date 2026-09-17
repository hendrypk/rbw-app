<script setup lang="ts">
import { ref } from 'vue';
import { useJournal } from '@/composables/useJournal';
import { Plus, ChevronLeft, ChevronRight, AlertTriangle, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import axios from 'axios';
import { toast } from 'vue-sonner';
import JournalModal from './JournalModal.vue';

const {
    journals,
    isLoading,
    startDate,
    endDate,
    currentPage,
    lastPage,
    totalData,
    fetchJournals,
    handleFilterChange,
    changePage,
    formatCurrency
} = useJournal() as any;

// ==========================================
// STATE KONTROL 1 MODAL (CREATE, EDIT, VIEW)
// ==========================================
const isModalOpen = ref(false);
const modalMode = ref<'create' | 'edit' | 'view'>('create');
const activeJournal = ref<any>(null);
const accounts = ref<Array<{ id: string; code: string; name: string }>>([]);

// ==========================================
// STATE KHUSUS KONFIRMASI HAPUS
// ==========================================
const isConfirmModalOpen = ref(false);
const itemToDelete = ref<any>(null);
const isDeleting = ref(false);

const confirmDelete = (item: any) => {
    itemToDelete.value = item;
    isConfirmModalOpen.value = true;
};

const executeDelete = async () => {
    if (!itemToDelete.value) return;

    isDeleting.value = true;
    try {
        const response = await axios.delete(`/api/finance/journal-entry/${itemToDelete.value.id}`);
        if (response.data.success) {
            toast.success("Jurnal manual berhasil dihapus.");
            isConfirmModalOpen.value = false;
            isModalOpen.value = false; // Tutup modal detail juga
            fetchJournals(currentPage.value);
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || "Gagal menghapus jurnal.");
    } finally {
        isDeleting.value = false;
        itemToDelete.value = null;
    }
};

const fetchAccounts = async () => {
    try {
        const response = await axios.get('/api/finance/accounts');
        accounts.value = response.data.data || response.data;
    } catch (error) {
        console.error("Gagal memuat master akun:", error);
    }
};

const handleOpenCreateModal = async () => {
    await fetchAccounts();
    modalMode.value = 'create';
    activeJournal.value = null;
    isModalOpen.value = true;
};

const handleRowClick = async (journal: any) => {
    await fetchAccounts();
    modalMode.value = 'view';
    activeJournal.value = journal;
    isModalOpen.value = true;
};

const handleSwitchToEdit = (journal: any) => {
    modalMode.value = 'edit';
    activeJournal.value = journal;
};

const handleSaveJournal = async (formData: any) => {
    try {
        let response;
        if (modalMode.value === 'edit') {
            response = await axios.put(`/api/finance/journal-entry/${formData.id}`, formData);
            toast.success("Jurnal umum berhasil diperbarui!");
        } else {
            response = await axios.post('/api/finance/journal-entry', formData);
            toast.success("Jurnal umum berhasil ditambahkan!");
        }

        if (response.data.success) {
            isModalOpen.value = false;
            fetchJournals(currentPage.value);
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || "Terjadi kesalahan saat menyimpan jurnal.");
    }
};

const paginationPages = (current: number, last: number) => {
    if (!last || last <= 1) return [];
    const delta = 2;
    const range = [];
    const rangeWithDots = [];
    let l: number | undefined;

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
            range.push(i);
        }
    }

    for (let i of range) {
        if (l) {
            if (i - l === 2) {
                rangeWithDots.push(l + 1);
            } else if (i - l !== 1) {
                rangeWithDots.push('...');
            }
        }
        rangeWithDots.push(i);
        l = i;
    }

    return rangeWithDots;
};
</script>

<template>
    <div class="p-4 sm:p-6 space-y-6">
        <!-- Header & Tombol Tambah Jurnal -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b pb-5 border-border/60">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">Buku Jurnal Umum</h1>
                <p class="text-xs text-muted-foreground mt-1">
                    Riwayat catatan pembukuan transaksi finansial berpasangan (<i>double-entry ledger</i>) secara kronologis.
                </p>
            </div>

            <Button
                @click="handleOpenCreateModal"
                class="inline-flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white dark:bg-zinc-100 dark:hover:bg-zinc-200 dark:text-zinc-900 px-4 py-2.5 rounded-2xl text-xs font-bold shadow-sm transition-all active:scale-95 cursor-pointer shrink-0"
            >
                <Plus class="h-4 w-4" /> Tambah Jurnal
            </Button>
        </div>

        <!-- Filter Periode -->
        <div class="flex flex-wrap items-center gap-2.5 p-3 bg-slate-50/60 dark:bg-zinc-900/40 rounded-2xl border border-slate-200/80 dark:border-zinc-800 text-xs shadow-2xs w-full sm:w-fit">
            <span class="font-bold text-slate-500">Periode Buku:</span>
            <div class="flex items-center gap-2 flex-1 sm:flex-none">
                <Input
                    v-model="startDate"
                    @change="handleFilterChange"
                    type="date"
                    class="bg-white dark:bg-zinc-900 border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-1 focus:outline-none h-9 font-medium w-full sm:w-auto text-xs shadow-2xs"
                />
                <span class="text-slate-400 font-semibold">s/d</span>
                <Input
                    v-model="endDate"
                    @change="handleFilterChange"
                    type="date"
                    class="bg-white dark:bg-zinc-900 border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-1 focus:outline-none h-9 font-medium w-full sm:w-auto text-xs shadow-2xs"
                />
            </div>
        </div>

        <!-- Tabel Jurnal -->
        <div class="rounded-2xl border border-slate-200/80 dark:border-zinc-800 overflow-hidden bg-white dark:bg-zinc-950 shadow-xs">
            <div class="overflow-x-auto w-full custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs min-w-[700px]">
                    <thead>
                        <tr class="bg-slate-50/80 dark:bg-zinc-900/60 border-b border-slate-200/80 dark:border-zinc-800 font-bold text-slate-400 uppercase tracking-wider text-[10px]">
                            <th class="px-4 py-3.5 w-32">Tanggal Buku</th>
                            <th class="px-4 py-3.5">Keterangan Akun Pembukuan</th>
                            <th class="px-4 py-3.5 w-40 text-right">Total Transaksi</th>
                            <th class="px-4 py-3.5 w-24 text-center">Sumber</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zinc-900">
                        <tr
                            v-for="j in journals"
                            :key="j?.id"
                            @click="handleRowClick(j)"
                            class="bg-white hover:bg-slate-50/60 dark:bg-zinc-950 dark:hover:bg-zinc-900/40 font-medium text-slate-800 dark:text-zinc-200 cursor-pointer transition-colors"
                        >
                            <td class="px-4 py-3 font-mono text-slate-500">{{ j?.entry_date }}</td>
                            <td class="px-4 py-3">
                                <span class="text-slate-900 dark:text-zinc-100 text-xs font-bold">{{ j?.description }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold font-mono text-slate-900 dark:text-zinc-100">
                                {{ formatCurrency(j?.total_amount) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                    !j?.is_manual_journal ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400' : 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400'
                                ]">
                                    {{ !j?.is_manual_journal ? 'Otomatis' : 'Manual' }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="(!journals || journals.length === 0) && !isLoading">
                            <td colspan="4" class="px-4 py-12 text-center text-slate-400 italic">
                                Belum ada aktivitas jurnal masuk untuk periode ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bagian Paginasi -->
            <div v-if="lastPage && lastPage > 1" class="px-4 py-3.5 bg-slate-50/80 dark:bg-zinc-900/60 border-t border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <span class="text-slate-500 font-medium">
                    Menampilkan halaman <b class="text-slate-900 dark:text-zinc-100">{{ currentPage }}</b> dari <b class="text-slate-900 dark:text-zinc-100">{{ lastPage }}</b>
                </span>

                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="changePage(currentPage - 1)"
                        :disabled="currentPage <= 1 || isLoading"
                        class="h-8 px-3 rounded-xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-bold disabled:opacity-40 cursor-pointer shadow-2xs"
                    >
                        <ChevronLeft class="h-3.5 w-3.5 mr-1" /> Prev
                    </Button>

                    <Button
                        variant="outline"
                        size="sm"
                        @click="changePage(currentPage + 1)"
                        :disabled="currentPage >= lastPage || isLoading"
                        class="h-8 px-3 rounded-xl border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-bold disabled:opacity-40 cursor-pointer shadow-2xs"
                    >
                        Next <ChevronRight class="h-3.5 w-3.5 ml-1" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 1 MODAL TUNGGAL UNTUK CREATE, EDIT, & VIEW -->
        <!-- ========================================== -->
        <JournalModal
            v-model:isOpen="isModalOpen"
            :mode="modalMode"
            :journalData="activeJournal"
            :accounts="accounts"
            :format-currency="formatCurrency"
            @save="handleSaveJournal"
            @edit="handleSwitchToEdit"
            @delete="(journal) => confirmDelete(journal)"
        />

        <!-- ========================================== -->
        <!-- MODAL KONFIRMASI HAPUS (CUSTOM)            -->
        <!-- ========================================== -->
        <div v-if="isConfirmModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-950 w-full max-w-md rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <div class="size-10 rounded-2xl bg-rose-50 dark:bg-rose-950/50 flex items-center justify-center text-rose-500">
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                    <button @click="isConfirmModalOpen = false" class="size-8 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-900 flex items-center justify-center transition-colors cursor-pointer">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-1.5">
                    <h3 class="font-extrabold text-base text-slate-900 dark:text-zinc-50">Hapus Jurnal Manual?</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Tindakan ini akan menghapus catatan jurnal <b class="text-slate-800 dark:text-zinc-200">"{{ itemToDelete?.description }}"</b> secara permanen dari sistem pembukuan.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <Button
                        variant="outline"
                        type="button"
                        @click="isConfirmModalOpen = false"
                        class="h-10 px-5 rounded-2xl text-xs font-bold border-slate-200 dark:border-zinc-800 cursor-pointer shadow-2xs"
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        @click="executeDelete"
                        :disabled="isDeleting"
                        class="h-10 px-6 rounded-2xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-sm cursor-pointer transition-all disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}
                    </Button>
                </div>
            </div>
        </div>

    </div>
</template>
