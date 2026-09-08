<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CashierShiftController extends Controller
{
    // Cek status shift aktif pada outlet tertentu
    public function checkActiveShift(Request $request): JsonResponse
    {
        $outletId = $request->header('X-Outlet-ID') ?? session('active_outlet_id');

        $activeShift = CashierShift::where('outlet_id', $outletId)
            ->where('status', 'open')
            ->with('user')
            ->first();

        return response()->json([
            'success' => true,
            'has_active_shift' => (bool) $activeShift,
            'data' => $activeShift
        ]);
    }

    // Buka Shift Baru (Open Cashier)
    public function openShift(Request $request): JsonResponse
    {
        $request->validate([
            'starting_cash' => 'required|numeric|min:0',
            'notes'         => 'nullable|string'
        ]);

        $outletId = $request->header('X-Outlet-ID') ?? session('active_outlet_id');

        // Pastikan belum ada shift aktif di outlet ini
        $existingShift = CashierShift::where('outlet_id', $outletId)
            ->where('status', 'open')
            ->first();

        if ($existingShift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift kasir sudah dibuka sebelumnya untuk outlet ini.'
            ], 422);
        }

        $shift = CashierShift::create([
            'outlet_id'     => $outletId,
            'user_id'       => auth()->id(),
            'starting_cash' => $request->starting_cash,
            'status'        => 'open',
            'notes'         => $request->notes,
            'opened_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shift kasir berhasil dibuka.',
            'data'    => $shift
        ], 201);
    }

    // Tutup Shift (Close Cashier / Reconciliation)
    public function closeShift(Request $request, CashierShift $shift): JsonResponse
    {
        $request->validate([
            'actual_cash' => 'required|numeric|min:0',
            'notes'       => 'nullable|string'
        ]);

        // Hitung total pemasukan cash selama shift ini berlangsung
        $cashSales = Order::where('outlet_id', $shift->outlet_id)
            ->where('payment_method', 'cash')
            ->where('status', 'paid')
            ->where('created_at', '>=', $shift->opened_at)
            ->sum('final_total');

        $expectedCash = $shift->starting_cash + $cashSales;
        $actualCash = floatval($request->actual_cash);
        $difference = $actualCash - $expectedCash;

        $shift->update([
            'actual_cash'   => $actualCash,
            'expected_cash' => $expectedCash,
            'difference'    => $difference,
            'status'        => 'closed',
            'notes'         => $request->notes,
            'closed_at'     => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shift kasir berhasil ditutup.',
            'data'    => $shift
        ]);
    }
}