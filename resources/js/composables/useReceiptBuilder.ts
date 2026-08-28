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

  // Helper untuk memotong nama item panjang menjadi beberapa baris agar tidak merusak layout 32 char
  const wrapItemName = (name: string): string[] => {
    if (name.length <= LINE_WIDTH) return [name];
    const words = name.split(' ');
    const lines: string[] = [];
    let currentLine = '';

    for (const word of words) {
      if ((currentLine + (currentLine ? ' ' : '') + word).length <= LINE_WIDTH) {
        currentLine += (currentLine ? ' ' : '') + word;
      } else {
        if (currentLine) lines.push(currentLine);
        // Jika satu kata melebihi LINE_WIDTH secara ekstrem, potong paksa
        if (word.length > LINE_WIDTH) {
          let remainingWord = word;
          while (remainingWord.length > 0) {
            lines.push(remainingWord.substring(0, LINE_WIDTH));
            remainingWord = remainingWord.substring(LINE_WIDTH);
          }
          currentLine = '';
        } else {
          currentLine = word;
        }
      }
    }
    if (currentLine) lines.push(currentLine);
    return lines;
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

    // Daftar Item Belanja (Dengan nomor urut & wrap nama item panjang)
    let totalQtyCount = 0;
    data.items.forEach((item, index) => {
      const prefix = `${index + 1}. `;
      const wrappedNames = wrapItemName(item.name);

      // Baris pertama nama item digabung dengan nomor urut
      wrappedNames.forEach((wName, wIdx) => {
        if (wIdx === 0) {
          receipt += prefix + wName + "\n";
        } else {
          receipt += `   ${wName}\n`;
        }
      });

      // Baris detail qty x harga dan subtotal item
      const itemDetailQty = `   ${item.qty} x ${formatRupiah(item.price)}`;
      const itemSubTotal = formatRupiah(item.price * item.qty);
      
      totalQtyCount += item.qty;

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