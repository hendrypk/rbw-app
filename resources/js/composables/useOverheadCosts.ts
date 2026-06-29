import { ref } from 'vue';
import axios from 'axios';

export interface OverheadCost {
    id: string;
    name: string;
    amount: number;
    type: string;
    is_active: boolean;
}

export function useOverheadCosts() {
    const overheads = ref<OverheadCost[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);

    const fetchOverheads = async (params: { page?: number; search?: string; status?: string } = {}) => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/overhead-costs', { params });
            
            // Karena backend menggunakan ->paginate(), data array berada di dalam .data.data
            if (response.data && response.data.data) {
                overheads.value = response.data.data;
                
                // Simpan info paginasi (current_page, last_page, dll) ke dalam meta
                const { data, ...paginationInfo } = response.data;
                meta.value = paginationInfo;
            } else {
                // Fallback jika backend mengembalikan direct array []
                overheads.value = Array.isArray(response.data) ? response.data : [];
                meta.value = null;
            }
        } catch (error) {
            console.error("Gagal mengambil data overhead cost:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { overheads, isLoading, meta, fetchOverheads };
}