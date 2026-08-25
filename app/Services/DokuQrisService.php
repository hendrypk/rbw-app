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
        // 1. WAJIB pakai config() agar kebal terhadap php artisan config:cache di VPS
        $this->clientId = config('services.doku.client_id', env('DOKU_CLIENT_ID'));
        $this->secretKey = config('services.doku.secret_key', env('DOKU_SECRET_KEY'));
        $this->merchantId = config('services.doku.merchant_id', env('DOKU_MERCHANT_ID', '2115')); 
        
        $sandboxMode = config('services.doku.sandbox_mode', env('DOKU_SANDBOX_MODE', true));
        $baseUrl = config('services.doku.base_url', env('DOKU_BASE_URL', ($sandboxMode ? 'https://api-sandbox.doku.com' : 'https://api.doku.com')));
        
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Generate Symmetric Signature sesuai standar SNAP DOKU
     */
    protected function generateSymmetricSignature($method, $endpoint, $accessToken, $bodyJson, $timestamp)
    {
        $clientSecret = $this->secretKey;

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
        $token = is_array($auth) ? $auth['accessToken'] : $auth;
        $token = trim($token);

        // 2. Format waktu WAJIB UTC (Z) untuk Production DOKU
        $timestamp = gmdate('Y-m-d\TH:i:s\Z'); 

        $body = [
            "partnerReferenceNo" => (string) $invoiceNo,
            "amount" => [
                "value"    => number_format((float) $amount, 2, '.', ''),
                "currency" => "IDR"
            ],
            "merchantId" => (string) $this->merchantId,
            "terminalId" => "K45", 
            "additionalInfo" => [
                "postalCode" => "55183",
                "feeType"    => "1"
            ]
        ];

        $endpoint = "/snap-adapter/b2b/v1.0/qr/qr-mpm-generate";

        // 3. Encode JSON manual agar formatnya tidak diotak-atik oleh Laravel
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = $this->generateSymmetricSignature('POST', $endpoint, $token, $bodyJson, $timestamp);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-PARTNER-ID'  => $this->clientId,
            'X-EXTERNAL-ID' => (string) time() . rand(100, 999), // Angka murni
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $signature,
            'CHANNEL-ID'    => 'H2H',
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])
        ->timeout(15)
        ->withBody($bodyJson, 'application/json') // <-- PENTING: Kirim string murni
        ->post($this->baseUrl . $endpoint);

        return $response->json();
    }

    /**
     * Query / Cek Status Pembayaran QRIS
     */
    public function queryStatus($invoiceNo, $dokuReferenceNo)
    {
        $path = '/snap-adapter/b2b/v1.0/qr/qr-mpm-query';
        $timestamp = gmdate('Y-m-d\TH:i:s\Z'); // Sama, gunakan UTC
        $externalId = (string) time() . rand(100, 999);
        
        $auth = app(DokuAuthService::class)->getToken();
        $token = is_array($auth) ? $auth['accessToken'] : $auth;
        $token = trim($token);

        $body = [
            "originalReferenceNo" => (string) $dokuReferenceNo,
            "originalPartnerReferenceNo" => (string) $invoiceNo,
            "serviceCode" => "47",
            "merchantId" => (string) $this->merchantId
        ];

        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = $this->generateSymmetricSignature('POST', $path, $token, $bodyJson, $timestamp);

        $response = Http::withHeaders([
            'X-PARTNER-ID'  => $this->clientId,
            'X-EXTERNAL-ID' => $externalId,
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $signature,
            'Authorization' => 'Bearer ' . $token,
            'CHANNEL-ID'    => 'H2H',
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])
        ->timeout(15)
        ->withBody($bodyJson, 'application/json')
        ->post($this->baseUrl . $path);

        return $response->json();
    }
}