<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getSummary(Request $request): JsonResponse
    {
        $outletId = $request->header('X-Outlet-ID') ?? $request->input('outlet_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Pastikan relasi 'items' sudah di-eager load di dalam getDashboardSummary agar tidak N+1
        $orders = Order::getDashboardSummary($outletId, $startDate, $endDate);

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

        // --- 1. Rata-rata Nilai Transaksi (AOV) ---
        $averageOrderValue = $totalNota > 0 ? $totalOmzet / $totalNota : 0;

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

        // --- 2. Tren Penjualan Harian (Untuk Line / Bar Chart) ---
        $salesTrend = $orders->groupBy(function ($order) {
            return Carbon::parse($order->created_at)->format('Y-m-d');
        })->sortKeys()->map(function ($group, $date) {
            return [
                'date'               => $date,
                'total_transactions' => $group->count(),
                'total_items'        => $group->sum(function ($order) {
                    return $order->items->sum('quantity');
                }),
                'total_revenue'      => $group->sum('final_total')
            ];
        })->values();

        // --- 3. Distribusi Metode Pembayaran (Untuk Pie / Donut Chart) ---
        $paymentChart = [
            [
                'method' => 'Cash',
                'total_revenue' => $omzetCash,
                'total_transactions' => $notaCash
            ],
            [
                'method' => 'QRIS',
                'total_revenue' => $omzetQris,
                'total_transactions' => $notaQris
            ]
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'shift_started_at'    => null,
                'total_omzet'         => $totalOmzet,
                'omzet_cash'          => $omzetCash,
                'nota_cash'           => $notaCash,
                'items_cash'          => $itemsCash,
                'omzet_qris'          => $omzetQris,
                'nota_qris'           => $notaQris,
                'items_qris'          => $itemsQris,
                'total_nota'          => $totalNota,
                'total_items'         => $totalItems,
                'net_profit'          => $netProfit,
                'profit_margin'       => round($profitMargin, 1),
                'average_order_value' => round($averageOrderValue, 0),
                'sold_products'       => $soldProducts,
                'peak_hours'          => $peakHours,
                'sales_trend'         => $salesTrend,
                'payment_chart'       => $paymentChart
            ]
        ]);
    }

    /**
     * Endpoint untuk Laporan Laba Rugi (Profit and Loss)
     */
    public function getProfitAndLoss(Request $request): JsonResponse
    {
        $outletId = $request->header('X-Outlet-ID') ?? $request->input('outlet_id');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // 1. Ambil data account_mappings yang aktif untuk outlet ini (atau global)
        $mappings = DB::table('account_mappings')
            ->when($outletId && $outletId !== 'all', function ($query) use ($outletId) {
                $query->where('outlet_id', $outletId);
            })
            ->get();

        // 2. Kumpulkan ID akun prioritas menggunakan Konstanta Model AccountMapping
        $mappedSalesAccountIds = $mappings->whereIn('transaction_type', [
                AccountMapping::TYPE_POS_REVENUE_CASH,
                AccountMapping::TYPE_POS_REVENUE_QRIS
            ])
            ->pluck('credit_account_id') // Pendapatan kas & qris ambil dari kredit
            ->merge(
                $mappings->whereIn('transaction_type', [
                    AccountMapping::TYPE_POS_SALES_DISCOUNT,
                    AccountMapping::TYPE_POS_SALES_RETURN
                ])->pluck('debit_account_id') // Diskon & retur ambil dari debet
            )
            ->filter()
            ->unique()
            ->toArray();

        $mappedHppAccountIds = $mappings->where('transaction_type', AccountMapping::TYPE_POS_SALES_HPP)
            ->pluck('debit_account_id') // HPP ambil dari debet
            ->filter()
            ->unique()
            ->toArray();

        $mappedExpenseAccountIds = $mappings->whereIn('transaction_type', [
                AccountMapping::TYPE_POS_MDR_FEE,
                AccountMapping::TYPE_POS_TAX_VAT,
                AccountMapping::TYPE_INVENTORY_WASTE,
                AccountMapping::TYPE_LOYALTY_REDEMPTION
            ])
            ->pluck('debit_account_id') // Biaya-biaya, tax vat, waste, loyalty ambil dari debet
            ->filter()
            ->unique()
            ->toArray();
        // 3. Helper universal untuk memetakan akun dengan memprioritaskan ID dari mapping
        $fetchAccountsWithPriority = function ($categories, $priorityAccountIds = []) use ($outletId, $startDate, $endDate) {
            $accounts = Account::whereIn('category', $categories)
                ->where('is_active', true)
                ->when($outletId && $outletId !== 'all', function ($q) use ($outletId) {
                    $q->where('outlet_id', $outletId);
                })
                ->get();

            $priorityAccounts = $accounts->whereIn('id', $priorityAccountIds);
            $otherAccounts = $accounts->whereNotIn('id', $priorityAccountIds);

            return $priorityAccounts->concat($otherAccounts)->map(function ($account) use ($outletId, $startDate, $endDate) {
                $net = DB::table('journal_items')
                    ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_items.account_id', $account->id)
                    ->when($outletId && $outletId !== 'all', fn($q) => $q->where('journal_entries.outlet_id', $outletId))
                    ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
                    ->select(
                        DB::raw("SUM(CASE WHEN journal_items.type = 'credit' THEN journal_items.amount ELSE 0 END) as total_credit"),
                        DB::raw("SUM(CASE WHEN journal_items.type = 'debit' THEN journal_items.amount ELSE 0 END) as total_debit")
                    )
                    ->first();

                $credit = (float) ($net->total_credit ?? 0);
                $debit = (float) ($net->total_debit ?? 0);

                // Kategori 4 & 6 bersaldo normal Kredit, sisanya Debit
                $isCreditNormal = in_array($account->category, ['4', '6']);
                $netVal = $isCreditNormal ? ($credit - $debit) : ($debit - $credit);

                return [
                    'account_id' => $account->id,
                    'net' => $netVal,
                    'account' => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'ref_code' => $account->code ?? $account->account_number,
                        'currency_id' => $account->currency_id ?? null,
                        'parent_id' => $account->parent_id ?? null,
                        'is_parent' => $account->is_parent ?? 0,
                        'formatted' => "({$account->code}) {$account->name}"
                    ]
                ];
            })->values();
        };

        // 4. Eksekusi pengelompokan akun
        $sales = $fetchAccountsWithPriority(['6'], $mappedSalesAccountIds);
        $costOfSales = $fetchAccountsWithPriority(['7'], $mappedHppAccountIds);
        $expenses = $fetchAccountsWithPriority(['8'], $mappedExpenseAccountIds);

        $totalSales = $sales->sum('net');
        $totalCos = $costOfSales->sum('net');
        $grossProfit = $totalSales - $totalCos;
        $totalExpenses = $expenses->sum('net');
        $netProfit = $grossProfit - $totalExpenses;

        return response()->json([
            'success' => true,
            'data' => [
                'data' => [
                    'sales' => $sales,
                    'other_revenue' => [],
                    'cost_of_sales' => $costOfSales,
                    'expenses' => $expenses,
                    'other_expenses' => []
                ],
                'total' => [
                    'trading_income' => $totalSales,
                    'cost_of_sales' => $totalCos,
                    'gross_profit' => $grossProfit,
                    'expenses' => $totalExpenses,
                    'net_profit' => $netProfit
                ]
            ],
            'message' => 'Laporan berhasil diambil'
        ]);
    }
}
