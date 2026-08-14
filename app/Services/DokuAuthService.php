<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DokuAuthService
{
    public function getToken()
    {
        // 1. Ambil langsung dari env
        $clientId = config('services.doku.client_id', env('DOKU_CLIENT_ID'));
        $privateKeyPath = storage_path('app/doku/private.key');
        
        $sandboxMode = config('services.doku.sandbox_mode', env('DOKU_SANDBOX_MODE', true));
        $baseUrl = rtrim(config('services.doku.base_url', env('DOKU_BASE_URL', ($sandboxMode ? 'https://api-sandbox.doku.com' : 'https://api.doku.com'))), '/');

        if (empty($clientId) || empty($baseUrl)) {
            throw new Exception('DOKU Client ID atau Base URL belum dikonfigurasi.');
        }

        $endpoint = '/authorization/v1/access-token/b2b';
        $fullUrl = $baseUrl . $endpoint;

        // 2. Format Timestamp ke UTC (ISO8601 UTC+0 / Z) - Sesuai dengan testGetToken
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        // 3. StringToSign untuk Asymmetric Signature (SHA256withRSA)
        $stringToSign = $clientId . '|' . $timestamp;

        // 4. Generate Signature menggunakan Private Key
        $signature = '';
        if (file_exists($privateKeyPath)) {
            $privateKey = file_get_contents($privateKeyPath);
            if (!openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                Log::error('DOKU Auth Error: Gagal melakukan signing dengan OpenSSL.');
                throw new Exception('Gagal men-generate signature DOKU.');
            }
            $signature = base64_encode($signature);
        } else {
            Log::error('DOKU Auth Error: Private key file not found at: ' . $privateKeyPath);
            throw new Exception('Private key file DOKU tidak ditemukan di path: ' . $privateKeyPath);
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
            ])->post($fullUrl, $body);

            $data = $response->json();
            $token = $data['accessToken'] ?? null;

            if (!$token || !$response->successful()) {
                Log::error('DOKU Auth Failed: ' . json_encode($data));
                throw new Exception('Gagal mendapatkan B2B Access Token dari DOKU: ' . ($data['responseMessage'] ?? 'Unknown Error'));
            }

            return ['accessToken' => $token];
            
        } catch (\Exception $e) {
            Log::error('DOKU HTTP Error: ' . $e->getMessage());
            throw $e;
        }
    }
}