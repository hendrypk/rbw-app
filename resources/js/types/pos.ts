export interface MenuPrice {
    channel: string;
    selling_price: number | string;
    is_active: boolean;
}

export interface MenuItem {
    id: string;
    name: string;
    category_id: string;
    image_path?: string;
    prices: MenuPrice[];
    category?: {
        id: string;
        name: string;
    };
}

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