import { ref } from 'vue';
import axios from 'axios';
import { useOutlet } from '@/composables/useOutlet';

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

    const { getOutletParam } = useOutlet(currentOutletId);

    const fetchMaterials = async (params: Record<string, any> = {}) => {
        isLoading.value = true;
        try {
            const queryParams = getOutletParam(params);

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

    const fetchMaterialOptions = async () => {
        try {
            const params = getOutletParam();

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