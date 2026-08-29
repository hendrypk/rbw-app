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
 * Template HTML Universal untuk Kertas Thermal 58mm
 */
const wrapHtmlReceipt = (content: string): string => {
    return `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Print Struk</title>
            <style>
                /* Pengaturan khusus ukuran fisik kertas thermal 58mm */
                @page {
                    size: 58mm auto;
                    margin: 0;
                }
                body {
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 11px;
                    line-height: 1.2;
                    width: 58mm;
                    max-width: 58mm;
                    margin: 0 auto;
                    padding: 4mm 2mm;
                    color: #000;
                    background: #fff;
                }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .font-bold { font-weight: bold; }
                .uppercase { text-transform: uppercase; }
                .divider {
                    border-top: 1px dashed #000;
                    margin: 6px 0;
                }
                .divider-solid {
                    border-top: 1px solid #000;
                    margin: 6px 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th, td {
                    font-size: 11px;
                    vertical-align: top;
                    padding: 1px 0;
                }
                .item-row td {
                    padding-top: 3px;
                }
                .notes {
                    font-size: 10px;
                    font-style: italic;
                    color: #333;
                    padding-left: 10px;
                }
                @media print {
                    body {
                        width: 58mm;
                    }
                }
            </style>
        </head>
        <body>
            ${content}
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(() => { window.close(); }, 500);
                }
            </script>
        </body>
        </html>
    `;
};

/**
 * 1. HTML: Struk Kasir Utama (Customer Receipt)
 */
export const formatCashierReceiptHtml = (data: ReceiptData): string => {
    let html = `
        <div class="text-center font-bold uppercase" style="font-size: 13px;">${data.storeName}</div>
        <div class="text-center" style="font-size: 10px;">${data.storeAddress}</div>
        <div class="text-center" style="font-size: 10px; margin-bottom: 4px;">Telp: ${data.phone}</div>
        <div class="divider"></div>
        
        <table style="font-size: 10px;">
            <tr><td width="30%">No. Nota</td><td width="70%">: ${data.orderNumber}</td></tr>
            <tr><td>Kasir</td><td>: ${data.cashierName}</td></tr>
            <tr><td>Pelanggan</td><td>: ${data.customerName}</td></tr>
            <tr><td>Tanggal</td><td>: ${data.dateStr}</td></tr>
        </table>
        
        <div class="divider"></div>
        
        <table>
    `;

    data.items.forEach(item => {
        const itemSubtotal = item.qty * item.price;
        html += `
            <tr class="item-row">
                <td colspan="2" class="font-bold">${item.name}</td>
            </tr>
            <tr>
                <td>${item.qty}x @${item.price.toLocaleString('id-ID')}</td>
                <td class="text-right">Rp ${itemSubtotal.toLocaleString('id-ID')}</td>
            </tr>
        `;
        if (item.notes) {
            html += `<tr><td colspan="2" class="notes">* ${item.notes}</td></tr>`;
        }
    });

    html += `
        </table>
        <div class="divider"></div>
        
        <table>
            <tr><td>Subtotal</td><td class="text-right">Rp ${data.subTotal.toLocaleString('id-ID')}</td></tr>
    `;

    if (data.discount > 0) {
        html += `<tr><td>Diskon</td><td class="text-right">-Rp ${data.discount.toLocaleString('id-ID')}</td></tr>`;
    }

    html += `
            <tr class="font-bold" style="font-size: 12px;">
                <td>TOTAL</td>
                <td class="text-right">Rp ${data.total.toLocaleString('id-ID')}</td>
            </tr>
            <tr><td>Bayar (${data.paymentMethod.toUpperCase()})</td><td class="text-right">Rp ${data.amountPaid.toLocaleString('id-ID')}</td></tr>
            <tr><td>Kembalian</td><td class="text-right">Rp ${data.changeAmount.toLocaleString('id-ID')}</td></tr>
        </table>
        
        <div class="divider"></div>
        <div class="text-center" style="margin-top: 6px; font-size: 10px;">${data.footerMessage}</div>
        <div style="height: 15px;"></div>
    `;

    return wrapHtmlReceipt(html);
};

/**
 * 2. HTML: Struk Dapur (Kitchen Ticket)
 */
export const formatKitchenReceiptHtml = (data: ReceiptData): string => {
    let html = `
        <div class="text-center font-bold" style="font-size: 14px; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px;">
            [ TIKET DAPUR ]
        </div>
        
        <table style="font-size: 11px; margin-bottom: 6px;">
            <tr><td width="30%">No. Nota</td><td width="70%">: ${data.orderNumber}</td></tr>
            <tr><td>Pelanggan</td><td>: ${data.customerName}</td></tr>
            <tr><td>Waktu</td><td>: ${data.dateStr}</td></tr>
        </table>
        
        <div class="divider-solid"></div>
        <div class="font-bold" style="margin-bottom: 4px;">DAFTAR PESANAN:</div>
        
        <table style="font-size: 12px;">
    `;

    data.items.forEach((item, index) => {
        html += `
            <tr style="padding-top: 4px;">
                <td width="15%" class="font-bold">${index + 1}. (${item.qty}x)</td>
                <td width="85%" class="font-bold uppercase">${item.name}</td>
            </tr>
        `;
        if (item.notes) {
            html += `<tr><td colspan="2" class="notes" style="font-size: 11px; font-weight: bold; color: #000;">NOTE: [ ${item.notes} ]</td></tr>`;
        }
    });

    html += `
        </table>
        <div class="divider-solid"></div>
        <div class="text-center font-bold" style="margin-top: 8px;">* Segera Siapkan Pesanan *</div>
        <div style="height: 15px;"></div>
    `;

    return wrapHtmlReceipt(html);
};