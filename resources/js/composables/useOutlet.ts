import { unref, type MaybeRef } from 'vue';

/**
 * Helper global untuk mendeteksi ID outlet aktif secara konsisten
 * Mendukung Ref, Getter/Function, atau nilai mentah, dengan fallback ke localStorage.
 */
export function useOutlet(targetOutlet?: MaybeRef<string | null | undefined>) {
    const getOutletId = (): string | null => {
        // unref otomatis mendeteksi apakah parameter berupa Vue Ref atau nilai biasa
        let outletId = unref(targetOutlet);

        // Jika berupa fungsi (getter), eksekusi
        if (typeof targetOutlet === 'function') {
            outletId = targetOutlet();
        }

        // Fallback ke localStorage jika kosong atau bernilai 'all'
        if (!outletId || outletId === 'all') {
            outletId = localStorage.getItem('active_outlet_id');
        }

        return outletId && outletId !== 'all' ? outletId : null;
    };

    const getOutletParam = (additionalParams: Record<string, any> = {}) => {
        const outletId = getOutletId();
        return outletId ? { ...additionalParams, outlet_id: outletId } : additionalParams;
    };

    return {
        getOutletId,
        getOutletParam,
    };
}