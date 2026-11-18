<?php

namespace App\Services\Fyda;

use App\Services\Fyda\Exceptions\FydaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FydaOidcClient
{
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function buildAuthorizationUrl(string $state, string $nonce, string $codeChallenge, array $overrides = []): string
    {
        $this->assertConfigured(['client_id', 'authorization_endpoint', 'redirect_uri']);

        $claims = $overrides['claims'] ?? $this->defaultClaims();

        $query = array_filter([
            'client_id' => $this->config['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => $overrides['scope'] ?? $this->config['scope'] ?? 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'acr_values' => $overrides['acr_values'] ?? $this->config['acr_values'] ?? null,
            'claims_locales' => $overrides['claims_locales'] ?? $this->config['claims_locales'] ?? null,
            'claims' => empty($claims) ? null : json_encode($claims),
        ], fn ($value) => $value !== null && $value !== '');

        return rtrim($this->config['authorization_endpoint'], '?').'?'.
            http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $this->assertConfigured([
            'client_id',
            'token_endpoint',
            'redirect_uri',
            'private_key_base64',
            'client_assertion_type',
        ]);

        try {
            $clientAssertion = $this->clientAssertion();

            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('fyda.timeout', 10))
                ->post($this->config['token_endpoint'], [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->config['redirect_uri'],
                    'client_id' => $this->config['client_id'],
                    'client_assertion' => $clientAssertion,
                    'client_assertion_type' => $this->config['client_assertion_type'],
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (ConnectionException) {
            throw FydaException::serviceUnavailable();
        }

        if ($response->failed()) {
            $assertion = $this->decodeJwtPayload($clientAssertion);

            Log::warning('fyda.token_exchange.failed', [
                'status' => $response->status(),
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
                'token_endpoint' => $this->config['token_endpoint'],
                'aud' => $assertion['aud'] ?? null,
                'ttl_seconds' => isset($assertion['iat'], $assertion['exp']) ? $assertion['exp'] - $assertion['iat'] : null,
                'has_nbf' => array_key_exists('nbf', $assertion),
                'has_kid' => $this->jwtHeaderHasKid($clientAssertion),
            ]);

            throw new FydaException((string) ($response->json('error_description') ?? $response->json('error') ?? 'Fayda token exchange failed.'));
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['access_token'])) {
            throw new FydaException('Fayda token exchange returned an invalid response.');
        }

        Log::info('fyda.token_exchange.completed', [
            'token_type' => $payload['token_type'] ?? null,
            'has_access_token' => ! empty($payload['access_token']),
            'has_id_token' => ! empty($payload['id_token']),
            'expires_in' => $payload['expires_in'] ?? null,
        ]);

        return $payload;
    }

    public function fetchUserInfo(string $accessToken): array
    {
        $this->assertConfigured(['userinfo_endpoint']);

        try {
            $response = Http::withToken($accessToken)
                ->accept('application/jwt, application/json')
                ->timeout((int) config('fyda.timeout', 10))
                ->get($this->config['userinfo_endpoint']);
        } catch (ConnectionException) {
            throw FydaException::serviceUnavailable();
        }

        if ($response->failed()) {
            Log::warning('fyda.userinfo.failed', [
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'www_authenticate' => $response->header('WWW-Authenticate'),
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
                'userinfo_endpoint' => $this->config['userinfo_endpoint'],
                'access_token_metadata' => $this->jwtMetadata($accessToken),
            ]);

            throw new FydaException((string) ($response->json('error_description') ?? $response->json('error') ?? 'Fayda userinfo request failed.'));
        }

        $contentType = $response->header('Content-Type', '');
        $body = trim((string) $response->body());

        if (str_contains($contentType, 'application/json')) {
            $json = $response->json();

            if (is_array($json)) {
                return $json;
            }
        }

        if (substr_count($body, '.') === 2) {
            return $this->decodeJwtPayload($body);
        }

        $json = json_decode($body, true);

        if (is_array($json)) {
            return $json;
        }

        throw new FydaException('Fayda userinfo returned an invalid response.');
    }

    public function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new FydaException('Invalid Fayda JWT response.');
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (! is_array($payload)) {
            throw new FydaException('Invalid Fayda JWT payload.');
        }

        return $payload;
    }

    private function clientAssertion(): string
    {
        $now = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $this->config['client_id'],
            'sub' => $this->config['client_id'],
            'aud' => $this->config['token_endpoint'],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + (int) ($this->config['client_assertion_ttl_seconds'] ?? 900),
            'jti' => (string) Str::uuid(),
        ];

        $signingInput = $this->base64UrlEncode(json_encode($header)).'.'.$this->base64UrlEncode(json_encode($payload));

        $privateKey = $this->privateKeyPem();
        $signature = '';

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new FydaException('Unable to sign Fayda client assertion.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function jwtHeaderHasKid(string $jwt): bool
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return false;
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);

        return is_array($header) && array_key_exists('kid', $header);
    }

    private function jwtMetadata(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return ['parts' => count($parts)];
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (! is_array($payload)) {
            return ['parts' => 3, 'payload' => 'unreadable'];
        }

        return Arr::only($payload, [
            'iss',
            'aud',
            'client_id',
            'scope',
            'token_type',
            'exp',
            'iat',
        ]);
    }

    private function privateKeyPem(): string
    {
        $decoded = base64_decode((string) $this->config['private_key_base64'], true);

        if ($decoded === false || trim($decoded) === '') {
            throw new FydaException('Fayda private key is not valid base64.');
        }

        if (str_contains($decoded, '-----BEGIN')) {
            return $decoded;
        }

        $jwk = $this->privateJwk($decoded);

        if (! is_array($jwk)) {
            throw new FydaException('Fayda private key must be PEM or RSA JWK.');
        }

        return $this->rsaJwkToPem($jwk);
    }

    private function privateJwk(?string $decoded = null): ?array
    {
        $decoded ??= base64_decode((string) $this->config['private_key_base64'], true);

        if (! is_string($decoded) || ! str_starts_with(ltrim($decoded), '{')) {
            return null;
        }

        $jwk = json_decode($decoded, true);

        if (! is_array($jwk)) {
            return null;
        }

        return Arr::get($jwk, 'keys.0', $jwk);
    }

    private function rsaJwkToPem(array $jwk): string
    {
        foreach (['n', 'e', 'd', 'p', 'q', 'dp', 'dq', 'qi'] as $key) {
            if (empty($jwk[$key])) {
                throw new FydaException("Fayda RSA JWK is missing {$key}.");
            }
        }

        $der = $this->asn1Sequence(
            $this->asn1Integer("\x00").
            $this->asn1Integer($this->base64UrlDecode($jwk['n'])).
            $this->asn1Integer($this->base64UrlDecode($jwk['e'])).
            $this->asn1Integer($this->base64UrlDecode($jwk['d'])).
            $this->asn1Integer($this->base64UrlDecode($jwk['p'])).
            $this->asn1Integer($this->base64UrlDecode($jwk['q'])).
            $this->asn1Integer($this->base64UrlDecode($jwk['dp'])).
            $this->asn1Integer($this->base64UrlDecode($jwk['dq'])).
            $this->asn1Integer($this->base64UrlDecode($jwk['qi']))
        );

        // Assemble the standard PEM label for caller-supplied key material. No key is embedded here.
        return "-----BEGIN RSA ". "PRIVATE KEY-----\n".
            chunk_split(base64_encode($der), 64, "\n").
            "-----END RSA ". "PRIVATE KEY-----\n";
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");
        $value = $value === '' ? "\x00" : $value;

        if ((ord($value[0]) & 0x80) === 0x80) {
            $value = "\x00".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xff).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url value.');
        }

        return $decoded;
    }

    private function assertConfigured(array $keys): void
    {
        foreach ($keys as $key) {
            if (empty($this->config[$key])) {
                throw new FydaException("Fayda OIDC {$key} is not configured.");
            }
        }
    }

    private function defaultClaims(): array
    {
        return [
            'userinfo' => [
                'individual_id' => ['essential' => true],
                'name' => ['essential' => true],
                'phone_number' => ['essential' => false],
                'email' => ['essential' => false],
                'gender' => ['essential' => false],
                'birthdate' => ['essential' => false],
                'address' => ['essential' => false],
            ],
        ];
    }
}
