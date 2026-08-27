export interface ReceiptItem {
    name: string;
    qty: number;
    price: number;
    notes?: string; // Sangat berguna untuk catatan masakan di struk dapur
}

export interface ReceiptData {
    storeName: string;
    storeAddress: string;
    phone: string;
    cashierName: string;
    customerName: string;
    orderNumber: string;
    dateStr: string;
    items: ReceiptItem[];
    subTotal: number;
    discount: number;
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
    const finalTotalAmount = Number(transaction.final_total || transaction.total || 0);
    const paidAmount = Number(transaction.amount_paid || finalTotalAmount);
    const changeVal = paidAmount > finalTotalAmount ? paidAmount - finalTotalAmount : 0;

    return {
        storeName: "Roti Bakar Wisuda",
        storeAddress: "Jl Kaliurang km 12.5 UII",
        phone: "08123456789",
        cashierName: "Admin POS",
        customerName: transaction.customer_name || 'Pelanggan Umum',
        orderNumber: transaction.order_number || '-',
        dateStr: new Date(transaction.created_at || Date.now()).toLocaleString('id-ID'),
        items: itemsList,
        subTotal: subTotalAmount,
        discount: Number(transaction.discount || 0),
        total: finalTotalAmount,
        amountPaid: paidAmount,
        changeAmount: changeVal,
        paymentMethod: transaction.payment_method || 'cash',
        footerMessage: "Terimakasih Telah Berbelanja"
    };
};

/**
 * 1. FORMAT: Struk Kasir Utama (Customer Receipt)
 */
export const formatCashierReceipt = (data: ReceiptData): string => {
    const line = "--------------------------------";
    let text = "";
    
    text += `       ${data.storeName.toUpperCase()}       \n`;
    text += `     ${data.storeAddress}     \n`;
    text += `          Telp: ${data.phone}         \n`;
    text += `${line}\n`;
    text += `No. Nota : ${data.orderNumber}\n`;
    text += `Kasir    : ${data.cashierName}\n`;
    text += `Pelanggan: ${data.customerName}\n`;
    text += `Tanggal  : ${data.dateStr}\n`;
    text += `${line}\n`;

    data.items.forEach(item => {
        text += `${item.name}\n`;
        text += `  ${item.qty}x @${item.price.toLocaleString('id-ID')} ... Rp ${(item.qty * item.price).toLocaleString('id-ID')}\n`;
        if (item.notes) {
            text += `  * Catatan: ${item.notes}\n`;
        }
    });

    text += `${line}\n`;
    text += `Subtotal   : Rp ${data.subTotal.toLocaleString('id-ID')}\n`;
    if (data.discount > 0) {
        text += `Diskon     : -Rp ${data.discount.toLocaleString('id-ID')}\n`;
    }
    text += `Total      : Rp ${data.total.toLocaleString('id-ID')}\n`;
    text += `Bayar (${data.paymentMethod.toUpperCase()}) : Rp ${data.amountPaid.toLocaleString('id-ID')}\n`;
    text += `Kembalian  : Rp ${data.changeAmount.toLocaleString('id-ID')}\n`;
    text += `${line}\n`;
    text += `     ${data.footerMessage}     \n\n\n`;

    return text;
};

/**
 * 2. FORMAT: Struk Dapur (Kitchen Ticket - Tanpa Harga, Fokus Qty & Catatan)
 */
export const formatKitchenReceipt = (data: ReceiptData): string => {
    const line = "================================";
    let text = "";
    
    text += `         [ TIKET DAPUR ]        \n`;
    text += `${line}\n`;
    text += `No. Nota : ${data.orderNumber}\n`;
    text += `Pelanggan: ${data.customerName}\n`;
    text += `Waktu    : ${data.dateStr}\n`;
    text += `${line}\n`;
    text += `PESANAN:\n`;

    data.items.forEach((item, index) => {
        text += `${index + 1}. (${item.qty}x) ${item.name.toUpperCase()}\n`;
        if (item.notes) {
            text += `    NOTE: [ ${item.notes} ]\n`;
        }
    });

    text += `${line}\n`;
    text += `   * Segera siapkan pesanan *\n\n\n`;

    return text;
};

/**
 * 3. FORMAT: Salinan Struk (Copy Receipt)
 */
export const formatCopyReceipt = (data: ReceiptData): string => {
    let text = formatCashierReceipt(data);
    // Tambahkan tanda air atau label copy di atas teks kasir
    return `*** SALINAN / COPY ***\n` + text;
};