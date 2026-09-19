import { ref } from 'vue';
import axios from 'axios';

export interface Customer {
    id: string;
    name: string;
    phone: string | null;
    email: string | null;
    shipping_address: string | null;
    total_points: number;
    // Data tambahan dari agregasi backend
    orders_count?: number;
    total_spent?: number;
    total_portions?: number;
}

export function useCustomers() {
    const customers = ref<Customer[]>([]);
    const customerOptions = ref<Customer[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null);

    const fetchCustomers = async (params: Record<string, any> = {}) => {
        isLoading.value = true;
        try {
            const { data } = await axios.get('/api/customers', { params });

            customers.value = data.data;
            meta.value = data.meta || null;
        } catch (error) {
            console.error('Gagal memuat data pelanggan:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const fetchCustomerOptions = async (params: Record<string, any> = {}) => {
        try {
            const { data } = await axios.get('/api/customers/options', { params });
            customerOptions.value = data.data || data;
        } catch (error) {
            console.error('Gagal memuat opsi pelanggan:', error);
        }
    };

    return {
        customers,
        customerOptions,
        meta,
        isLoading,
        fetchCustomers,
        fetchCustomerOptions,
    };
}
