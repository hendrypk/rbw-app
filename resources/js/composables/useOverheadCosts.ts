import { ref } from 'vue';
import axios from 'axios';
import { useOutlet } from './useOutlet';

export interface OverheadCost {
    id: string;
    outlet_id: string;
    name: string;
    amount: number;
    type: string;
    is_active: boolean;
}

export function useOverheadCosts() {
    const overheads = ref<OverheadCost[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);
    const { getOutletParam } = useOutlet();

    const fetchOverheads = async (params: { page?: number; search?: string; type?: string } = {}) => {
        isLoading.value = true;
        try {
            const outletParam = getOutletParam();
            const queryParams = { ...params, ...outletParam };
            const response = await axios.get('/api/overhead-costs', { params: queryParams });
            overheads.value = response.data.data;
            const { data, ...paginationInfo } = response.data;
            meta.value = paginationInfo;
        } catch (error) {
            console.error("Gagal memuat overhead costs:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { overheads, isLoading, meta, fetchOverheads };
}