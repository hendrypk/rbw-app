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

interface OverheadFilters {
    search?: string;
    type?: string;
    [key: string]: any;
}

export function useOverheadCosts(currentOutletId?: any) {
    const overheads = ref<OverheadCost[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);
    const { getOutletParam } = useOutlet(currentOutletId);

    const fetchOverheads = async (filters: OverheadFilters = {}) => {
        isLoading.value = true;
        try {
            const outletParam = getOutletParam();
            const queryParams = { ...filters, ...outletParam };
            const response = await axios.get('/api/overhead-costs', { params: queryParams });
            overheads.value = response.data.data ?? response.data;
            if (response.data.data && response.data.current_page) {
                const { data, ...paginationInfo } = response.data;
                meta.value = paginationInfo;
            }
        } catch (error) {
            console.error("Gagal memuat overhead costs:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return {
        overheads,
        isLoading,
        meta,
        fetchOverheads,
    };
}