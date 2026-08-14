<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    /**
     * Registrasi Kustomer Baru
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
            'email' => 'required|email|max:255|unique:customers,email',
            'password' => 'required|string|min:6|confirmed', 
            'shipping_address' => 'nullable|string',
        ]);

        $customer = Customer::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'shipping_address' => $request->shipping_address,
        ]);

        // Generate token Sanctum setelah berhasil register
        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registrasi berhasil!',
            'data' => [
                'customer' => $customer,
                'token' => $token,
            ]
        ], 201);
        }

    /**
     * Login Kustomer (Menggunakan Nomor HP atau Email)
     */
public function login(Request $request)
{
    $request->validate([
        'login' => 'required',
        'password' => 'required',
    ]);

    $customer = \App\Models\Customer::where('email', $request->login)
                ->orWhere('phone', $request->login)
                ->first();

    if (! $customer || ! \Illuminate\Support\Facades\Hash::check($request->password, $customer->password)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Kredensial yang Anda masukkan salah.'
        ], 422);
    }

    // --- GUNAKAN GUARD 'customer' SECARA EKSPLISIT ---
    Auth::guard('customer')->login($customer);
    $request->session()->regenerate();
    // ------------------------------------------------

    return response()->json([
        'status' => 'success',
        'message' => 'Login berhasil',
        'data' => [
            'customer' => $customer
        ]
    ]);
}

    /**
     * Get Profile Kustomer yang Sedang Login
     */
    public function profile(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()
        ]);
    }

    /**
     * Logout Kustomer (Hapus Token)
     */
    public function logout(Request $request)
    {
        // Logout dari session guard
        Auth::guard('web')->logout();

        // Invalidate session dan regenerate token CSRF yang baru
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout.'
        ]);
    }
}