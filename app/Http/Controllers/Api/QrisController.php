<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DokuQrisService;
use Illuminate\Http\Request;

class QrisController extends Controller
{
    protected $qrisService;

    public function __construct(DokuQrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    /**
     * Generate QRIS String / URL dari DOKU
     */
public function generate(Request $request)
{
    $request->validate([
        'amount' => 'required|numeric'
    ]);

    $invoiceNo = 'INV-' . time();

    $result = app(\App\Services\DokuQrisService::class)
        ->generate($invoiceNo, $request->amount);

    return response()->json($result);
}

    /**
     * Membuat Transaksi Baru dan Generate QRIS string
     */
    public function processQrisPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
        ]);

        // 1. Simpan pesanan ke database Anda dengan status 'PENDING'
        $invoiceNo = 'INV-' . time();
        
        // 2. Tembak Service QRIS DOKU
        $dokuResponse = $this->qrisService->generate($invoiceNo, $request->amount);

        if (isset($dokuResponse['responseCode']) && $dokuResponse['responseCode'] === '2000000') {
            return response()->json([
                'success' => true,
                'invoice_no' => $invoiceNo,
                'reference_no' => $dokuResponse['referenceNo'],
                'qr_content' => $dokuResponse['qrContent'], // String QRIS payload raw text
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $dokuResponse['responseMessage'] ?? 'Gagal membuat QRIS ke payment gateway.'
        ], 400);
    }

    /**
     * Endpoint untuk di-cek berkala (polling) oleh Vue.js
     */
    public function checkStatus(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required',
            'reference_no' => 'required'
        ]);

        $status = $this->qrisService->queryStatus($request->invoice_no, $request->reference_no);

        // Berdasarkan spek: cek field latestTransactionStatus
        // Biasanya bernilai 'SUCCESS', 'FAILED', atau 'PENDING'
        return response()->json([
            'status' => $status['latestTransactionStatus'] ?? 'PENDING',
            'message' => $status['transactionStatusDesc'] ?? 'Menunggu pembayaran'
        ]);
    }
}
