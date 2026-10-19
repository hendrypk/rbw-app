import { ref } from 'vue';
import axios from 'axios';
import { useOutlet } from './useOutlet';

// Definisikan Interface sesuai dengan fillable yang Anda berikan
export interface Order {
    id: string | number;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    transaction_at: string;
    status: string;
    final_total: number;
    payment_method: string;
}

export function useOrders(currentOutletId?: any) {
    const orders = ref<Order[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);
    const { getOutletParam } = useOutlet(currentOutletId);

    const fetchOrders = async (params: { page?: number; search?: string } = {}) => {
        isLoading.value = true;
        try {
            const outletParam = getOutletParam();
            const queryParams = { ...params, ...outletParam };
            const response = await axios.get('/api/sales', { params: queryParams });
            orders.value = response.data.data;
            const { data, ...paginationInfo } = response.data;
            meta.value = paginationInfo;
        } catch (error) {
            console.error("Gagal memuat PO:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { orders, isLoading, meta, fetchOrders };
}
