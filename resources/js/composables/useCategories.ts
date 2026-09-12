import { ref } from 'vue';
import axios from 'axios';
import { useOutlet } from './useOutlet';

export function useCategories(currentOutletId?: any) {
    const categories = ref<any[]>([]);
    const isLoading = ref(false);
    const { getOutletParam } = useOutlet(currentOutletId);

    const fetchCategories = async (params = {}) => {
        isLoading.value = true;
        try {
            const queryParams = getOutletParam(params);
            const response = await axios.get('/api/categories', { params: queryParams });
            categories.value = response.data;
        } catch (error) {
            console.error('Gagal memuat kategori', error);
        } finally {
            isLoading.value = false;
        }
    };

    const createCategory = async (data: { name: string; sort?: number; is_visible?: boolean }) => {
        const outletParam = getOutletParam();
        const payload = { ...data, ...outletParam };
        const response = await axios.post('/api/categories', payload);
        await fetchCategories();
        return response.data;
    };

    const updateCategory = async (id: string, data: { name?: string; sort?: number; is_visible?: boolean }) => {
        const outletParam = getOutletParam();
        const payload = { ...data, ...outletParam };
        const response = await axios.put(`/api/categories/${id}`, payload);
        await fetchCategories();
        return response.data;
    };

    const deleteCategory = async (id: string) => {
        const response = await axios.delete(`/api/categories/${id}`);
        await fetchCategories();
        return response.data;
    };

    const sortCategories = async (items: Array<{ id: string; sort: number }>) => {
        const outletParam = getOutletParam();
        const payload = { items, ...outletParam };
        const response = await axios.post('/api/categories/sort', payload);
        await fetchCategories();
        return response.data;
    };

    return {
        categories,
        isLoading,
        fetchCategories,
        createCategory,
        updateCategory,
        deleteCategory,
        sortCategories,
    };
}