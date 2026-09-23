import { ref } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

export interface Voucher {
    id: string;
    code: string;
    name: string;
    type: 'fixed' | 'percentage';
    value: number;
    min_spend: number;
    max_discount: number | null;
    usage_limit: number | null;
    used_count: number;
    started_at: string | null;
    expired_at: string | null;
    is_active: boolean;
    menus?: Array<{ id: string; name: string }>;
}

export interface AppliedVoucher {
    voucher_id: string;
    code: string;
    name: string;
    type: 'fixed' | 'percentage';
    value: number;
    discount_amount: number;
}

export function useVoucher() {
    const vouchers = ref<Voucher[]>([]);
    const appliedVoucher = ref<AppliedVoucher | null>(null);
    const isValidating = ref<boolean>(false);
    const isLoadingVouchers = ref<boolean>(false);
    const voucherCodeInput = ref<string>('');

    // Tambahkan parameter outletId pada fetchVouchers
    const fetchVouchers = async (outletId?: string | number) => {
        isLoadingVouchers.value = true;
        try {
            const response = await axios.get('/api/vouchers', {
                params: { outlet_id: outletId } // Kirim sebagai query parameter
            });
            vouchers.value = response.data.data || response.data;
        } catch (error) {
            console.error('Gagal memuat daftar voucher:', error);
            toast.error('Gagal memuat daftar voucher');
        } finally {
            isLoadingVouchers.value = false;
        }
    };

    // Tambahkan outletId pada validasi agar backend bisa mengecek kecocokan outlet
    const validateAndApplyVoucher = async (
        code: string,
        cartItems: Array<{ menu_id: string; subtotal: number }>,
        outletId?: string | number
    ) => {
        if (!code.trim()) {
            toast.error('Masukkan kode voucher terlebih dahulu.');
            return false;
        }

        isValidating.value = true;
        try {
            const response = await axios.post('/api/v1/user/voucher/validate', {
                code: code,
                items: cartItems,
                outlet_id: outletId // Sertakan di payload request
            });

            if (response.data.success) {
                const resData = response.data.data;

                appliedVoucher.value = {
                    voucher_id: resData.id || resData.voucher_id,
                    code: resData.code,
                    name: resData.name,
                    type: resData.type,
                    value: resData.value,
                    discount_amount: resData.discount_amount
                };

                toast.success(response.data.message);
                return true;
            }
        } catch (error: any) {
            const errorMsg = error.response?.data?.message || 'Gagal menerapkan voucher.';
            toast.error(errorMsg);
            appliedVoucher.value = null;
            return false;
        } finally {
            isValidating.value = false;
        }
        return false;
    };

    const removeVoucher = () => {
        appliedVoucher.value = null;
        voucherCodeInput.value = '';
    };

    return {
        vouchers,
        appliedVoucher,
        isValidating,
        isLoadingVouchers,
        voucherCodeInput,
        fetchVouchers,
        validateAndApplyVoucher,
        removeVoucher,
    };
}
