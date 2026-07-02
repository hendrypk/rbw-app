<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index ()
    {
        return Inertia::render('posPage/Index');
    }

    /**
     * Tampilkan Halaman Daftar Pesanan Tertunda (Unpaid)
     */
    public function orders(Request $request)
    {
        $orders = Order::with('items.menu') // Sesuaikan nama relasi item & menu Anda
            ->where('status', 'unpaid')
            ->when($request->search, function ($query, $search) {
                $query->where('order_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('POS/Orders', [
            'orders' => $orders,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Tampilkan Halaman Riwayat Invoice Sukses (Paid)
     */
    public function invoices(Request $request)
    {
        $invoices = Order::with('items.menu')
            ->where('status', 'paid')
            ->when($request->search, function ($query, $search) {
                $query->where('order_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('POS/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only(['search'])
        ]);
    }

    public function transactions(Request $request)
    {
        return Inertia::render('posPage/Transaction');
    }
}
