import { ref, computed, onMounted } from 'vue';
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
}

export function useJournal() {
    const journals = ref<JournalEntry[]>([]);
    const isLoading = ref<boolean>(false);
    
    // Set default filter: Awal bulan ini s/d hari ini
    const today = new Date().toISOString().split('T')[0];
    const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 2).toISOString().split('T')[0];
    
    const startDate = ref<string>(firstDayOfMonth);
    const endDate = ref<string>(today);

    // Fetch data dari backend Laravel dengan query string filter tanggal
    const fetchJournals = async () => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/finance/journal-entry', {
                params: {
                    start_date: startDate.value,
                    end_date: endDate.value
                }
            });
            journals.value = response.data.data;
        } catch (error) {
            console.error('Gagal memuat data jurnal umum:', error);
        } finally {
            isLoading.value = false;
        }
    };

    // Fungsi utility untuk format mata uang Rupiah
    const formatCurrency = (val: number | string) => {
        const num = typeof val === 'string' ? parseFloat(val) : val;
        return 'Rp ' + (num || 0).toLocaleString('id-ID');
    };

    // Jalankan fetch otomatis saat filter tanggal diubah oleh user
    const handleFilterChange = () => {
        fetchJournals();
    };

    onMounted(() => {
        fetchJournals();
    });

    return {
        journals,
        isLoading,
        startDate,
        endDate,
        fetchJournals,
        handleFilterChange,
        formatCurrency
    };
}