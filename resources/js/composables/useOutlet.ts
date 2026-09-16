import axios from 'axios';
import { ref, unref, type MaybeRef } from 'vue';

const globalOutlets = ref<any[]>([]);
const globalMeta = ref<any>(null); // 1. Tambahkan state meta
const isLoadingOutlets = ref(false);


export function useOutlet(targetOutlet?: MaybeRef<string | null | undefined>) {
    const getOutletId = (): string | null => {
        let outletId = unref(targetOutlet);

        if (!outletId || outletId === 'all') {
            outletId = localStorage.getItem('active_outlet_id');
        }

        return outletId && outletId !== 'all' ? outletId : null;
    };

    const getOutletParam = (additionalParams: Record<string, any> = {}) => {
        const outletId = getOutletId();
        return outletId ? { ...additionalParams, outlet_id: outletId } : additionalParams;
    };

    const fetchOutlets = async (customParams: Record<string, any> = {}) => {
        isLoadingOutlets.value = true;
        try {
            const params = { ...customParams };
            const response = await axios.get('/api/outlets', { params });

            globalOutlets.value = response.data.data || response.data || [];

            globalMeta.value = response.data.meta || response.data || null;

        } catch (error) {
            console.error('Gagal mengambil data outlet:', error);
        } finally {
            isLoadingOutlets.value = false;
        }
    };

    return {
        getOutletId,
        getOutletParam,
        fetchOutlets,
        outlets: globalOutlets,
        meta: globalMeta,
        isLoadingOutlets,
    };
}
