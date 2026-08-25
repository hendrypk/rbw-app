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
        // Gunakan config() agar aman saat 'php artisan config:cache'
        $this->clientId   = config('services.doku.client_id', env('DOKU_CLIENT_ID'));
        $this->secretKey  = config('services.doku.secret_key', env('DOKU_SECRET_KEY'));
        $this->merchantId = config('services.doku.merchant_id', env('DOKU_MERCHANT_ID'));
        $this->baseUrl    = rtrim(config('services.doku.base_url', env('DOKU_BASE_URL', 'https://api.doku.com')), '/');
    }

    /**
     * Generate Symmetric Signature sesuai standar SNAP DOKU
     */
    protected function generateSymmetricSignature($method, $endpoint, $accessToken, $bodyArray, $timestamp)
    {
        $clientSecret = $this->secretKey;

        // Minify JSON Body
        $bodyJson = json_encode($bodyArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // SHA256 Digest
        $digest = strtolower(hash('sha256', $bodyJson)); 
        
        // String to Sign
        $stringToSign = strtoupper($method) . ":" . $endpoint . ":" . $accessToken . ":" . $digest . ":" . $timestamp;

        // HMAC-SHA512
        $signatureRaw = hash_hmac('sha512', $stringToSign, $clientSecret, true);

        return base64_encode($signatureRaw);
    }

    /**
     * Generate QRIS MPM
     */
    public function generate($invoiceNo, $amount)
    {
        $auth  = app(DokuAuthService::class)->getToken();
        $token = is_array($auth) ? $auth['accessToken'] : $auth;

        // Format ISO8601 UTC Zulu Wajib SNAP DOKU
        $timestamp = Carbon::now('UTC')->toIso8601ZuluString(); 

        $body = [
            "partnerReferenceNo" => (string) $invoiceNo,
            "amount" => [
                "value"    => number_format($amount, 2, '.', ''),
                "currency" => "IDR"
            ],
            "merchantId" => $this->merchantId,
            "terminalId" => "K45", 
            "additionalInfo" => [
                "postalCode" => "55183",
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
        $path       = '/snap-adapter/b2b/v1.0/qr/qr-mpm-query';
        $timestamp  = Carbon::now('UTC')->toIso8601ZuluString(); 
        $externalId = (string) rand(100000, 999999) . time();
        
        $auth  = app(DokuAuthService::class)->getToken();
        $token = is_array($auth) ? $auth['accessToken'] : $auth;

        $body = [
            "originalReferenceNo"        => $dokuReferenceNo,
            "originalPartnerReferenceNo" => $invoiceNo,
            "serviceCode"                => "47",
            "merchantId"                 => $this->merchantId
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
        ])
        ->timeout(15)
        ->post($this->baseUrl . $path, $body);

        return $response->json();
    }
}