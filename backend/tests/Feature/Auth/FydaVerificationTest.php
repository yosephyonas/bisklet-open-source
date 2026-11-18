<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FydaVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function testFydaOidcAuthorizationUrlUsesPkceAndState(): void
    {
        $this->configureFydaOidc();

        $response = $this->postJson('/api/v1/auth/fyda/authorize', [
            'acr_values' => 'mosip:idp:acr:generated-code',
            'claims_locales' => 'en am',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'authorization_url',
                'state',
                'expires_in',
            ]);

        $query = [];
        parse_str(parse_url($response->json('authorization_url'), PHP_URL_QUERY), $query);

        $this->assertSame(config('fyda.oidc.client_id'), $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(config('fyda.oidc.redirect_uri'), $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('mosip:idp:acr:generated-code', $query['acr_values']);
        $this->assertSame('en am', $query['claims_locales']);
        $this->assertNotEmpty($query['code_challenge']);
        $this->assertSame($response->json('state'), $query['state']);

        $claims = json_decode($query['claims'], true);

        $this->assertIsArray($claims);
        $this->assertArrayHasKey('individual_id', $claims['userinfo']);
        $this->assertArrayNotHasKey('id_token', $claims);
    }

    public function testFydaOidcCallbackExchangesCodeCreatesUserAndCachesResult(): void
    {
        $this->configureFydaOidc();

        $authorize = $this->postJson('/api/v1/auth/fyda/authorize')->assertOk();
        $state = $authorize->json('state');
        $userinfoJwt = $this->unsignedJwt([
            'individual_id' => '0000000000456',
            'sub' => 'fallback-subject',
            'name' => 'Mekdes Alemu',
            'email' => 'mekdes@example.com',
            'phone_number' => '+251900000456',
            'birthdate' => '1992-08-19',
            'gender' => 'F',
        ]);

        Http::fake([
            config('fyda.oidc.token_endpoint') => function (Request $request) {
                parse_str($request->body(), $body);

                $this->assertSame('authorization_code', $body['grant_type']);
                $this->assertSame('auth-code-123', $body['code']);
                $this->assertSame(config('fyda.oidc.client_id'), $body['client_id']);
                $this->assertSame(config('fyda.oidc.redirect_uri'), $body['redirect_uri']);
                $this->assertSame(config('fyda.oidc.client_assertion_type'), $body['client_assertion_type']);
                $this->assertNotEmpty($body['code_verifier']);
                $this->assertNotEmpty($body['client_assertion']);

                $assertion = $this->decodeJwtPayload($body['client_assertion']);

                $this->assertSame(config('fyda.oidc.client_id'), $assertion['iss']);
                $this->assertSame(config('fyda.oidc.client_id'), $assertion['sub']);
                $this->assertSame(config('fyda.oidc.token_endpoint'), $assertion['aud']);
                $this->assertArrayHasKey('nbf', $assertion);
                $this->assertLessThanOrEqual(15, $assertion['exp'] - $assertion['iat']);

                return Http::response([
                    'access_token' => 'access-token-123',
                    'id_token' => 'id-token-123',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ]);
            },
            config('fyda.oidc.userinfo_endpoint') => Http::response($userinfoJwt, 200, [
                'Content-Type' => 'application/jwt',
            ]),
        ]);

        $callback = $this->getJson("/api/v1/auth/fyda/callback?code=auth-code-123&state={$state}");

        $callback->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.national_id', '0000000000456')
            ->assertJsonPath('user.first_name', 'Mekdes')
            ->assertJsonPath('user.last_name', 'Alemu');

        $this->assertDatabaseHas('users', [
            'national_id' => '0000000000456',
            'email' => 'mekdes@example.com',
        ]);

        $this->assertDatabaseHas('fyda_verifications', [
            'national_id' => '0000000000456',
            'status' => 'verified',
        ]);

        $this->getJson("/api/v1/auth/fyda/result/{$state}")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('user.national_id', '0000000000456');
    }

    public function testLogoutRevokesCurrentToken(): void
    {
        $user = User::factory()->create([
            'fyda_verified_at' => now(),
        ]);

        $token = $user->createToken('api');

        $this->withToken($token->plainTextToken)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out.']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    private function configureFydaOidc(): void
    {
        config([
            'fyda.oidc.client_id' => 'bisklet-client',
            'fyda.oidc.authorization_endpoint' => 'https://fayda.example/authorize',
            'fyda.oidc.token_endpoint' => 'https://fayda.example/token',
            'fyda.oidc.userinfo_endpoint' => 'https://fayda.example/userinfo',
            'fyda.oidc.redirect_uri' => 'https://bisklet.example/api/v1/auth/fyda/callback',
            'fyda.oidc.private_key_base64' => base64_encode($this->testPrivateKeyPem()),
            'fyda.oidc.client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'fyda.oidc.client_assertion_ttl_seconds' => 15,
            'fyda.oidc.scope' => 'openid profile email',
            'fyda.oidc.pending_ttl_seconds' => 600,
            'fyda.oidc.result_ttl_seconds' => 300,
            'fyda.oidc.success_redirect_url' => null,
            'fyda.oidc.failure_redirect_url' => null,
        ]);
    }

    private function unsignedJwt(array $payload): string
    {
        return $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ])).'.'.$this->base64UrlEncode(json_encode($payload)).'.signature';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        $this->assertCount(3, $parts);

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    }

    private function testPrivateKeyPem(): string
    {
        // Create a disposable RSA key for tests. Never check private keys into Git.
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $pem = '';
        if ($key === false || !openssl_pkey_export($key, $pem)) {
            throw new \RuntimeException('Unable to generate RSA key for test');
        }

        return $pem;
    }
}
