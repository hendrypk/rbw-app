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

export interface CompletedOrderData {
    orderNumber: string;
    customerName: string;
    finalTotal: number;
    items: CartItem[];
    paymentMethod: string;
}

export function usePosCheckout() {
    // --- UI & Modal States ---
    const isPaymentModalOpen = ref<boolean>(false);
    const isQrisModalOpen = ref<boolean>(false);
    const isGeneratingQris = ref<boolean>(false);
    const isSuccessModalOpen = ref<boolean>(false); // State untuk modal sukses universal
    const checking = ref<boolean>(false);

    // --- Transaction Form States ---
    const customerName = ref<string>('');
    const orderNote = ref<string>('');
    const discountInput = ref<number>(0);
    const transactionFee = ref<number>(0);
    const paymentMethod = ref<string>('cash');
    const amountPaidInput = ref<number>(0);

    // --- Cart, QRIS & Success Order States ---
    const cart = ref<CartItem[]>([]);
    const qrisData = ref<QrisData>({ invoiceNo: '', referenceNo: '', qrContent: '' });
    const qrisPaymentStatus = ref<'PENDING' | 'SUCCESS' | 'FAILED'>('PENDING');
    
    // Menyimpan data pesanan terakhir yang sukses untuk dicetak struk
    const lastCompletedOrder = ref<CompletedOrderData>({
        orderNumber: '-',
        customerName: '',
        finalTotal: 0,
        items: [],
        paymentMethod: 'cash'
    });

    let statusInterval: any = undefined;

    // --- Computed Financials ---
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
        isSuccessModalOpen.value = false;
        cart.value = [];
        orderNote.value = '';
        discountInput.value = 0;
        transactionFee.value = 0;
        customerName.value = '';
        amountPaidInput.value = 0;
        qrisPaymentStatus.value = 'PENDING';
    };

    // --- Checkout Actions (Standard Cash/Save) ---
    const submitCheckout = async (type: 'save' | 'pay') => {
        if (cart.value.length === 0) return;

        // Jika metode pembayaran QRIS dan memilih bayar, arahkan ke QRIS Handler
        if (paymentMethod.value === 'qris' && type === 'pay') {
            await handleQrisCheckout();
            return;
        }

        try {
            const payload = {
                customer_name: customerName.value || 'Pelanggan POS',
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
            const orderData = response.data.data;
            
            toast.success(`Transaksi ${orderData.order_number} berhasil diproses!`);

            // Jika dibayar tunai (pay), simpan data struk dan tampilkan modal sukses universal
            if (type === 'pay') {
                lastCompletedOrder.value = {
                    orderNumber: orderData.order_number,
                    customerName: customerName.value || 'Pelanggan Umum',
                    finalTotal: finalTotal.value,
                    items: [...cart.value],
                    paymentMethod: paymentMethod.value
                };
                
                closePaymentModal();
                isSuccessModalOpen.value = true; // Munculkan modal sukses
            } else {
                // Jika hanya disimpan (save / unpaid), langsung reset state keranjang
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
                
                // ✅ Simpan order_id (atau id) dari respons backend agar tidak null
                (qrisData.value as any).orderId = registeredOrder.order_id || registeredOrder.id; 

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

                // JIKA PEMBAYARAN DARI GATEWAY SUDAH SUKSES/PAID
                if (response.data.status === 'success' && response.data.paid) {
                    qrisPaymentStatus.value = 'SUCCESS';
                    clearInterval(statusInterval);

                    // --- LANGSUNG AMBIL ENDPOINT MARK-PAID DI SINI ---
const currentOrderId = (qrisData.value as any).orderId;
console.log("ID Order yang mau dilunasi:", currentOrderId); // Cek F12 Console browser

if (currentOrderId) {
    const res = await axios.post(`/api/pos/orders/${currentOrderId}/mark-paid`, {
        payment_method: 'qris'
    });
    console.log("Respon mark-paid:", res.data);
} else {
    console.error("ERROR: orderId kosong/null!");
}

                    // Panggil fungsi untuk memunculkan modal sukses & data struk
                    handleQrisSuccessAction();

                } else if (response.data.status === 'FAILED') {
                    qrisPaymentStatus.value = 'FAILED';
                    clearInterval(statusInterval);
                }
            } catch (error) {
                console.error('Gagal mengecek status pembayaran', error);
            }
        }, 4000);
    };

    // Dipanggil ketika polling QRIS mendeteksi status SUCCESS dari backend
    const handleQrisSuccessAction = () => {
        lastCompletedOrder.value = {
            orderNumber: qrisData.value.invoiceNo,
            customerName: customerName.value || 'Pelanggan Umum',
            finalTotal: finalTotal.value,
            items: [...cart.value],
            paymentMethod: 'qris'
        };

        if (statusInterval) clearInterval(statusInterval);
        isQrisModalOpen.value = false;
        
        // Tampilkan modal sukses universal
        isSuccessModalOpen.value = true;
    };

    onBeforeUnmount(() => {
        if (statusInterval) clearInterval(statusInterval);
    });

    return {
        isPaymentModalOpen, isQrisModalOpen, isGeneratingQris, isSuccessModalOpen, checking,
        customerName, orderNote, discountInput, transactionFee, paymentMethod, amountPaidInput,
        cart, qrisData, qrisPaymentStatus, lastCompletedOrder,
        cartSubtotal, taxAmount, finalTotal,
        openPaymentModal, closePaymentModal, closeQrisModal, resetPosState,
        submitCheckout, handleQrisCheckout, handleQrisSuccessAction
    };
}