import { ref } from 'vue';
import axios from 'axios';

export function useCategories() {
    const categories = ref<any[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    const fetchCategories = async () => {
        isLoading.value = true;
        error.value = null;
        try {
            const res = await axios.get('/api/categories');
            categories.value = res.data;
        } catch (err: any) {
            console.error('Gagal memuat kategori:', err);
            error.value = err.response?.data?.message || 'Gagal memuat data kategori.';
        } finally {
            isLoading.value = false;
        }
    };

    return {
        categories,
        isLoading,
        error,
        fetchCategories
    };
}