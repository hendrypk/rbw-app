<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class DokuAuthService
{
    public function getToken()
    {
        $clientId = config('services.doku.client_id', env('DOKU_CLIENT_ID'));
        $baseUrl  = rtrim(config('services.doku.base_url', env('DOKU_BASE_URL', 'https://api.doku.com')), '/');

        // 1. Format ISO8601 UTC Zulu Wajib SNAP
        $timestamp = Carbon::now('UTC')->toIso8601ZuluString();

        // 2. String to Sign B2B: CLIENT_ID|X-TIMESTAMP
        $stringToSign = $clientId . "|" . $timestamp;

        // 3. Signature SHA256withRSA
        $privateKeyPath = storage_path('app/doku/private.key');
        if (!file_exists($privateKeyPath)) {
            throw new \Exception("Private key missing at " . $privateKeyPath);
        }

        $privateKey = file_get_contents($privateKeyPath);
        if (!openssl_sign($stringToSign, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \Exception("Failed to generate OpenSSL signature.");
        }

        $encodedSignature = base64_encode($binarySignature);

        // 4. Endpoint B2B Access Token Resmi DOKU SNAP
        $url = $baseUrl . '/v1.0/access-token/b2b';

        // 5. HTTP POST Request
        $response = Http::withHeaders([
            'X-CLIENT-KEY' => $clientId,
            'X-TIMESTAMP'  => $timestamp,
            'X-SIGNATURE'  => $encodedSignature,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'grantType' => 'client_credentials',
        ]);

        $json = $response->json();

        if ($response->failed() || !isset($json['accessToken'])) {
            throw new \Exception('DOKU AUTH FAILED: ' . json_encode($json ?? $response->body()));
        }

        // Kembalikan sebagai Array & String Token langsung agar aman
        return [
            'accessToken' => $json['accessToken'],
            'tokenType'   => $json['tokenType'] ?? 'Bearer',
            'expiresIn'   => $json['expiresIn'] ?? '900'
        ];
    }
}