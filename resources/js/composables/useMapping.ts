import { ref } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

// Struktur data tunggal terstandarisasi sesuai database
export interface Mapping {
    id: string;
    transaction_type: string;
    debit_account_id: string;
    credit_account_id: string;
    description_template: string;
    // Tambahkan relasi opsional jika dari backend mereturn object COA untuk tampilan tabel
    debit_account?: {
        id: string;
        code: string;
        name: string;
    };
    credit_account?: {
        id: string;
        code: string;
        name: string;
    };
}

export function useMapping() {
    const mappings = ref<Mapping[]>([]);
    const loading = ref(false);

    // Ambil data seluruh data mapping dari backend
    const fetchMappings = async () => {
        try {
            loading.value = true;
            const response = await axios.get('/api/finance/account-mappings');
            // Menyesuaikan jika API Anda membungkus data di response.data.data
            mappings.value = response.data.data || response.data;
        } catch (error: any) {
            toast.error('Gagal mengambil data mapping akun');
            console.error(error);
        } finally {
            loading.value = false;
        }
    };

    // Update data mapping berdasarkan UUID
    const updateMapping = async (id: string, payload: Partial<Mapping>) => {
        try {
            const response = await axios.put(`/api/finance/account-mappings/${id}`, payload);
            toast.success('Pemetaan akun berhasil diperbarui!');
            return response.data;
        } catch (error: any) {
            const errMsg = error.response?.data?.message || 'Gagal menyimpan perubahan pemetaan';
            toast.error(errMsg);
            throw error;
        }
    };

    return {
        mappings,
        loading,
        fetchMappings,
        updateMapping
    };
}