import { ref } from 'vue';
import axios from 'axios';
import { useOutlet } from './useOutlet';

export interface MenuPrice {
    id: string;
    menu_id: string;
    channel: string;
    selling_price: number;
    margin_percent: number;
}

export interface Menu {
    id: string;
    outlet_id: string;
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

interface MenuFilters {
    search?: string;
    category_id?: string;
    [key: string]: any;
}

export function useMenus(currentOutletId?: any) {
    const menus = ref<Menu[]>([]); 
    const isLoading = ref(false);
    const meta = ref<any>(null);
    const { getOutletParam } = useOutlet(currentOutletId);

    const fetchMenus = async (filters: MenuFilters = {}) => {
        isLoading.value = true;
        try {
            const outletParam = getOutletParam();
            const queryParams = { ...filters, ...outletParam };
            const response = await axios.get('/api/menus', { params: queryParams });
            menus.value = response.data.data ?? response.data;
            if (response.data.data && response.data.current_page) {
                const { data, ...paginationInfo } = response.data;
                meta.value = paginationInfo;
            }
        } catch (error) {
            console.error("Gagal mengambil data menu:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { 
        menus, 
        isLoading, 
        meta, 
        fetchMenus 
    };
}