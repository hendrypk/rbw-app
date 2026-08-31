<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DokuQrisService;
use App\Models\Order;
use App\Models\DokuTransaction; // <-- Jangan lupa import model DokuTransaction
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // <-- Jangan lupa import DB transaction

class QrisController extends Controller
{
    protected $qrisService;

    public function __construct(DokuQrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    /**
     * Endpoint khusus untuk dump full request & response DOKU
     */
    public function debugGenerate(Request $request)
    {
        $orderNumber = $request->input('order_number', 'INV-TEST-' . time());
        $amount = $request->input('amount', 10000);

        try {
            $auth = app(\App\Services\DokuAuthService::class)->getToken();
            $token = $auth['accessToken'] ?? null;

            $timestamp = date('c'); 
            $endpoint = "/snap-adapter/b2b/v1.0/qr/qr-mpm-generate";
            $clientId = env('DOKU_CLIENT_ID');
            $secretKey = env('DOKU_SECRET_KEY');
            $merchantId = env('DOKU_MERCHANT_ID', '2115');

            $body = [
                "partnerReferenceNo" => (string) $orderNumber,
                "amount" => [
                    "value"    => number_format($amount, 2, '.', ''),
                    "currency" => "IDR"
                ],
                "merchantId" => $merchantId,
                "terminalId" => "K45",
                "additionalInfo" => [
                    "postalCode" => "55183",
                    "feeType"    => "1"
                ]
            ];

            $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $digest = strtolower(hash('sha256', $bodyJson)); 
            $stringToSign = "POST:" . $endpoint . ":" . $token . ":" . $digest . ":" . $timestamp;
            $signatureRaw = hash_hmac('sha512', $stringToSign, $secretKey, true);
            $signature = base64_encode($signatureRaw);

            $baseUrl = rtrim(env('DOKU_BASE_URL', 'https://api-sandbox.doku.com'), '/');
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-PARTNER-ID'  => $clientId,
                'X-EXTERNAL-ID' => (string) rand(100000, 999999) . time(),
                'X-TIMESTAMP'   => $timestamp,
                'X-SIGNATURE'   => $signature,
                'CHANNEL-ID'    => 'H2H',
                'Content-Type'  => 'application/json',
            ])
            ->timeout(15)
            ->post($baseUrl . $endpoint, $body);

            return response()->json([
                'debug_info' => [
                    'client_id_used'    => $clientId,
                    'merchant_id_used'  => $merchantId,
                    'base_url'          => $baseUrl,
                    'timestamp'         => $timestamp,
                    'string_to_sign'    => $stringToSign,
                    'generated_signature' => $signature,
                    'payload_sent'      => $body,
                ],
                'doku_http_status' => $response->status(),
                'doku_response'    => $response->json() ?? $response->body()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    }

    /**
     * Generate QRIS String / URL dari DOKU berdasarkan order_number yang ada
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'order_number' => 'required|string',
            'amount'       => 'required|numeric'
        ]);

        $order = Order::where('order_number', $request->order_number)->firstOrFail();

        // 1. Cek apakah sudah ada transaksi pending yang aktif di tabel doku_transactions
        $existingTx = DokuTransaction::where('order_number', $order->order_number)
            ->where('status', 'pending')
            ->where('expired_at', '>', now())
            ->first();

        if ($existingTx && !empty($existingTx->qr_content)) {
            $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($existingTx->qr_content);
            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_number' => $order->order_number,
                    'reference_no' => $existingTx->original_reference_no,
                    'qr_content'   => $existingTx->qr_content,
                    'qr_image_url' => $qrImageUrl, 
                    'validity_period' => $existingTx->expired_at->toIso8601String(),
                ]
            ]);
        }

        // 2. Hit API DOKU menggunakan Service jika belum ada / sudah kedaluwarsa
        $dokuInvoiceNo = $order->order_number . '-' . strtoupper(substr(uniqid(), -4));
        $dokuResponse = $this->qrisService->generate($dokuInvoiceNo, $order->final_total);

        // 3. Kode sukses QRIS dari DOKU adalah 2004700 atau 2000000
        if (isset($dokuResponse['responseCode']) && in_array($dokuResponse['responseCode'], ['2004700', '2000000'])) {
            
            $qrisString = $dokuResponse['qrContent'] ?? '';
            $referenceNo = $dokuResponse['referenceNo'] ?? $dokuResponse['originalReferenceNo'] ?? null;
            $expiredAt = Carbon::now()->addMinutes(15);

            // 4. SIMPAN KE TABEL doku_transactions AGAR BISA DI-QUERY NANTINYA
            DokuTransaction::updateOrCreate(
                ['order_number' => $order->order_number],
                [
                    'original_reference_no' => $dokuInvoiceNo,
                    'amount' => $order->final_total,
                    'qr_content' => $qrisString,
                    'status' => 'pending',
                    'expired_at' => $expiredAt,
                    'raw_response' => $dokuResponse
                ]
            );

            $qrImageUrl = !empty($qrisString) 
                ? "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrisString) 
                : null;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_number' => $order->order_number,
                    'reference_no' => $referenceNo,
                    'qr_content'   => $qrisString,
                    'qr_image_url' => $qrImageUrl, 
                    'validity_period' => $expiredAt->toIso8601String(),
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'DOKU Error: ' . json_encode($dokuResponse)
        ], 400);
    }

    /**
     * Endpoint untuk di-cek berkala (polling) oleh Vue.js
     */
    public function checkStatus(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'reference_no' => 'required|string',
        ]);

        $order = Order::where('order_number', $request->order_number)->firstOrFail();
        $dokuReferenceNo = $request->reference_no;

        // Ambil data referensi DOKU dari tabel doku_transactions
        $dokuTx = DokuTransaction::where('order_number', $order->order_number)->latest()->first();

        if (!$dokuReferenceNo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Referensi transaksi DOKU tidak ditemukan untuk order ini.'
            ], 404);
        }

        // Kirim original_reference_no yang benar ke service queryStatus DOKU
        // $status = $this->qrisService->queryStatus($order->order_number, $dokuTx->original_reference_no);
        $status = $this->qrisService->queryStatus($order->order_number, $dokuReferenceNo);
        Log::info('DOKU Query Status Response:', (array) $status);

        // Sesuaikan pengecekan status sukses dari DOKU
        // $latestStatus = $status['latestTransactionStatus'] ?? $status['transaction_status'] ?? '';
        $latestStatus = (string) ($status['latestTransactionStatus'] ?? $status['responCode'] ?? $status['transaction_status'] ?? '');
        // $isPaid = strtoupper($latestStatus) === 'SUCCESS' || strtoupper($latestStatus) === '00';
        $isPaid = in_array(strtoupper(trim($latestStatus)), ['00', 'Success', 'PAID', 'SUCCESSFUL', '2005100']);
        if ($isPaid) {
            DB::transaction(function () use ($order, $dokuReferenceNo, $status) {
                if ($order->status !== 'paid') {
                    $order->update([
                        'status' => 'paid', 
                        'payment_method' => 'qris'
                    ]);
                }

                DokuTransaction::where('original_reference_no', $dokuReferenceNo)->update([
                    'status' => 'success',
                    'raw_response' => $status
                ]);
            });
        }
        return response()->json([
            'status' => 'success',
            'paid'   => $isPaid,
            'payment_status' => $isPaid ? 'completed' : 'pending',
            'message' => $status['transactionStatusDesc'] ?? 'Menunggu pembayaran'
        ]);
    }

    public function testGetToken()
    {
        $clientId = config('services.doku.client_id'); 
        $privateKeyPath = storage_path('app/doku/private.key'); 
        $baseUrl = config('services.doku.sandbox_mode', true) 
            ? 'https://api-sandbox.doku.com' 
            : 'https://api.doku.com';

        $endpoint = '/authorization/v1/access-token/b2b';
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $stringToSign = $clientId . '|' . $timestamp;

        $signature = '';
        if (file_exists($privateKeyPath)) {
            $privateKey = file_get_contents($privateKeyPath);
            openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $signature = base64_encode($signature);
        } else {
            return response()->json([
                'error' => 'Private key file not found at: ' . $privateKeyPath
            ], 500);
        }

        $body = [
            'grantType' => 'client_credentials'
        ];

        try {
            $response = Http::withHeaders([
                'X-TIMESTAMP'  => $timestamp,
                'X-CLIENT-KEY' => $clientId,
                'X-SIGNATURE'  => $signature,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . $endpoint, $body);

            return response()->json([
                'http_status'     => $response->status(),
                'is_success'      => $response->successful(),
                'string_to_sign'  => $stringToSign,
                'request_headers' => [
                    'X-TIMESTAMP'  => $timestamp,
                    'X-CLIENT-KEY' => $clientId,
                    'X-SIGNATURE'  => $signature,
                ],
                'response_body'   => $response->json(),
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error_message' => $e->getMessage(),
                'file'          => $e->getFile(),
                'line'          => $e->getLine(),
            ], 500);
        }
    }
}