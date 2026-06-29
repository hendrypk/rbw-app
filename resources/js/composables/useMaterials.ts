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

export function useMaterials() {
    const materials = ref<Material[]>([]);
    const materialOptions = ref<Material[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);

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
            const { data } = await axios.get('/api/raw-materials', {
                params,
            });

            materials.value = data.data;

            const { data: _, ...pagination } = data;
            meta.value = pagination;
        } finally {
            isLoading.value = false;
        }
    };

    // Untuk dropdown
    const fetchMaterialOptions = async () => {
        const { data } = await axios.get('/api/raw-materials/options');

        materialOptions.value = data;
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