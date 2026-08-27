<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class VoucherController extends Controller
{
    /**
     * Ambil daftar semua voucher beserta menu terkait (untuk halaman manajemen).
     */
    public function index(): JsonResponse
    {
        $vouchers = Voucher::with('menus')->latest()->get();
        
        return response()->json([
            'success' => true,
            'data'    => $vouchers
        ]);
    }

    /**
     * Buat voucher baru (Mendukung permanent, waktu manual, & menu spesifik).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code'         => 'required|string|unique:vouchers,code|max:50',
            'name'         => 'required|string|max:100',
            'type'         => 'required|string|in:fixed,percentage',
            'value'        => 'required|numeric|min:0',
            'min_spend'    => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit'  => 'nullable|integer|min:1',
            'started_at'   => 'nullable|date',
            'expired_at'   => 'nullable|date|after_or_equal:started_at',
            'menu_ids'     => 'nullable|array', // Array UUID menu spesifik (opsional)
            'menu_ids.*'   => 'exists:menus,id',
            'is_active'    => 'boolean',
        ]);

        $voucher = Voucher::create([
            'code'         => strtoupper($request->code),
            'name'         => $request->name,
            'type'         => $request->type,
            'value'        => $request->value,
            'min_spend'    => $request->min_spend ?? 0,
            'max_discount' => $request->max_discount,
            'usage_limit'  => $request->usage_limit,
            'started_at'   => $request->started_at, // Null = Berlaku langsung/permanent dari awal
            'expired_at'   => $request->expired_at, // Null = Permanent selamanya
            'is_active'    => $request->is_active ?? true,
        ]);

        // Jika menu_ids dikirim, hubungkan voucher ke menu-menu tersebut
        if (!empty($request->menu_ids)) {
            $voucher->menus()->sync($request->menu_ids);
        }

        return response()->json([
            'success' => true, 
            'message' => 'Voucher berhasil dibuat.', 
            'data'    => $voucher->load('menus')
        ], 201);
    }

    /**
     * Validasi & Hitung Potongan Voucher saat Kasir Input di POS.
     */
    public function validateVoucher(Request $request): JsonResponse
    {
        $request->validate([
            'code'             => 'required|string',
            'items'            => 'required|array|min:1', // Item di keranjang kasir
            'items.*.menu_id'  => 'required|exists:menus,id',
            'items.*.subtotal' => 'required|numeric|min:0',
        ]);

        $voucher = Voucher::with('menus')->where('code', strtoupper($request->code))->first();

        // Cek Keberadaan & Status Aktif Voucher
        if (!$voucher || !$voucher->is_active) {
            return response()->json(['success' => false, 'message' => 'Kode voucher tidak valid atau tidak aktif.'], 422);
        }

        $now = Carbon::now();

        // 1. Cek Waktu Mulai (Hanya jika started_at diisi / tidak permanent)
        if ($voucher->started_at && $now->lessThan($voucher->started_at)) {
            return response()->json(['success' => false, 'message' => 'Voucher ini belum mulai berlaku.'], 422);
        }

        // 2. Cek Masa Kedaluwarsa (Hanya jika expired_at diisi / tidak permanent)
        if ($voucher->expired_at && $now->greaterThan($voucher->expired_at)) {
            return response()->json(['success' => false, 'message' => 'Voucher sudah kedaluwarsa.'], 422);
        }

        // 3. Cek Batas Total Penggunaan (Usage Limit)
        if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) {
            return response()->json(['success' => false, 'message' => 'Kuota penggunaan voucher sudah habis.'], 422);
        }

        // 4. Hitung Subtotal Berdasarkan Menu yang Memenuhi Syarat
        $eligibleSubtotal = 0;
        $restrictedMenuIds = $voucher->menus->pluck('id')->toArray(); // Ambil daftar menu khusus voucher

        foreach ($request->items as $cartItem) {
            // Jika voucher dibatasi menu tertentu DAN menu item ini tidak ada di daftar voucher
            if (!empty($restrictedMenuIds) && !in_array($cartItem['menu_id'], $restrictedMenuIds)) {
                continue; // Lewati item ini (tidak dihitung sebagai dasar diskon)
            }
            $eligibleSubtotal += floatval($cartItem['subtotal']);
        }

        // Jika tidak ada satupun menu di keranjang yang cocok dengan ketentuan voucher
        if ($eligibleSubtotal <= 0) {
            return response()->json([
                'success' => false, 
                'message' => 'Voucher tidak dapat digunakan karena tidak ada menu yang sesuai di keranjang.'
            ], 422);
        }

        // 5. Cek Minimum Belanja (Berdasarkan total subtotal menu yang valid)
        if ($eligibleSubtotal < $voucher->min_spend) {
            return response()->json([
                'success' => false, 
                'message' => "Minimum belanja menu yang berhak diskon adalah Rp " . number_format($voucher->min_spend, 0, ',', '.')
            ], 422);
        }

        // 6. Kalkulasi Besar Diskon
        $discountAmount = 0;
        if ($voucher->type === 'fixed') {
            $discountAmount = floatval($voucher->value);
        } else {
            // Tipe Persentase (%)
            $discountAmount = $eligibleSubtotal * (floatval($voucher->value) / 100);
            
            // Batasi dengan max_discount jika diatur
            if ($voucher->max_discount !== null && $discountAmount > floatval($voucher->max_discount)) {
                $discountAmount = floatval($voucher->max_discount);
            }
        }

        // Pastikan diskon tidak melebihi subtotal item yang berhak
        if ($discountAmount > $eligibleSubtotal) {
            $discountAmount = $eligibleSubtotal;
        }

        return response()->json([
            'success' => true,
            'message' => 'Voucher berhasil diterapkan!',
            'data'    => [
                'voucher_id'      => $voucher->id,
                'code'            => $voucher->code,
                'name'            => $voucher->name,
                'type'            => $voucher->type,
                'value'           => $voucher->value,
                'discount_amount' => round($discountAmount, 2),
            ]
        ]);
    }

    /**
     * Hapus Voucher.
     */
    public function destroy(string $id): JsonResponse
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->delete();

        return response()->json([
            'success' => true, 
            'message' => 'Voucher berhasil dihapus.'
        ]);
    }
}