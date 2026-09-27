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

export function useOrder(currentOutletId?: any) {
    const orders = ref<Order[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);
    const { getOutletParam } = useOutlet(currentOutletId);

    // Menerima parameter pencarian, status, dan page
    const fetchOrders = async (params: { page?: number; search?: string; status?: string } = {}) => {
        // isLoading.value = true;
        try {
            const outletParam = getOutletParam();

            // Gabungkan filter pencarian dan parameter outlet
            const queryParams = { ...params, ...outletParam };

            // Panggil API Laravel
            const response = await axios.get('/api/sales', { params: queryParams });

            // Simpan array data ke 'orders'
            orders.value = response.data.data;

            // Pisahkan sisa pagination (links, total, from, to) ke 'meta'
            const { data, ...paginationInfo } = response.data;
            meta.value = paginationInfo;

        } catch (error) {
            console.error("Gagal memuat Order:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { orders, isLoading, meta, fetchOrders };
}
