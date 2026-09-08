<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PosController extends Controller
{
    /**
     * Display the outlet selection page before entering POS.
     */
    public function selectOutlet(Request $request)
    {
        // Retrieve active outlets related to the currently logged-in user
        $outlets = $request->user()->outlets()->where('is_active', true)->get();

        return Inertia::render('posPage/SelectOutlet', [
            'outlets' => $outlets
        ]);
    }

    public function index(Request $request)
    {
        $outlets = $request->user()->outlets()->where('is_active', true)->get();

        return Inertia::render('posPage/Index', [
            'outlets' => $outlets, // ⬅️ Pastikan baris ini ADA
            'activeOutletId' => $request->header('X-Outlet-ID') ?? session('active_outlet_id')
        ]);
    }

    /**
     * Display the list of pending/unpaid orders.
     */
    public function orders(Request $request)
    {
        $orders = Order::with('items.menu')
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
     * Display the history of successful paid invoices.
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

    public function settings(Request $request)
    {
        return Inertia::render('posPage/Settings');
    }

}