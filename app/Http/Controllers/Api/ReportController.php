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

        $getAccountMutations = function ($categoryCode) use ($outletId, $startDate, $endDate) {
            $accounts = Account::where('category', $categoryCode)
                ->where('is_active', true)
                ->get();

            $items = [];
            $totalCategoryBalance = 0;

            foreach ($accounts as $account) {
                $mutations = DB::table('journal_items')
                    ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_items.account_id', $account->id)
                    ->when($outletId && $outletId !== 'all', function ($query) use ($outletId) {
                        $query->where('journal_entries.outlet_id', $outletId);
                    })
                    ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
                    ->select(
                        'journal_items.type',
                        DB::raw('SUM(journal_items.amount) as total_amount')
                    )
                    ->groupBy('journal_items.type')
                    ->get();

                $totalDebit = 0;
                $totalCredit = 0;

                foreach ($mutations as $mut) {
                    if ($mut->type === 'debit') {
                        $totalDebit = (float) $mut->total_amount;
                    } elseif ($mut->type === 'credit') {
                        $totalCredit = (float) $mut->total_amount;
                    }
                }

                $balance = 0;
                if ($categoryCode === '2') {
                    $balance = $totalCredit - $totalDebit;
                } else if ($categoryCode === '5') {
                    $balance = $totalDebit - $totalCredit;
                }

                if ($balance != 0 || $totalDebit != 0 || $totalCredit != 0) {
                    $items[] = [
                        'code'    => $account->code,
                        'name'    => $account->name,
                        'balance' => $balance,
                    ];
                    $totalCategoryBalance += $balance;
                }
            }

            return [
                'items' => $items,
                'total' => $totalCategoryBalance
            ];
        };

        $revenues = $getAccountMutations('2');

        // 🌟 Ambil Akun HPP secara Dinamis dari account_mappings (pos_sales_hpp)
        $hppMapping = AccountMapping::where('transaction_type', 'pos_sales_hpp')
            ->when($outletId && $outletId !== 'all', function ($query) use ($outletId) {
                $query->where('outlet_id', $outletId);
            })
            ->first();

        // Ambil debit_account_id sebagai akun HPP[cite: 3]
        $hppAccountId = $hppMapping?->debit_account_id;
        $hppAccount = $hppAccountId ? Account::find($hppAccountId) : null;

        // Fallback manual jika mapping belum ada di database
        if (!$hppAccount) {
            $hppAccount = Account::where('code', '5-5000')->first();
            $hppAccountId = $hppAccount?->id;
        }

        $totalHpp = 0;
        if ($hppAccount) {
            $hppMutations = DB::table('journal_items')
                ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_items.account_id', $hppAccount->id)
                ->when($outletId && $outletId !== 'all', function ($query) use ($outletId) {
                    $query->where('journal_entries.outlet_id', $outletId);
                })
                ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
                ->select(
                    'journal_items.type',
                    DB::raw('SUM(journal_items.amount) as total_amount')
                )
                ->groupBy('journal_items.type')
                ->get();

            $hppDebit = 0;
            $hppCredit = 0;
            foreach ($hppMutations as $mut) {
                if ($mut->type === 'debit') $hppDebit = (float) $mut->total_amount;
                if ($mut->type === 'credit') $hppCredit = (float) $mut->total_amount;
            }
            $totalHpp = $hppDebit - $hppCredit;
        }

        $grossProfit = $revenues['total'] - $totalHpp;
        $grossMargin = $revenues['total'] > 0 ? ($grossProfit / $revenues['total']) * 100 : 0;

        // 🌟 Ambil Beban Operasional dengan mengecualikan akun HPP yang didapat dari mapping
        $rawExpenses = Account::where('category', '5')
            ->when($hppAccountId, function ($query) use ($hppAccountId) {
                $query->where('id', '!=', $hppAccountId);
            })
            ->where('is_active', true)
            ->get();

        $expenseItems = [];
        $totalExpense = 0;

        foreach ($rawExpenses as $account) {
            $mutations = DB::table('journal_items')
                ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_items.account_id', $account->id)
                ->when($outletId && $outletId !== 'all', function ($query) use ($outletId) {
                    $query->where('journal_entries.outlet_id', $outletId);
                })
                ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
                ->select(
                    'journal_items.type',
                    DB::raw('SUM(journal_items.amount) as total_amount')
                )
                ->groupBy('journal_items.type')
                ->get();

            $expDebit = 0;
            $expCredit = 0;
            foreach ($mutations as $mut) {
                if ($mut->type === 'debit') $expDebit = (float) $mut->total_amount;
                if ($mut->type === 'credit') $expCredit = (float) $mut->total_amount;
            }

            $balance = $expDebit - $expCredit;

            if ($balance != 0) {
                $expenseItems[] = [
                    'code'    => $account->code,
                    'name'    => $account->name,
                    'balance' => $balance,
                ];
                $totalExpense += $balance;
            }
        }

        $netProfit = $grossProfit - $totalExpense;
        $netMargin = $revenues['total'] > 0 ? ($netProfit / $revenues['total']) * 100 : 0;
        $opexRatio = $revenues['total'] > 0 ? ($totalExpense / $revenues['total']) * 100 : 0;

        return response()->json([
            'success' => true,
            'message' => 'Laporan Laba Rugi berhasil dimuat',
            'data'    => [
                'revenues' => [
                    'items' => $revenues['items'],
                    'total' => $revenues['total'],
                ],
                'cogs' => [
                    'total' => $totalHpp,
                ],
                'gross_profit' => [
                    'amount' => $grossProfit,
                    'margin' => round($grossMargin, 1),
                ],
                'expenses' => [
                    'items' => $expenseItems,
                    'total' => $totalExpense,
                ],
                'net_profit' => [
                    'amount' => $netProfit,
                    'margin' => round($netMargin, 1),
                ],
                'ratios' => [
                    'gross_margin' => round($grossMargin, 1),
                    'net_margin' => round($netMargin, 1),
                    'opex_ratio' => round($opexRatio, 1),
                ]
            ]
        ]);
    }
}
