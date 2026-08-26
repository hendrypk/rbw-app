import { ref, computed, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

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

export function usePosCheckout() {
    // --- UI States ---
    const isPaymentModalOpen = ref<boolean>(false);
    const isQrisModalOpen = ref<boolean>(false);
    const isGeneratingQris = ref<boolean>(false);
    const checking = ref<boolean>(false);

    // --- Transaction Form States ---
    const customerName = ref<string>('');
    const orderNote = ref<string>('');
    const discountInput = ref<number>(0);
    const transactionFee = ref<number>(0);
    const paymentMethod = ref<string>('cash');
    const amountPaidInput = ref<number>(0);

    // --- Cart & QRIS States ---
    const cart = ref<CartItem[]>([]);
    const qrisData = ref<QrisData>({ invoiceNo: '', referenceNo: '', qrContent: '' });
    const qrisPaymentStatus = ref<'PENDING' | 'SUCCESS' | 'FAILED'>('PENDING');
    let statusInterval: any = undefined;

    // --- Computed Financials (Explicit Type Parameters Added) ---
    const cartSubtotal = computed<number>(() => 
        cart.value.reduce((sum: number, item: CartItem) => sum + item.subtotal, 0)
    );
    
    const taxAmount = computed<number>(() => cartSubtotal.value * 0.11);
    
    const finalTotal = computed<number>(() => {
        const total = (cartSubtotal.value + taxAmount.value + Number(transactionFee.value)) - Number(discountInput.value);
        return total < 0 ? 0 : total;
    });

    // --- Modal Controls ---
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

    const resetPosState = () => {
        isPaymentModalOpen.value = false;
        cart.value = [];
        orderNote.value = '';
        discountInput.value = 0;
        transactionFee.value = 0;
        customerName.value = '';
        amountPaidInput.value = 0;
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
                customer_name: customerName.value,
                payment_method: paymentMethod.value,
                discount: discountInput.value,
                transaction_fee: transactionFee.value,
                notes: orderNote.value,
                items: cart.value.map((item: CartItem) => ({ 
                    menu_id: item.menu_id, 
                    quantity: item.quantity 
                })),
                action_type: type,
                amount_paid: type === 'pay' ? amountPaidInput.value : 0
            };

            const response = await axios.post('/api/pos/checkout', payload);
            toast.success(`Transaksi ${response.data.data.order_number} berhasil diproses!`);
            resetPosState();
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
                payment_method: 'qris',
                discount: Number(discountInput.value) || 0,
                transaction_fee: Number(transactionFee.value) || 0,
                notes: orderNote.value || '',
                items: cart.value.map((item: CartItem) => ({ 
                    menu_id: item.menu_id, 
                    quantity: item.quantity 
                })),
                action_type: 'save',
                amount_paid: 0
            };

            const registerResponse = await axios.post('/api/pos/checkout', registerPayload);
            if (!registerResponse.data.success) {
                throw new Error(registerResponse.data.message || 'Gagal membuat tagihan order.');
            }

            const registeredOrder = registerResponse.data.data;

            const qrisResponse = await axios.post('/api/payment/qris/generate', {
                order_number: registeredOrder.order_number,
                amount: registeredOrder.final_total
            });

            if (qrisResponse.data.status === 'success') {
                qrisData.value.invoiceNo = registeredOrder.order_number;
                qrisData.value.referenceNo = qrisResponse.data.data.reference_no;
                qrisData.value.qrContent = qrisResponse.data.data.qr_content;

                isQrisModalOpen.value = true;
                qrisPaymentStatus.value = 'PENDING';
                startPollingStatus();
            } else {
                throw new Error(qrisResponse.data.message || 'Gagal meng-generate QRIS DOKU');
            }
        } catch (error: any) {
            const errorMsg = error.response?.data?.error || error.response?.data?.message || error.message || 'Gagal menyiapkan QRIS';
            toast.error(errorMsg);
            console.error('QRIS Checkout Error:', error.response?.data || error);
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
                    qrisPaymentStatus.value = 'SUCCESS';
                    clearInterval(statusInterval);
                } else if (response.data.status === 'FAILED') {
                    qrisPaymentStatus.value = 'FAILED';
                    clearInterval(statusInterval);
                }
            } catch (error) {
                console.error('Gagal mengecek status pembayaran', error);
            }
        }, 4000);
    };

    onBeforeUnmount(() => {
        if (statusInterval) clearInterval(statusInterval);
    });

    return {
        isPaymentModalOpen, isQrisModalOpen, isGeneratingQris, checking,
        customerName, orderNote, discountInput, transactionFee, paymentMethod, amountPaidInput,
        cart, qrisData, qrisPaymentStatus,
        cartSubtotal, taxAmount, finalTotal,
        openPaymentModal, closePaymentModal, closeQrisModal,
        submitCheckout, handleQrisCheckout
    };
}