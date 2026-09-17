import { ref, onMounted } from 'vue';
import axios from 'axios';

export interface JournalItem {
    account_code: string;
    account_name: string;
    type: 'debit' | 'credit';
    amount: number;
}

export interface JournalEntry {
    id: string;
    entry_date: string;
    description: string;
    total_amount: number;
    items: JournalItem[];
    is_manual_journal: boolean;
}

export function useJournal() {
    const journals = ref<JournalEntry[]>([]);
    const isLoading = ref<boolean>(false);

    // State Paginasi
    const currentPage = ref<number>(1);
    const lastPage = ref<number>(1);
    const totalData = ref<number>(0);
    const perPage = ref<number>(10);

    // Set default filter: Awal bulan ini s/d hari ini
    const today = new Date().toISOString().split('T')[0];
    const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 2).toISOString().split('T')[0];

    const startDate = ref<string>(firstDayOfMonth);
    const endDate = ref<string>(today);

    // Fetch data dari backend Laravel dengan parameter halaman dan filter tanggal
    const fetchJournals = async (page = 1) => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/finance/journal-entry', {
                params: {
                    page: page,
                    per_page: perPage.value,
                    start_date: startDate.value,
                    end_date: endDate.value
                }
            });

            // Mengambil struktur data dari Laravel Paginator (response.data.data)
            const paginatedResponse = response.data.data;

            journals.value = paginatedResponse.data;
            currentPage.value = paginatedResponse.current_page;
            lastPage.value = paginatedResponse.last_page;
            totalData.value = paginatedResponse.total;

        } catch (error) {
            console.error('Gagal memuat data jurnal umum:', error);
        } finally {
            isLoading.value = false;
        }
    };

    // Fungsi navigasi halaman
    const changePage = (page: number) => {
        if (page >= 1 && page <= lastPage.value) {
            currentPage.value = page;
            fetchJournals(page);
        }
    };

    // Fungsi utility untuk format mata uang Rupiah
    const formatCurrency = (val: number | string) => {
        const num = typeof val === 'string' ? parseFloat(val) : val;
        return 'Rp ' + (num || 0).toLocaleString('id-ID');
    };

    // Jalankan fetch otomatis kembali ke halaman 1 saat filter tanggal diubah
    const handleFilterChange = () => {
        currentPage.value = 1;
        fetchJournals(1);
    };

    onMounted(() => {
        fetchJournals(1);
    });

    return {
        journals,
        isLoading,
        startDate,
        endDate,
        currentPage,
        lastPage,
        totalData,
        fetchJournals,
        changePage,
        handleFilterChange,
        formatCurrency
    };
}
