<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class DokuAuthService
{
    /**
     * Get B2B2C Access Token from DOKU
     */
public function getToken()
{
    $clientId = env('DOKU_CLIENT_ID');
    $baseUrl = rtrim(env('DOKU_BASE_URL'), '/');

    // 1. Format ISO8601 UTC+0
    $timestamp = Carbon::now('UTC')->toIso8601ZuluString(); 

    // 2. Rumus String to Sign: client_ID + "|" + X-TIMESTAMP
    $stringToSign = $clientId . "|" . $timestamp;

    // 3. Generate Asymmetric Signature dengan Private Key RSA
    $privateKeyPath = storage_path('keys/private.pem');
    if (!file_exists($privateKeyPath)) {
        throw new \Exception("DOKU Setup Error: Private key file missing at " . $privateKeyPath);
    }
    
    $privateKey = file_get_contents($privateKeyPath);
    if (!openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new \Exception('DOKU Setup Error: Failed to generate OpenSSL signature.');
    }

    $encodedSignature = base64_encode($signature);

    // 4. Endpoint Token B2B DOKU SNAP (Sesuaikan dengan dokumen, umumnya v1.0/access-token/b2b)
    $url = $baseUrl . '/authorization/v1/access-token/b2b2c';
    // Jika di sandbox Anda tertulis v1/access-token/b2b2c, gunakan:
    // $url = $baseUrl . '/v1/access-token/b2b2c';

    // 5. REQUEST BODY YANG BENAR UNTUK B2B TOKEN
    $response = Http::withHeaders([
        'X-CLIENT-KEY' => $clientId,
        'X-TIMESTAMP'  => $timestamp,
        'X-SIGNATURE'  => $encodedSignature,
        'Content-Type' => 'application/json',
    ])->post($url, [
        'grantType' => 'authorization_code', // WAJIB client_credentials
    ]);

    $json = $response->json();

    // Cek response sukses (Standard SNAP mengembalikan 'accessToken')
    if ($response->failed() || !isset($json['accessToken'])) {
        throw new \Exception('DOKU AUTH FAILED: ' . json_encode($json ?? $response->body()));
    }

    return $json;
}
}