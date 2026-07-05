<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DokuQrisService
{
    protected $clientId;
    protected $secretKey;
    protected $baseUrl;

    public function __construct()
    {
        // Ambil data dari config atau .env Anda
        $this->clientId = config('services.doku.client_id');
        $this->secretKey = config('services.doku.secret_key');
        $this->baseUrl = config('services.doku.base_url', 'https://api-sandbox.doku.com');
    }

    /**
     * Membantu membuat X-SIGNATURE sesuai rumus SNAP BI DOKU
     */
    private function generateSignature($method, $path, $accessToken, $body, $timestamp)
    {
        // 1. Minify & Hash SHA256 dari Request Body
        $hashedBody = strtolower(hash('sha256', json_encode($body)));
        
        // 2. Satukan komponen String to Sign
        // Formula: HTTPMethod + ":" + EndpointUrl + ":" + AccessToken + ":" + HashedBody + ":" + TimeStamp
        $stringToSign = strtoupper($method) . ":" . $path . ":" . $accessToken . ":" . $hashedBody . ":" . $timestamp;

        // 3. HMAC-SHA512 dengan Secret Key Merchant
        return hash_hmac('sha512', $stringToSign, $this->secretKey);
    }

    /**
     * Generate QRIS MPM
     */
public function generate($invoiceNo, $amount)
    {
        $clientId = env('DOKU_CLIENT_ID');
        $merchantId = env('DOKU_MERCHANT_ID');
        $baseUrl = rtrim(env('DOKU_BASE_URL'), '/');

        // Fetch valid Token
        $auth = app(DokuAuthService::class)->getToken();

        $token = $auth['accessToken'];

        // SNAP adapter headers require local ISO8601 format as well
        // $timestamp = Carbon::now('Asia/Jakarta')->toIso8601String();
        $timestamp = Carbon::now('UTC')->toIso8601ZuluString(); 

        $body = [
            "partnerReferenceNo" => (string) $invoiceNo,
            "amount" => [
                "value"    => number_format($amount, 2, '.', ''),
                "currency" => "IDR"
            ],
            "merchantId" => $merchantId,
            "terminalId" => "TERM-01",
            "additionalInfo" => [
                "postalCode" => "12345",
                "feeType"    => "1"
            ]
        ];

        $jsonBody = json_encode($body);
        $endpoint = "/snap-adapter/b2b/v1.0/qr/qr-mpm-generate";

        // Lowercase(HexEncode(SHA-256(minify(RequestBody))))
        $minifyBody = hash('sha256', $jsonBody);

        // Standard SNAP Formula: HTTPMethod + ":" + EndpointUrl + ":" + AccessToken + ":" + Lowercase(HexEncode(SHA-256(minify(RequestBody)))) + ":" + TimeStamp
        $stringToSign = "POST:" . $endpoint . ":" . $token . ":" . $minifyBody . ":" . $timestamp;

        // Symmetric Signature with Get Token: HMAC_SHA512 (clientSecret, stringToSign)
        $signature = hash_hmac('sha512', $stringToSign, env('DOKU_SECRET_KEY'), true);
        $signature = base64_encode($signature);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-PARTNER-ID'  => $clientId,
            'X-EXTERNAL-ID' => (string) rand(100000, 999999) . time(), // Unique daily transmission constraint
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $signature,
            'CHANNEL-ID'    => 'H2H', // Forced from QRIS documentation schema requirement
            'Content-Type'  => 'application/json',
        ])->post($baseUrl . $endpoint, $body);

        return $response->json();
    }

    /**
     * Query / Cek Status Pembayaran QRIS
     */
    public function queryStatus($invoiceNo, $dokuReferenceNo)
    {
        $path = '/snap-adapter/b2b/v1.0/qr/qr-mpm-query';
        $timestamp = now()->toIso8601ZuluString();
        $externalId = Str::numeric(8);
        $accessToken = "MOCK_ACCESS_TOKEN_ANDA";

        $body = [
            "originalReferenceNo" => $dokuReferenceNo, // Diambil dari response Generate QRIS
            "originalPartnerReferenceNo" => $invoiceNo,
            "serviceCode" => "47",
            "merchantId" => config('services.doku.merchant_id')
        ];

        $signature = $this->generateSignature('POST', $path, $accessToken, $body, $timestamp);

        $response = Http::withHeaders([
            'X-PARTNER-ID' => $this->clientId,
            'X-EXTERNAL-ID' => $externalId,
            'X-TIMESTAMP'  => $timestamp,
            'X-SIGNATURE'  => $signature,
            'Authorization' => 'Bearer ' . $accessToken,
            'CHANNEL-ID'   => 'H2H',
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . $path, $body);

        return $response->json();
    }
}