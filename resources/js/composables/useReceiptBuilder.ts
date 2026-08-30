import { ReceiptData } from "./useReceiptFormatter";
/**
 * 1. FORMAT TEKS: Struk Kasir Utama (Customer Receipt) - Lebar 32 Kolom
 */
export const formatCashierReceipt = (data: ReceiptData): string => {
    const line = "--------------------------------";
    let text = "";
    
    text += `       ${data.storeName.toUpperCase()}       \n`;
    text += `    ${data.storeAddress}    \n`;
    text += `       WA: ${data.phone}       \n`;
    text += `     IG: @${data.instagram}     \n`;
    text += `${line}\n`;
    text += `No. Nota : #${data.orderNumber}\n`;
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
    text += `Sub Total      : Rp ${data.subTotal.toLocaleString('id-ID')}\n`;
    // if (data.discount > 0) {
        text += `Discount       : Rp ${data.discount.toLocaleString('id-ID')}\n`;
    // }
    text += `Total          : Rp ${data.total.toLocaleString('id-ID')}\n`;
    if (data.pointsUsed && data.pointsUsed > 0) {
        text += `Poin Digunakan : Rp ${data.pointsUsed.toLocaleString('id-ID')}\n`;
    }
    text += `Dibayar (${data.paymentMethod.toUpperCase()})  : Rp ${data.amountPaid.toLocaleString('id-ID')}\n`;
    text += `Kembalian      : Rp ${data.changeAmount.toLocaleString('id-ID')}\n`;
    text += `${line}\n`;
    text += `    ${data.footerMessage}    \n\n\n`;

    return text;
};

/**
 * 2. FORMAT TEKS: Struk Dapur (Kitchen Ticket - Termasuk Nomor Antrian)
 */
export const formatKitchenReceipt = (data: ReceiptData): string => {
    const line = "================================";
    let text = "";
    
    text += `         [ TIKET DAPUR ]        \n`;
    text += `${line}\n`;
    text += `NO. ANTRIAN : [ ${data.queueNumber} ]\n`; 
    text += `No. Nota    : #${data.orderNumber}\n`;
    text += `Pelanggan   : ${data.customerName}\n`;
    text += `Waktu       : ${data.dateStr}\n`;
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
 * 3. FORMAT TEKS: Salinan Struk (Copy Receipt)
 */
export const formatCopyReceipt = (data: ReceiptData): string => {
    let text = formatCashierReceipt(data);
    return `*** SALINAN / COPY ***\n` + text;
};

/**
 * Template HTML Universal yang dioptimalkan untuk Kertas Thermal 58mm
 */
const wrapHtmlReceipt = (content: string): string => {
    return `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Print Struk</title>
            <style>
                @page {
                    size: 58mm auto;
                    margin: 0;
                }
                body {
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 10px;
                    line-height: 1.15;
                    width: 54mm;
                    max-width: 54mm;
                    margin: 0 auto;
                    padding: 2mm 1mm;
                    color: #000;
                    background: #fff;
                }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .font-bold { font-weight: bold; }
                .uppercase { text-transform: uppercase; }
                .divider {
                    border-top: 1px dashed #000;
                    margin: 4px 0;
                }
                .divider-solid {
                    border-top: 1px solid #000;
                    margin: 4px 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th, td {
                    font-size: 10px;
                    vertical-align: top;
                    padding: 1px 0;
                }
                .item-row td {
                    padding-top: 2px;
                }
                .notes {
                    font-size: 9px;
                    font-style: italic;
                    color: #222;
                    padding-left: 8px;
                }
                @media print {
                    body {
                        width: 54mm;
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
        <div class="text-center font-bold uppercase" style="font-size: 12px;">${data.storeName}</div>
        <div class="text-center" style="font-size: 9px;">${data.storeAddress}</div>
        <div class="text-center" style="font-size: 9px;">WA: ${data.phone}</div>
        <div class="text-center" style="font-size: 9px; margin-bottom: 3px;">IG: @${data.instagram}</div>
        <div class="divider"></div>
        
        <table style="font-size: 9px;">
            <tr><td width="28%">No. Nota</td><td width="72%">: #${data.orderNumber}</td></tr>
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
            <tr><td>Sub Total</td><td class="text-right">Rp ${data.subTotal.toLocaleString('id-ID')}</td></tr>
    `;

    if (data.discount > 0) {
        html += `<tr><td>Discount</td><td class="text-right">Rp ${data.discount.toLocaleString('id-ID')}</td></tr>`;
    }

    html += `
            <tr class="font-bold" style="font-size: 11px;">
                <td>Total</td>
                <td class="text-right">Rp ${data.total.toLocaleString('id-ID')}</td>
            </tr>
    `;

    if (data.pointsUsed && data.pointsUsed > 0) {
        html += `<tr><td>Poin Digunakan</td><td class="text-right">Rp ${data.pointsUsed.toLocaleString('id-ID')}</td></tr>`;
    }

    html += `
            <tr><td>Dibayar (${data.paymentMethod.toUpperCase()})</td><td class="text-right">Rp ${data.amountPaid.toLocaleString('id-ID')}</td></tr>
            <tr><td>Kembalian</td><td class="text-right">Rp ${data.changeAmount.toLocaleString('id-ID')}</td></tr>
        </table>
        
        <div class="divider"></div>
        <div class="text-center" style="margin-top: 4px; font-size: 9px;">${data.footerMessage}</div>
        <div style="height: 10px;"></div>
    `;

    return wrapHtmlReceipt(html);
};

/**
 * 2. HTML: Struk Dapur (Kitchen Ticket dengan Nomor Antrian)
 */
export const formatKitchenReceiptHtml = (data: ReceiptData): string => {
    let html = `
        <div class="text-center font-bold" style="font-size: 12px; border-bottom: 1px solid #000; padding-bottom: 3px; margin-bottom: 4px;">
            [ TIKET DAPUR ]
        </div>
        
        <table style="font-size: 10px; margin-bottom: 4px;">
            <tr><td width="32%" class="font-bold">NO. ANTRIAN</td><td width="68%" class="font-bold">: [ ${data.queueNumber} ]</td></tr>
            <tr><td>No. Nota</td><td>: #${data.orderNumber}</td></tr>
            <tr><td>Pelanggan</td><td>: ${data.customerName}</td></tr>
            <tr><td>Waktu</td><td>: ${data.dateStr}</td></tr>
        </table>
        
        <div class="divider-solid"></div>
        <div class="font-bold" style="margin-bottom: 3px; font-size: 10px;">PESANAN:</div>
        
        <table style="font-size: 10px;">
    `;

    data.items.forEach((item, index) => {
        html += `
            <tr style="padding-top: 3px;">
                <td width="15%" class="font-bold">${index + 1}. (${item.qty}x)</td>
                <td width="85%" class="font-bold uppercase">${item.name}</td>
            </tr>
        `;
        if (item.notes) {
            html += `<tr><td colspan="2" class="notes" style="font-size: 9px; font-weight: bold; color: #000;">NOTE: [ ${item.notes} ]</td></tr>`;
        }
    });

    html += `
        </table>
        <div class="divider-solid"></div>
        <div class="text-center font-bold" style="margin-top: 6px; font-size: 10px;">* Segera Siapkan Pesanan *</div>
        <div style="height: 10px;"></div>
    `;

    return wrapHtmlReceipt(html);
};