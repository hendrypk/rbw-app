<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DokuQrisService;
use App\Services\DokuAuthService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class QrisController extends Controller
{
    protected $qrisService;

    public function __construct(DokuQrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    /**
     * Endpoint khusus untuk dump full request & response DOKU (Debugging)
     */
    public function debugGenerate(Request $request)
    {
        $orderNumber = $request->input('order_number', 'INV-TEST-' . time());
        $amount      = $request->input('amount', 10000);

        try {
            // 1. Ambil Auth Token
            $auth = app(DokuAuthService::class)->getToken();
            $token = is_array($auth) ? $auth['accessToken'] : $auth;

            // 2. Persiapkan Data Request (Menggunakan config() agar aman dari config:cache)
            $timestamp  = Carbon::now('UTC')->toIso8601ZuluString(); 
            $endpoint   = "/snap-adapter/b2b/v1.0/qr/qr-mpm-generate";
            $clientId   = config('services.doku.client_id', env('DOKU_CLIENT_ID'));
            $secretKey  = config('services.doku.secret_key', env('DOKU_SECRET_KEY'));
            $merchantId = config('services.doku.merchant_id', env('DOKU_MERCHANT_ID'));
            $baseUrl    = rtrim(config('services.doku.base_url', env('DOKU_BASE_URL', 'https://api.doku.com')), '/');

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

            // 3. Hitung Symmetric Signature (HMAC-SHA512)
            $bodyJson     = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $digest       = strtolower(hash('sha256', $bodyJson)); 
            $stringToSign = "POST:" . $endpoint . ":" . $token . ":" . $digest . ":" . $timestamp;
            $signatureRaw = hash_hmac('sha512', $stringToSign, $secretKey, true);
            $signature    = base64_encode($signatureRaw);

            // 4. Hit API DOKU
            $response = Http::withHeaders([
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
                    'client_id_used'      => $clientId,
                    'merchant_id_used'    => $merchantId,
                    'base_url'            => $baseUrl,
                    'timestamp'           => $timestamp,
                    'string_to_sign'      => $stringToSign,
                    'generated_signature' => $signature,
                    'payload_sent'        => $body,
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
     * Generate QRIS String / URL dari DOKU berdasarkan order_number
     */
    public function generate(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'amount'       => 'required|numeric'
        ]);

        $order = Order::where('order_number', $request->order_number)->firstOrFail();

        $dokuResponse = $this->qrisService->generate($order->order_number, $order->final_total);

        // KODE SUKSES QRIS DOKU ADALAH 2004700 (Atau 2000000)
        if (isset($dokuResponse['responseCode']) && in_array($dokuResponse['responseCode'], ['2004700', '2000000'])) {
            
            // Simpan reference_no dari DOKU ke DB jika ada
            if (isset($dokuResponse['referenceNo'])) {
                $order->update(['doku_reference_no' => $dokuResponse['referenceNo']]);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_number' => $order->order_number,
                    'reference_no' => $dokuResponse['referenceNo'] ?? null,
                    'qr_content'   => $dokuResponse['qrContent'] ?? null,
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
            'order_number' => 'required|string'
        ]);

        $order = Order::where('order_number', $request->order_number)->firstOrFail();

        $status = $this->qrisService->queryStatus($order->order_number, $order->doku_reference_no ?? '');

        $isPaid = isset($status['latestTransactionStatus']) && $status['latestTransactionStatus'] === 'SUCCESS';

        if ($isPaid && $order->status !== 'paid') {
            $order->update([
                'status' => 'paid', 
                'payment_method' => 'qris'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'paid'   => $isPaid,
            'payment_status' => $isPaid ? 'completed' : 'pending',
            'message'        => $status['transactionStatusDesc'] ?? 'Menunggu pembayaran'
        ]);
    }

    /**
     * Test B2B Access Token
     */
    public function testGetToken()
    {
        try {
            $tokenData = app(DokuAuthService::class)->getToken();
            return response()->json([
                'status' => 'success',
                'data'   => $tokenData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}