<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DokuQrisService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            // 1. Ambil Auth Token
            $auth = app(\App\Services\DokuAuthService::class)->getToken();
            $token = $auth['accessToken'] ?? null;

            // 2. Persiapkan Data Request
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

            // 3. Hitung Signature
            $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $digest = strtolower(hash('sha256', $bodyJson)); 
            $stringToSign = "POST:" . $endpoint . ":" . $token . ":" . $digest . ":" . $timestamp;
            $signatureRaw = hash_hmac('sha512', $stringToSign, $secretKey, true);
            $signature = base64_encode($signatureRaw);

            // 4. Hit API DOKU
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

            // Dump Hasil Lengkap
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
public function generate(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'amount'       => 'required|numeric'
        ]);

        $order = Order::where('order_number', $request->order_number)->firstOrFail();

        $dokuResponse = $this->qrisService->generate($order->order_number, $order->final_total);

        // KODE SUKSES QRIS DOKU ADALAH 2004700 (Bukan 2000000)
        if (isset($dokuResponse['responseCode']) && in_array($dokuResponse['responseCode'], ['2004700', '2000000'])) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_number' => $order->order_number,
                    'reference_no' => $dokuResponse['referenceNo'] ?? null,
                    'qr_content'   => $dokuResponse['qrContent'] ?? null,
                ]
            ]);
        }

        // Tampilkan respons ASLI dari DOKU ke frontend agar kita tahu persis salahnya di mana
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

        // Panggil service query status DOKU
        // (Pastikan method queryStatus di DokuQrisService Anda sudah disesuaikan menerima order_number)
        $status = $this->qrisService->queryStatus($order->order_number, $order->doku_reference_no ?? '');

        $isPaid = isset($status['latestTransactionStatus']) && $status['latestTransactionStatus'] === 'SUCCESS';

        // Update status order jika sudah terkonfirmasi bayar oleh DOKU
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
            'message' => $status['transactionStatusDesc'] ?? 'Menunggu pembayaran'
        ]);
    }

        public function testGetToken()
    {
        // 1. Konfigurasi kredensial (Sesuaikan dengan .env Anda)
        $clientId = config('services.doku.client_id'); // contoh: MCH-0008-...
        $privateKeyPath = storage_path('app/doku/private.key'); // Path ke private key Anda
        $baseUrl = config('services.doku.sandbox_mode', true) 
            ? 'https://api-sandbox.doku.com' 
            : 'https://api.doku.com';

        $endpoint = '/authorization/v1/access-token/b2b';

        // 2. Format Timestamp ke UTC (ISO8601 UTC+0 / Z)
        // Kurangi 7 jam jika waktu server/lokal Anda WIB (UTC+7)
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        // 3. Buat StringToSign untuk Asymmetric Signature (SHA256withRSA)
        // Formula: client_ID + "|" + X-TIMESTAMP
        $stringToSign = $clientId . '|' . $timestamp;

        // 4. Generate Signature menggunakan Private Key
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

        // 5. Susun Request Body
        $body = [
            'grantType' => 'client_credentials'
        ];

        // 6. Hit API DOKU
        try {
            $response = Http::withHeaders([
                'X-TIMESTAMP'  => $timestamp,
                'X-CLIENT-KEY' => $clientId,
                'X-SIGNATURE'  => $signature,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . $endpoint, $body);

            // 7. Ambil hasil response body
            $responseBody = $response->json();
            $statusCode = $response->status();

            // Debugging langsung di browser/API client
            return response()->json([
                'http_status'     => $statusCode,
                'is_success'      => $response->successful(),
                'string_to_sign'  => $stringToSign,
                'request_headers' => [
                    'X-TIMESTAMP'  => $timestamp,
                    'X-CLIENT-KEY' => $clientId,
                    'X-SIGNATURE'  => $signature,
                ],
                'response_body'   => $responseBody,
            ], $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'error_message' => $e->getMessage(),
                'file'          => $e->getFile(),
                'line'          => $e->getLine(),
            ], 500);
        }
    }
}