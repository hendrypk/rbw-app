export interface ReceiptItem {
    name: string;
    qty: number;
    price: number;
    notes?: string; 
}

export interface ReceiptData {
    storeName: string;
    storeAddress: string;
    phone: string;
    instagram: string;
    cashierName: string;
    customerName: string;
    orderNumber: string;
    queueNumber: string; 
    dateStr: string;
    items: ReceiptItem[];
    subTotal: number;
    discount: number;
    pointsUsed: number; // ⬅️ Ditambahkan ke Interface
    total: number;
    amountPaid: number;
    changeAmount: number;
    paymentMethod: string;
    footerMessage: string;
}

/**
 * Helper pemetaan data transaksi mentah ke format standar
 */
export const mapTransactionToReceiptData = (transaction: any): ReceiptData => {
    const itemsList = (transaction.items || []).map((item: any) => ({
        name: item.menu?.name || item.name || item.product_name || 'Item POS',
        qty: Number(item.quantity || item.qty || 1),
        price: Number(item.price || 0),
        notes: item.notes || transaction.notes || ''
    }));
    
    const subTotalAmount = itemsList.reduce((acc: number, curr: any) => acc + (curr.price * curr.qty), 0);
    const discountAmount = Number(transaction.discount || 0);

    // Mengambil nilai poin dari relasi 'points' atau 'customer_points'
    let pointsAmount = 0;
    const pointData = transaction.points || transaction.customer_points;
    if (pointData) {
        if (!Array.isArray(pointData)) {
            pointsAmount = Math.abs(Number(pointData.points || 0));
        } else {
            const found = pointData.find((p: any) => p.type === 'redeem' || p.type === 'out' || Number(p.points) < 0);
            if (found) {
                pointsAmount = Math.abs(Number(found.points));
            }
        }
    } else {
        pointsAmount = Number(transaction.points_used || transaction.points || 0);
    }

    const rawTotal = Number(transaction.final_total || transaction.total || 0);
    const finalTotalAmount = rawTotal > 0 ? rawTotal : Math.max(0, (subTotalAmount - discountAmount) - pointsAmount);
    
    const rawPaid = transaction.amount_paid ?? transaction.paid ?? transaction.cash_amount;
    const paidAmount = (rawPaid !== undefined && rawPaid !== null && Number(rawPaid) > 0) ? Number(rawPaid) : finalTotalAmount;
    
    const changeVal = paidAmount > finalTotalAmount ? paidAmount - finalTotalAmount : 0;

    return {
        storeName: "Roti Bakar Wisuda",
        storeAddress: "Jl Kaliurang km 12.5 UII",
        phone: "085814973157",
        instagram: "rotibakar.wisuda",
        cashierName: "Admin POS",
        customerName: transaction.customer_name || 'Pelanggan Umum',
        orderNumber: transaction.order_number || '-',
        queueNumber: transaction.queue_number || transaction.queueNumber || 'A-01',
        dateStr: new Date(transaction.created_at || Date.now()).toLocaleString('id-ID'),
        items: itemsList,
        subTotal: subTotalAmount,
        discount: discountAmount,
        pointsUsed: pointsAmount,
        total: finalTotalAmount,
        amountPaid: paidAmount,
        changeAmount: changeVal,
        paymentMethod: transaction.payment_method || 'cash',
        footerMessage: "Terimakasih Telah Berbelanja"
    };
};