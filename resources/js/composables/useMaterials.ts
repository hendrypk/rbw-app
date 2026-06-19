import { ref } from 'vue';
import axios from 'axios';

// Pastikan interface ini mencakup semua field baru
export interface Material {
    id: string;
    name: string;
    base_unit: string;      // Tambahkan ini
    purchase_unit: string;  // Tambahkan ini
    stock_qty: number;
    min_stock: number;
    is_active: boolean;
    avg_cost: number;       // Tambahkan ini
    last_cost: number;      // Tambahkan ini
}

export function useMaterials() {
    const materials = ref<Material[]>([]);
    const isLoading = ref(false);
    // 2. Tipe meta yang lebih spesifik
    const meta = ref<any>(null); 

    const fetchMaterials = async (params: { page?: number; search?: string; active?: boolean } = {}) => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/raw-materials', {
                params: {
                    page: params.page ?? 1,
                    search: params.search,
                    active: params.active
                }
            });

            materials.value = response.data.data;
            
            // 3. Simpan meta saja, pisahkan dari data
            const { data, ...paginationInfo } = response.data;
            meta.value = paginationInfo;
            
        } catch (error) {
            console.error("Gagal mengambil data material:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { materials, isLoading, meta, fetchMaterials };
}