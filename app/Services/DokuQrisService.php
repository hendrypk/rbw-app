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

        // Format waktu ISO8601 lokal (+07:00)
        $timestamp = date('c'); 

        $body = [
            "partnerReferenceNo" => (string) $invoiceNo,
            "amount" => [
                "value"    => number_format($amount, 2, '.', ''),
                "currency" => "IDR"
            ],
            "merchantId" => $this->merchantId,
            "terminalId" => "K45", 
            
            // --- INI WAJIB ADA AGAR SERVER DOKU TIDAK CRASH 500 ---
            "additionalInfo" => [
                "postalCode" => "55183", // Kode pos Bantul, atau sesuaikan
                "feeType"    => "1"
            ]
        ];

        $endpoint = "/snap-adapter/b2b/v1.0/qr/qr-mpm-generate";

        $signature = $this->generateSymmetricSignature('POST', $endpoint, $token, $body, $timestamp);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-PARTNER-ID'  => $this->clientId,
            'X-EXTERNAL-ID' => (string) rand(100000, 999999) . time(),
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $signature,
            'CHANNEL-ID'    => 'H2H',
            'Content-Type'  => 'application/json',
        ])
        ->timeout(15)
        ->post($this->baseUrl . $endpoint, $body);

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