// resources/js/composables/useMenus.ts
import { ref } from 'vue';
import axios from 'axios';

export interface Menu {
    id: string;
    name: string;
    category: string;
    hpp: number;
    is_active: boolean;
    // Tambahkan field lain jika ada (misal: recipes, prices)
}

export function useMenus() {
    // TAMBAHKAN <Menu[]> DI SINI agar TypeScript tahu isinya adalah array of Menu
    const menus = ref<Menu[]>([]); 
    const isLoading = ref(false);
    const meta = ref<any>(null); // Opsional: jika ingin mendukung pagination seperti materials

    const fetchMenus = async (params: { page?: number; search?: string } = {}) => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/menus', { params });
            
            // Jika responsnya adalah array langsung
            menus.value = response.data;
            
            // ATAU jika responsnya ada pagination (data: []), gunakan:
            // menus.value = response.data.data;
            // const { data, ...paginationInfo } = response.data;
            // meta.value = paginationInfo;
            
        } catch (error) {
            console.error("Gagal mengambil data menu:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { menus, isLoading, meta, fetchMenus };
}