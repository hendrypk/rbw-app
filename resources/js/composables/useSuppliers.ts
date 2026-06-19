// resources/js/composables/useSuppliers.ts
import { ref } from 'vue';
import axios from 'axios';

export function useSuppliers() {
    const suppliers = ref<any[]>([]);
    const isLoading = ref(false);
    const meta = ref<any>(null); // Menyimpan info pagination

    const fetchSuppliers = async (params: { page?: number; search?: string; active?: boolean } = {}) => {
        isLoading.value = true;
        try {
            // Kita gunakan route() helper (jika pakai Ziggy) atau URL langsung
            const response = await axios.get('/api/suppliers', {
                params: {
                    page: params.page ?? 1,
                    search: params.search,
                    active: params.active
                }
            });

            suppliers.value = response.data.data;
            meta.value = response.data; // Simpan meta pagination
        } catch (error) {
            console.error("Gagal mengambil data supplier:", error);
        } finally {
            isLoading.value = false;
        }
    };

    return { suppliers, isLoading, meta, fetchSuppliers };
}