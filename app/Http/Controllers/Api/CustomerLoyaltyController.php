<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\Voucher;

class CustomerLoyaltyController extends Controller
{
    public function leaderboard()
    {
        $topCustomers = Customer::select('id', 'name', 'total_points')
            ->orderByDesc('total_points')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $topCustomers
        ]);
    }

    public function myLoyaltyProfile(Request $request)
    {
        $customer = auth('customer')->user();
        
        $history = CustomerPoint::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_points' => $customer->total_points,
                'history'      => $history
            ]
        ]);
    }

    // Daftar voucher eksklusif yang bisa di-redeem pakai poin
    public function availableRedemptions()
    {
        $redemptions = Voucher::where('is_active', true)
            ->where('is_redeemable', true)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $redemptions
        ]);
    }

    public function redeemVoucher(Request $request)
    {
        // ⬅️ Ubah validasi dari redemption_id ke voucher_id
        $request->validate([
            'voucher_id' => 'required|uuid|exists:vouchers,id'
        ]);
        
        $customer = auth('customer')->user();
        
        // ⬅️ Langsung cari dari tabel Voucher
        $voucher = Voucher::where('is_redeemable', true)->findOrFail($request->voucher_id);

        if ($customer->total_points < $voucher->points_required) {
            return response()->json([
                'success' => false, 
                'message' => 'Poin Anda tidak mencukupi untuk penukaran ini.'
            ], 422);
        }

        $customer->decrement('total_points', $voucher->points_required);

        CustomerPoint::create([
            'customer_id' => $customer->id,
            'points'      => -$voucher->points_required,
            'type'        => 'redeemed',
            'description' => "Redeem voucher eksklusif: {$voucher->name} ({$voucher->code})" // ⬅️ Gunakan name & code voucher
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil menukar poin dengan voucher!',
            'remaining_points' => $customer->total_points
        ]);
    }
}