import { ref } from 'vue';
import axios from 'axios';

export interface PurchaseOrder {
    id: string;
    po_number: string;
    supplier: { name: string };
    order_date: string;
    status: string;
    total_amount: number;
}

export function usePurchaseOrders() {
    const purchaseOrders = ref<PurchaseOrder[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);

    const fetchPurchaseOrders = async (params: { page?: number; search?: string } = {}) => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/purchase-orders', { params });
            purchaseOrders.value = response.data.data;
            const { data, ...paginationInfo } = response.data;
            meta.value = paginationInfo;
        } catch (error) {
            console.error("Gagal memuat PO:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { purchaseOrders, isLoading, meta, fetchPurchaseOrders };
}