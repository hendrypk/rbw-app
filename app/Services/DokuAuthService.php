<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DokuAuthService
{
public function getToken()
{
    // Mengambil nilai dari config/services.php
    $clientId = config('services.doku.client_id');
    $baseUrl  = rtrim(config('services.doku.base_url', 'https://api.doku.com'), '/');

    // Wajib format ISO8601 UTC Zulu
    $timestamp = \Carbon\Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');

    // String to Sign B2B Auth: CLIENT_ID|X-TIMESTAMP
    $stringToSign = $clientId . "|" . $timestamp;

    $privateKeyPath = storage_path('app/doku/private.key');
    if (!file_exists($privateKeyPath)) {
        throw new \Exception("Private key missing at " . $privateKeyPath);
    }

    $privateKey = file_get_contents($privateKeyPath);
    if (!openssl_sign($stringToSign, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new \Exception("Failed to generate OpenSSL signature.");
    }

    $encodedSignature = base64_encode($binarySignature);
    $url = $baseUrl . '/authorization/v1/access-token/b2b';

    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'X-SIGNATURE'  => $encodedSignature,
        'X-TIMESTAMP'  => $timestamp,
        'X-CLIENT-KEY' => $clientId,
        'Content-Type' => 'application/json',
    ])->post($url, [
        'grantType' => 'client_credentials',
    ]);

    $json = $response->json();

    if ($response->failed() || !isset($json['accessToken'])) {
        throw new \Exception('DOKU AUTH FAILED: ' . json_encode($json ?? $response->body()));
    }

    return $json['accessToken'];
}
}