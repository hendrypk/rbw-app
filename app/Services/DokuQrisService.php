<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DokuQrisService
{
    protected $clientId;
    protected $secretKey;
    protected $baseUrl;
    protected $merchantId;

    public function __construct()
    {
        $this->clientId = env('DOKU_CLIENT_ID');
        $this->secretKey = env('DOKU_SECRET_KEY');
        $this->merchantId = env('DOKU_MERCHANT_ID', '2115'); // Fallback ke 2115 jika env kosong
        $this->baseUrl = rtrim(env('DOKU_BASE_URL', 'https://api-sandbox.doku.com'), '/');
    }

    /**
     * Generate Symmetric Signature sesuai standar SNAP DOKU
     */
    protected function generateSymmetricSignature($method, $endpoint, $accessToken, $bodyArray, $timestamp)
    {
        $clientSecret = $this->secretKey;

        // JSON_UNESCAPED_SLASHES wajib agar hash cocok dengan server DOKU
        $bodyJson = json_encode($bodyArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $digest = strtolower(hash('sha256', $bodyJson)); 
        
        $stringToSign = strtoupper($method) . ":" . $endpoint . ":" . $accessToken . ":" . $digest . ":" . $timestamp;

        $signatureRaw = hash_hmac('sha512', $stringToSign, $clientSecret, true);

        return base64_encode($signatureRaw);
    }

    /**
     * Generate QRIS MPM
     */
    public function generate($invoiceNo, $amount)
    {
        $auth = app(DokuAuthService::class)->getToken();
        $token = $auth['accessToken'];

        // 1. Seragamkan format waktu menjadi UTC (Z) untuk menghindari gagal parse di Prod
        $timestamp = gmdate('Y-m-d\TH:i:s\Z'); 

        $body = [
            "partnerReferenceNo" => (string) $invoiceNo,
            "amount" => [
                "value"    => number_format((float) $amount, 2, '.', ''),
                "currency" => "IDR"
            ],
            "merchantId" => (string) $this->merchantId,
            "terminalId" => env('DOKU_TERMINAL_ID', 'A01'), // Gunakan env agar fleksibel di Prod
            "additionalInfo" => [
                "postalCode" => "55183", 
                "feeType"    => "1"
            ]
        ];

        $endpoint = "/snap-adapter/b2b/v1.0/qr/qr-mpm-generate";

        // 2. Hash array menggunakan json_encode spesifik
        $signature = $this->generateSymmetricSignature('POST', $endpoint, $token, $body, $timestamp);

        // Ubah array ke JSON string statis untuk dikirim
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 3. Gunakan withBody() alih-alih melempar $body langsung ke post()
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-PARTNER-ID'  => $this->clientId,
            'X-EXTERNAL-ID' => (string) time() . rand(100, 999), // Pastikan numeric murni
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $signature,
            'CHANNEL-ID'    => 'H2H',
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])
        ->timeout(15)
        ->withBody($bodyJson, 'application/json') 
        ->post($this->baseUrl . $endpoint);

        return $response->json();
    }

    /**
     * Query / Cek Status Pembayaran QRIS
     */
    public function queryStatus($invoiceNo, $dokuReferenceNo)
    {
        $path = '/snap-adapter/b2b/v1.0/qr/qr-mpm-query';
        $timestamp = date('c'); 
        $externalId = (string) rand(100000, 999999) . time();
        
        $auth = app(DokuAuthService::class)->getToken();
        $token = $auth['accessToken'];

        $body = [
            "originalReferenceNo" => $dokuReferenceNo,
            "originalPartnerReferenceNo" => $invoiceNo,
            "serviceCode" => "47",
            "merchantId" => $this->merchantId
        ];

        $signature = $this->generateSymmetricSignature('POST', $path, $token, $body, $timestamp);
\Log::info('DOKU REQUEST DEBUG', [
    'token' => $token,
    'partner_id' => $this->clientId,
    'timestamp' => $timestamp,
    'signature' => $signature
]);
        $response = Http::withHeaders([
            'X-PARTNER-ID'  => $this->clientId,
            'X-EXTERNAL-ID' => $externalId,
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $signature,
            'Authorization' => 'Bearer ' . $token,
            'CHANNEL-ID'    => 'H2H',
            'Content-Type'  => 'application/json'
        ])->post($this->baseUrl . $path, $body);

        return $response->json();
    }
}