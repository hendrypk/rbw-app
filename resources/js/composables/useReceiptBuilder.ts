// resources/js/composables/useReceiptBuilder.ts

export interface ItemBelanja {
  name: string;
  qty: number;
  price: number;
}

export interface ReceiptData {
  storeName: string;
  storeAddress: string;
  cashierName?: string;
  items: ItemBelanja[];
  total: number;
  footerMessage?: string;
}

export function useReceiptBuilder() {
  const LINE_WIDTH = 32; // Standar lebar karakter printer thermal 58mm

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

    // Header Toko (Center)
    receipt += padCenter(data.storeName) + "\n";
    receipt += padCenter(data.storeAddress) + "\n";
    if (data.cashierName) {
      receipt += padCenter(`Kasir: ${data.cashierName}`) + "\n";
    }
    receipt += "-".repeat(LINE_WIDTH) + "\n";

    // Daftar Item
    data.items.forEach((item) => {
      const itemTitle = `${item.qty}x ${item.name}`;
      const itemSubTotal = formatRupiah(item.price * item.qty);
      
      // Jika nama item terlalu panjang, turunkan baris
      if (itemTitle.length > 18) {
        receipt += itemTitle + "\n";
        receipt += padRightLeft("  @", itemSubTotal) + "\n";
      } else {
        receipt += padRightLeft(itemTitle, itemSubTotal) + "\n";
      }
    });

    receipt += "-".repeat(LINE_WIDTH) + "\n";

    // Total
    receipt += padRightLeft("TOTAL", formatRupiah(data.total)) + "\n";
    receipt += "-".repeat(LINE_WIDTH) + "\n";

    // Footer (Center)
    const footer = data.footerMessage || "Terima Kasih Telah Berbelanja!";
    receipt += padCenter(footer) + "\n";

    return receipt;
  };

  return {
    generateReceiptText
  };
}