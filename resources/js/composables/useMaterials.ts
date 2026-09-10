import { ref } from 'vue';
import axios from 'axios';

export interface Material {
    id: string;
    name: string;
    base_unit: string;
    purchase_unit: string;
    stock_qty: number;
    min_stock: number;
    is_active: boolean;
    avg_cost: number;
    last_cost: number;
}

export function useMaterials(currentOutletId?: any) {
    const materials = ref<Material[]>([]);
    const materialOptions = ref<Material[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);

const getOutletParam = () => {
        // Cek jika parameter berupa Ref Vue atau fungsi, ambil .value-nya
        let outletId = null;
        if (currentOutletId) {
            outletId = typeof currentOutletId === 'object' && 'value' in currentOutletId 
                ? currentOutletId.value 
                : (typeof currentOutletId === 'function' ? currentOutletId() : currentOutletId);
        }
        
        // Fallback ke localStorage jika kosong atau bernilai 'all'
        if (!outletId || outletId === 'all') {
            outletId = localStorage.getItem('active_outlet_id');
        }

        return outletId && outletId !== 'all' ? outletId : null;
    };

    // Untuk halaman master material (pagination)
    const fetchMaterials = async (
        params: {
            page?: number;
            search?: string;
            active?: boolean;
        } = {}
    ) => {
        isLoading.value = true;

        try {
            const outletId = getOutletParam();
            const queryParams = outletId ? { ...params, outlet_id: outletId } : params;

            const { data } = await axios.get('/api/raw-materials', {
                params: queryParams,
            });

            materials.value = data.data;

            const { data: _, ...pagination } = data;
            meta.value = pagination;
        } catch (error) {
            console.error('Failed to fetch materials data:', error);
        } finally {
            isLoading.value = false;
        }
    };

    // Untuk dropdown
    const fetchMaterialOptions = async () => {
        try {
            const outletId = getOutletParam();
            const params = outletId ? { outlet_id: outletId } : {};

            const { data } = await axios.get('/api/raw-materials/options', {
                params,
            });

            materialOptions.value = data;
        } catch (error) {
            console.error('Failed to fetch material options:', error);
        }
    };

    return {
        materials,
        materialOptions,
        meta,
        isLoading,
        fetchMaterials,
        fetchMaterialOptions,
    };
}