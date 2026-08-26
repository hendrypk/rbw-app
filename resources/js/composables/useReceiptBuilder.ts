export interface ItemBelanja {
  name: string;
  qty: number;
  price: number;
}

export interface ReceiptData {
  storeName: string;
  storeAddress: string;
  phone?: string;
  cashierName?: string;
  customerName?: string;
  orderNumber: string;
  dateStr: string;
  items: ItemBelanja[];
  subTotal: number;
  discount?: number;
  total: number;
  amountPaid?: number;
  changeAmount?: number;
  paymentMethod?: string;
  footerMessage?: string;
}

export function useReceiptBuilder() {
  const LINE_WIDTH = 32; 

  const formatRupiah = (amount: number): string => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount).replace('Rp', '').trim();
  };

  const padCenter = (text: string): string => {
    if (text.length >= LINE_WIDTH) return text.substring(0, LINE_WIDTH);
    const padding = Math.floor((LINE_WIDTH - text.length) / 2);
    return ' '.repeat(padding) + text;
  };

  const padRightLeft = (left: string, right: string): string => {
    const spaceLength = LINE_WIDTH - (left.length + right.length);
    if (spaceLength < 1) return left + ' ' + right;
    return left + ' '.repeat(spaceLength) + right;
  };

  const generateReceiptText = (data: ReceiptData): string => {
    let receipt = "";

    // Simbol Logo Toko Teks (Pengganti grafis raster untuk printer thermal teks)
    receipt += padCenter("[=== T O K O ===]") + "\n";
    receipt += padCenter(data.storeName) + "\n";
    receipt += padCenter(data.storeAddress) + "\n";
    if (data.phone) {
      receipt += padCenter(`Telp: ${data.phone}`) + "\n";
    }
    receipt += "-".repeat(LINE_WIDTH) + "\n";

    // Info Header Transaksi (Tanggal, Kasir, Pelanggan, No Invoice)
    receipt += padRightLeft(data.dateStr, data.cashierName ? `Kasir: ${data.cashierName}` : "") + "\n";
    if (data.customerName) {
      receipt += padRightLeft(`Pelanggan:`, data.customerName) + "\n";
    }
    receipt += `No. Inv: ${data.orderNumber}\n`;
    receipt += "-".repeat(LINE_WIDTH) + "\n";

    // Daftar Item Belanja (Dengan nomor urut seperti referensi)
    let totalQtyCount = 0;
    data.items.forEach((item, index) => {
      const itemNo = `${index + 1}. ${item.name}`;
      const itemDetailQty = `   ${item.qty} x ${formatRupiah(item.price)}`;
      const itemSubTotal = formatRupiah(item.price * item.qty);
      
      totalQtyCount += item.qty;

      receipt += itemNo + "\n";
      receipt += padRightLeft(itemDetailQty, itemSubTotal) + "\n";
    });

    receipt += "-".repeat(LINE_WIDTH) + "\n";

    // Ringkasan Pembayaran
    receipt += padRightLeft(`Total QTY : ${totalQtyCount}`, "") + "\n";
    receipt += padRightLeft("Sub Total", formatRupiah(data.subTotal)) + "\n";
    if (data.discount && data.discount > 0) {
      receipt += padRightLeft("Diskon", `-${formatRupiah(data.discount)}`) + "\n";
    }
    receipt += padRightLeft("Total", formatRupiah(data.total)) + "\n";

    if (data.amountPaid !== undefined) {
      const paymentMethodLabel = `Bayar (${(data.paymentMethod || 'cash').toUpperCase()})`;
      receipt += padRightLeft(paymentMethodLabel, formatRupiah(data.amountPaid)) + "\n";
      receipt += padRightLeft("Kembali", formatRupiah(data.changeAmount || 0)) + "\n";
    }

    receipt += "-".repeat(LINE_WIDTH) + "\n";

    // Footer Pesan & Kritik Saran
    const footer = data.footerMessage || "Terimakasih Telah Berbelanja";
    receipt += padCenter(footer) + "\n";
    receipt += padCenter("Layanan Kritik & Saran:") + "\n";
    receipt += padCenter("bit.ly/e-receipt-pos") + "\n";

    return receipt;
  };

  return {
    generateReceiptText
  };
}