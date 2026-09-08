import { ref, onMounted } from 'vue';
import axios from 'axios';

export interface Outlet {
    id: string;
    name: string;
}

export function useOutletFilter() {
    const outlets = ref<Outlet[]>([]);
    const selectedOutletId = ref<string>('all');

    const fetchOutlets = async (onLoaded?: (outletId: string) => void) => {
        try {
            const response = await axios.get('/api/outlets');
            if (response.data.success && response.data.data.length > 0) {
                outlets.value = response.data.data;
                const saved = localStorage.getItem('selected_outlet_id');
                selectedOutletId.value = saved || 'all';
                
                if (onLoaded) {
                    onLoaded(selectedOutletId.value);
                }
            }
        } catch (error) {
            console.error("Gagal memuat daftar outlet", error);
        }
    };

    const setOutlet = (outletId: string, callback?: (outletId: string) => void) => {
        selectedOutletId.value = outletId;
        localStorage.setItem('selected_outlet_id', outletId);
        if (callback) {
            callback(outletId);
        }
    };

    return {
        outlets,
        selectedOutletId,
        fetchOutlets,
        setOutlet
    };
}   