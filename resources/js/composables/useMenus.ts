// resources/js/composables/useMenus.ts
import { ref } from 'vue';
import axios from 'axios';

export interface MenuPrice {
    id: string;
    menu_id: string;
    channel: string;
    selling_price: number;
    margin_percent: number;
}

export interface Menu {
    id: string;
    name: string;
    category?: {
        id: string;
        name: string;
        is_visible: boolean;
    };
    categories?: Array<{
        id: string;
        name: string;
        is_visible: boolean;
        pivot?: {
            sort: number;
        };
    }>;
    hpp: number;
    overhead_cost: number;
    is_active: boolean;
    prices?: MenuPrice[];
}

export function useMenus() {
    const menus = ref<Menu[]>([]); 
    const isLoading = ref(false);
    const meta = ref<any>(null);

    const fetchMenus = async (params: { page?: number; search?: string } = {}) => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/menus', { params });
            menus.value = response.data;
        } catch (error) {
            console.error("Gagal mengambil data menu:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { menus, isLoading, meta, fetchMenus };
}