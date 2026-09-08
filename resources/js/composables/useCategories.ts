import { ref } from 'vue';
import axios from 'axios';

export function useCategories() {
    const categories = ref<any[]>([]);
    const isLoading = ref(false);

    const fetchCategories = async (params = {}) => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/categories', { params });
            categories.value = response.data;
        } catch (error) {
            console.error('Gagal memuat kategori', error);
        } finally {
            isLoading.value = false;
        }
    };

    return {
        categories,
        isLoading,
        fetchCategories,
    };
}