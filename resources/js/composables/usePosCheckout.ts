import { ref, computed, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useVoucher } from './useVoucher';

// --- Type Declarations ---
export interface CartItem {
    menu_id: string;
    name: string;
    quantity: number;
    price: number;
    subtotal: number;
    image_path?: string;
}

export interface QrisData {
    invoiceNo: string;
    referenceNo: string;
    qrContent: string;
}

export interface CompletedOrderData {
    orderNumber: string;
    customerName: string;
    customerId: string | null;
    subtotal: number;
    discount: number;
    pointsUsed?: number;
    finalTotal: number;
    items: CartItem[];
    paymentMethod: string;
}

export function usePosCheckout() {
    // --- UI & Modal States ---
    const isPaymentModalOpen = ref<boolean>(false);
    const isCustomerAddModalOpen = ref<boolean>(false);
    const isCustomerModalOpen = ref<boolean>(false);
    const isDiscountModalOpen = ref<boolean>(false);
    const isQrisModalOpen = ref<boolean>(false);
    const isGeneratingQris = ref<boolean>(false);
    const isSuccessModalOpen = ref<boolean>(false);
    const checking = ref<boolean>(false);

    // --- Transaction Form States ---
    const customerName = ref<string>('');
    const customerId = ref<string>('');
    const orderNote = ref<string>('');
    const discountInput = ref<number>(0);
    const transactionFee = ref<number>(0);
    const paymentMethod = ref<string>('cash');
    const amountPaidInput = ref<number>(0);

    // --- Cart, QRIS & Success Order States ---
    const cart = ref<CartItem[]>([]);
    const qrisData = ref<QrisData>({ invoiceNo: '', referenceNo: '', qrContent: '' });
    const paymentStatus = ref<'PENDING' | 'SUCCESS' | 'FAILED'>('PENDING');

    const {
        vouchers,
        appliedVoucher,
        isLoadingVouchers,
        fetchVouchers,
        validateAndApplyVoucher,
        removeVoucher
    } = useVoucher();

    let statusInterval: any = undefined;

    // --- Computed Financials ---
    const cartSubtotal = computed<number>(() =>
        cart.value.reduce((sum: number, item: CartItem) => sum + item.subtotal, 0)
    );

    const taxAmount = computed<number>(() => cartSubtotal.value * 0.11);

    const totalDiscount = computed<number>(() => {
        if (appliedVoucher.value) {
            return appliedVoucher.value.discount_amount;
        }
        return Number(discountInput.value) || 0;
    });

     const finalTotal = computed<number>(() => {
        const total = (cartSubtotal.value + Number(transactionFee.value)) - Number(totalDiscount.value);
        const roundedTotal = Math.round(total);
        return roundedTotal < 0 ? 0 : roundedTotal;
    });

    const lastCompletedOrder = ref<CompletedOrderData>({
        orderNumber: '-',
        customerName: '',
        customerId: '',
        subtotal: 0,
        discount: 0,
        pointsUsed: 0,
        finalTotal: 0,
        items: [],
        paymentMethod: 'cash'
    });

    const openPaymentModal = () => {
        amountPaidInput.value = finalTotal.value;
        isPaymentModalOpen.value = true;
    };

    const closePaymentModal = () => {
        isPaymentModalOpen.value = false;
    };

    const closeQrisModal = () => {
        isQrisModalOpen.value = false;
        if (statusInterval) clearInterval(statusInterval);
    };

    const closeSuccessModal = () => {
        isSuccessModalOpen.value = false;
        resetPosState();
    };

    const openCustomerModal = () => {
        isCustomerModalOpen.value = true;
    };

    const openCustomerAddModal = () => {
        isCustomerAddModalOpen.value = true;
    };

    const openDiscountModal = () => {
        isDiscountModalOpen.value = true;
    };

    const resetPosState = () => {
        isPaymentModalOpen.value = false;
        isSuccessModalOpen.value = false;
        cart.value = [];
        orderNote.value = '';
        discountInput.value = 0;
        transactionFee.value = 0;
        customerName.value = '';
        customerId.value = '';
        amountPaidInput.value = 0;
        paymentStatus.value = 'PENDING';
        removeVoucher();
    };

    const getCartValidationItems = () => {
        return cart.value.map(item => ({
            menu_id: item.menu_id,
            subtotal: item.subtotal
        }));
    };

    // --- Checkout Actions (Standard Cash/Save) ---
    const submitCheckout = async (type: 'save' | 'pay') => {
        if (cart.value.length === 0) return;

        if (paymentMethod.value === 'qris' && type === 'pay') {
            await handleQrisCheckout();
            return;
        }

        try {
            const payload = {
                customer_name: customerName.value || 'Pelanggan POS',
                customer_id: customerId.value || null,
                payment_method: paymentMethod.value,
                discount: totalDiscount.value,
                voucher_id: appliedVoucher.value?.voucher_id || null,
                transaction_fee: transactionFee.value,
                notes: orderNote.value,
                items: cart.value.map((item: CartItem) => ({
                    menu_id: item.menu_id,
                    quantity: item.quantity
                })),
                action_type: type,
                amount_paid: type === 'pay' ? amountPaidInput.value : 0
            };

            const activeOutletId = localStorage.getItem('active_outlet_id');

            const response = await axios.post('/api/pos/checkout', payload, {
                headers: {
                    'X-Outlet-ID': activeOutletId
                }
            });

            const orderData = response.data.data || response.data;
            const validOrderNumber = orderData?.order_number || orderData?.orderNumber || orderData?.invoice_no || orderData?.id || '-';

            toast.success(`Transaksi ${orderData.order_number} berhasil diproses!`);

            if (type === 'pay') {
                lastCompletedOrder.value = {
                    orderNumber: validOrderNumber,
                    customerName: customerName.value || 'Pelanggan Umum',
                    customerId: customerId.value || null,
                    subtotal: cartSubtotal.value,
                    discount: totalDiscount.value,
                    pointsUsed: Number(orderData.points_used || orderData.points || 0),
                    finalTotal: orderData.final_total ?? finalTotal.value,
                    items: [...cart.value],
                    paymentMethod: paymentMethod.value
                };

                closePaymentModal();
                paymentStatus.value = 'SUCCESS';
                isSuccessModalOpen.value = true;
            } else {
                resetPosState();
            }
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal memproses transaksi');
        }
    };

    // --- DOKU QRIS Dynamic Checkout Handler ---
    const handleQrisCheckout = async () => {
        if (isGeneratingQris.value) return;
        isGeneratingQris.value = true;

        try {
            closePaymentModal();

            const registerPayload = {
                customer_name: customerName.value || 'Pelanggan POS',
                customer_id: customerId.value || null,
                payment_method: 'qris',
                discount: totalDiscount.value,
                voucher_id: appliedVoucher.value?.voucher_id || null,
                transaction_fee: Number(transactionFee.value) || 0,
                notes: orderNote.value || '',
                items: cart.value.map((item: CartItem) => ({
                    menu_id: item.menu_id,
                    quantity: item.quantity
                })),
                action_type: 'save',
                amount_paid: 0
            };

            const activeOutletId = localStorage.getItem('active_outlet_id');

            const registerResponse = await axios.post('/api/pos/checkout', registerPayload, {
                headers: {
                    'X-Outlet-ID': activeOutletId
                }
            });
            if (!registerResponse.data.success) {
                throw new Error(registerResponse.data.message || 'Gagal membuat tagihan order.');
            }

            const registeredOrder = registerResponse.data.data;

            const qrisResponse = await axios.post('/api/payment/qris/generate', {
                order_number: registeredOrder.order_number,
                amount: registeredOrder.final_total
            }, {
                headers: {
                    'X-Outlet-ID': activeOutletId
                }
            });

            if (qrisResponse.data.status === 'success') {
                qrisData.value.invoiceNo = registeredOrder.order_number;
                (qrisData.value as any).orderId = registeredOrder.order_id || registeredOrder.id;

                qrisData.value.referenceNo = qrisResponse.data.data.reference_no;
                qrisData.value.qrContent = qrisResponse.data.data.qr_content;

                isQrisModalOpen.value = true;
                paymentStatus.value = 'PENDING';
                startPollingStatus();

            } else {
                throw new Error(qrisResponse.data.message || 'Gagal meng-generate QRIS DOKU');
            }
        } catch (error: any) {
            const errorMsg = error.response?.data?.error || error.response?.data?.message || error.message || 'Gagal menyiapkan QRIS';
            toast.error(errorMsg);
        } finally {
            isGeneratingQris.value = false;
        }
    };

    // --- Polling Status Payment Checker ---
    const startPollingStatus = () => {
        if (statusInterval) clearInterval(statusInterval);

        statusInterval = setInterval(async () => {
            try {
                const response = await axios.post('/api/payment/qris/check-status', {
                    order_number: qrisData.value.invoiceNo,
                    reference_no: qrisData.value.referenceNo
                });

                if (response.data.status === 'success' && response.data.paid) {
                    paymentStatus.value = 'SUCCESS';
                    clearInterval(statusInterval);

                    const currentOrderId = (qrisData.value as any).orderId;

                    if (currentOrderId) {
                        await axios.post(`/api/pos/orders/${currentOrderId}/mark-paid`, {
                            payment_method: 'qris'
                        });
                    }

                    handleQrisSuccessAction();

                } else if (response.data.status === 'FAILED') {
                    paymentStatus.value = 'FAILED';
                    clearInterval(statusInterval);
                }
            } catch (error) {
                // Silent error on background poll
            }
        }, 4000);
    };

    const handleQrisSuccessAction = (orderDetail?: any) => {
        lastCompletedOrder.value = {
            orderNumber: orderDetail?.order_number || '-',
            customerName: customerName.value || 'Pelanggan Umum',
            customerId: customerId.value || null,
            subtotal: cartSubtotal.value,
            discount: totalDiscount.value,
            pointsUsed: Number(orderDetail?.points_used || orderDetail?.points || 0),
            finalTotal: orderDetail?.final_total ?? finalTotal.value,
            items: [...cart.value],
            paymentMethod: orderDetail?.payment_method || 'qris'
        };

        if (statusInterval) clearInterval(statusInterval);
        isQrisModalOpen.value = false;
        isSuccessModalOpen.value = true;
    };

    onBeforeUnmount(() => {
        if (statusInterval) clearInterval(statusInterval);
    });

    return {
        isPaymentModalOpen, isQrisModalOpen, isGeneratingQris, isSuccessModalOpen, checking,
        customerName, customerId, orderNote, discountInput, transactionFee, paymentMethod, amountPaidInput,
        cart, qrisData, paymentStatus, lastCompletedOrder, isCustomerModalOpen, isDiscountModalOpen, isCustomerAddModalOpen,
        vouchers, appliedVoucher, isLoadingVouchers, fetchVouchers, validateAndApplyVoucher, removeVoucher, getCartValidationItems,
        cartSubtotal, taxAmount, finalTotal, closeSuccessModal,
        openPaymentModal, closePaymentModal, closeQrisModal, openCustomerModal, openDiscountModal, openCustomerAddModal,
        resetPosState,
        submitCheckout, handleQrisCheckout, handleQrisSuccessAction
    };
}
