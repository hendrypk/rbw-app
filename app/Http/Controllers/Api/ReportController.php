<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
public function getSummary(Request $request): JsonResponse
    {
        $outletId = $request->header('X-Outlet-ID') ?? $request->input('outlet_id');

        // Panggil method dari Model Order
        $orders = Order::getDashboardSummary($outletId);

        $omzetCash = $orders->where('payment_method', 'cash')->sum('final_total');
        $omzetQris = $orders->where('payment_method', 'qris')->sum('final_total');
        $totalOmzet = $omzetCash + $omzetQris;

        $notaCash = $orders->where('payment_method', 'cash')->count();
        $itemsCash = $orders->where('payment_method', 'cash')->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        $notaQris = $orders->where('payment_method', 'qris')->count();
        $itemsQris = $orders->where('payment_method', 'qris')->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        $totalNota = $orders->count();
        $totalItems = $orders->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        $totalHpp = $orders->sum('total_hpp');
        $totalOverhead = $orders->sum('total_overhead');
        $netProfit = $totalOmzet - ($totalHpp + $totalOverhead);
        $profitMargin = $totalOmzet > 0 ? ($netProfit / $totalOmzet) * 100 : 0;

        $orderIds = $orders->pluck('id');
        $soldProducts = collect();
        
        if ($orderIds->isNotEmpty()) {
            $soldProducts = OrderItem::whereIn('order_id', $orderIds)
                ->with('menu')
                ->get()
                ->groupBy('menu_id')
                ->map(function ($items) {
                    $firstItem = $items->first();
                    return [
                        'item_name'     => $firstItem->menu->name ?? 'Menu Tidak Ditemukan',
                        'total_qty'     => $items->sum('quantity'),
                        'total_revenue' => $items->sum('subtotal'),
                    ];
                })
                ->sort(function ($a, $b) {
                    if ($b['total_qty'] !== $a['total_qty']) {
                        return $b['total_qty'] <=> $a['total_qty'];
                    }
                    return $b['total_revenue'] <=> $a['total_revenue'];
                })
                ->values();
        }

        $peakHours = $orders->groupBy(function ($order) {
            return Carbon::parse($order->created_at)->format('H:00');
        })->map(function ($group) {
            return [
                'total_transactions' => $group->count(),
                'total_omzet'        => $group->sum('final_total')
            ];
        })->sortKeys();

        return response()->json([
            'success' => true,
            'data'    => [
                'shift_started_at' => null,
                'total_omzet'      => $totalOmzet,
                'omzet_cash'       => $omzetCash,
                'nota_cash'        => $notaCash,
                'items_cash'       => $itemsCash,
                'omzet_qris'       => $omzetQris,
                'nota_qris'        => $notaQris,
                'items_qris'       => $itemsQris,
                'total_nota'       => $totalNota,
                'total_items'      => $totalItems,
                'net_profit'       => $netProfit,
                'profit_margin'    => round($profitMargin, 1),
                'sold_products'    => $soldProducts,
                'peak_hours'       => $peakHours
            ]
        ]);
    }
}
