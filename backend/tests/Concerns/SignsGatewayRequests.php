<?php

namespace Tests\Concerns;

trait SignsGatewayRequests
{
    /** @return array<string, string> */
    protected function gatewaySignatureHeaders(string $uri, array $payload, string $token = 'test-gateway-token'): array
    {
        $timestamp = (string) now()->utc()->timestamp;
        $nonce = 'test-'.bin2hex(random_bytes(12));
        $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $canonical = implode("\n", [
            $timestamp,
            $nonce,
            'POST',
            $uri,
            hash('sha256', $body),
        ]);

        return [
            'X-Gateway-Timestamp' => $timestamp,
            'X-Gateway-Nonce' => $nonce,
            'X-Gateway-Signature' => hash_hmac('sha256', $canonical, $token),
        ];
    }
}
