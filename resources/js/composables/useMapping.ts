import { ref } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useOutlet } from './useOutlet';

export interface Mapping {
    id: string;
    outlet_id?: string;
    transaction_type: string;
    debit_account_id: string;
    credit_account_id: string;
    description_template: string;
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

export function useMapping(currentOutletId?: any) {
    const mappings = ref<Mapping[]>([]);
    const loading = ref(false);
    const { getOutletParam } = useOutlet(currentOutletId);

    const fetchMappings = async (filters: Record<string, any> = {}) => {
        try {
            loading.value = true;
            const outletParam = getOutletParam();
            const queryParams = { ...filters, ...outletParam };
            const response = await axios.get('/api/finance/account-mappings', {
                params: queryParams,
            });
            mappings.value = response.data.data || response.data;
        } catch (error: any) {
            toast.error('Gagal mengambil data mapping akun');
            console.error(error);
        } finally {
            loading.value = false;
        }
    };

    const updateMapping = async (id: string, payload: Partial<Mapping>) => {
        try {
            const outletParam = getOutletParam();
            const dataToSubmit = { ...payload, ...outletParam };
            const response = await axios.put(`/api/finance/account-mappings/${id}`, dataToSubmit);
            toast.success('Pemetaan akun berhasil diperbarui!');
            await fetchMappings();
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