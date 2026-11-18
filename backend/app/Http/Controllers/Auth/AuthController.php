<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\FydaVerification;
use App\Models\User;
use App\Services\Fyda\Dtos\FydaIdentity;
use App\Services\Fyda\Exceptions\FydaException;
use App\Services\Fyda\FydaOidcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function authorizeFyda(Request $request, FydaOidcClient $oidcClient): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', 'string', 'max:120'],
            'acr_values' => ['nullable', 'string', 'max:180'],
            'claims_locales' => ['nullable', 'string', 'max:40'],
            'claims' => ['nullable', 'array'],
        ]);

        $state = Str::random(64);
        $nonce = Str::random(64);
        $codeVerifier = $this->base64UrlEncode(random_bytes(64));
        $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));

        $ttl = (int) config('fyda.oidc.pending_ttl_seconds', 600);

        Cache::put($this->fydaPendingCacheKey($state), [
            'code_verifier' => $codeVerifier,
            'nonce' => $nonce,
            'request_payload' => Arr::only($data, ['scope', 'acr_values', 'claims_locales', 'claims']),
            'created_at' => Carbon::now()->toIso8601String(),
        ], $ttl);

        return response()->json([
            'authorization_url' => $oidcClient->buildAuthorizationUrl($state, $nonce, $codeChallenge, $data),
            'state' => $state,
            'expires_in' => $ttl,
        ]);
    }

    public function handleFydaCallback(Request $request, FydaOidcClient $oidcClient)
    {
        $state = (string) $request->query('state', '');

        if ($request->filled('error')) {
            return $this->finishFydaCallbackWithError(
                $state,
                (string) ($request->query('error_description') ?: $request->query('error')),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $pending = $state === '' ? null : Cache::get($this->fydaPendingCacheKey($state));

        if (! is_array($pending)) {
            return $this->finishFydaCallbackWithError(
                $state,
                FydaException::authorizationExpired()->getMessage(),
                Response::HTTP_GONE,
            );
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $this->finishFydaCallbackWithError(
                $state,
                'FYDA callback did not include an authorization code.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $tokens = $oidcClient->exchangeAuthorizationCode($code, $pending['code_verifier']);
            $claims = $oidcClient->fetchUserInfo($tokens['access_token']);
            $identity = FydaIdentity::fromArray($claims);
            [$user, $wasNew] = $this->syncVerifiedFydaUser($identity, [
                'state' => $state,
                'scope' => Arr::get($pending, 'request_payload.scope'),
                'auth_method' => 'oidc',
            ], [
                'email' => $identity->email,
            ]);
        } catch (FydaException $exception) {
            return $this->finishFydaCallbackWithError($state, $exception->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        Cache::forget($this->fydaPendingCacheKey($state));

        $token = $user->createToken('api')->plainTextToken;
        $result = [
            'status' => 'completed',
            'token' => $token,
            'token_type' => 'Bearer',
            'was_new_user' => $wasNew,
            'user' => (new UserResource($user))->resolve(),
        ];

        Cache::put($this->fydaResultCacheKey($state), $result, (int) config('fyda.oidc.result_ttl_seconds', 300));

        if ($redirectUrl = config('fyda.oidc.success_redirect_url')) {
            return redirect()->away($redirectUrl.(str_contains($redirectUrl, '?') ? '&' : '?').http_build_query([
                'state' => $state,
                'status' => 'completed',
            ]));
        }

        if ($request->acceptsHtml() && ! $request->expectsJson()) {
            return $this->fydaCallbackHtml($state, 'completed');
        }

        return response()->json($result, $wasNew ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function fydaResult(string $state): JsonResponse
    {
        $result = Cache::get($this->fydaResultCacheKey($state));

        if (is_array($result)) {
            if (($result['status'] ?? null) === 'failed') {
                return response()->json($result, (int) ($result['status_code'] ?? Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            Cache::forget($this->fydaResultCacheKey($state));

            return response()->json($result);
        }

        if (Cache::has($this->fydaPendingCacheKey($state))) {
            return response()->json([
                'status' => 'pending',
            ], Response::HTTP_ACCEPTED);
        }

        return response()->json([
            'status' => 'expired',
            'message' => 'FYDA authorization result is no longer available.',
        ], Response::HTTP_GONE);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'All sessions revoked.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json([
            'message' => 'Account deleted.',
        ]);
    }

    private function syncVerifiedFydaUser(FydaIdentity $identity, array $payloadForAudit, array $userInput = []): array
    {
        $now = Carbon::now();

        return DB::transaction(function () use ($identity, $payloadForAudit, $userInput, $now) {
            $user = User::query()->lockForUpdate()->firstWhere('national_id', $identity->nationalId);

            $isNew = false;

            if ($user === null) {
                $user = new User([
                    'national_id' => $identity->nationalId,
                ]);
                $isNew = true;
            }

            $user->fill([
                'first_name' => $identity->firstName,
                'last_name' => $identity->lastName,
                'father_name' => $identity->fatherName,
                'date_of_birth' => $identity->dateOfBirth !== '' ? $identity->dateOfBirth : null,
                'phone' => $userInput['phone'] ?? $identity->phone,
            ]);

            if (! empty($userInput['email'])) {
                $user->email = $userInput['email'];
            }

            if (! $user->role) {
                $user->role = UserRole::Rider;
            }

            $user->fyda_verified_at = $now;
            $user->last_login_at = $now;

            $metadata = $user->metadata ?? [];
            $metadata['fyda_identity'] = $identity->toArray();
            $user->metadata = $metadata;

            $user->save();

            FydaVerification::create([
                'user_id' => $user->id,
                'national_id' => $identity->nationalId,
                'status' => 'verified',
                'request_payload' => $payloadForAudit,
                'response_payload' => $identity->raw ?? $identity->toArray(),
                'verified_at' => $now,
            ]);

            return [$user->fresh(), $isNew];
        });
    }

    private function finishFydaCallbackWithError(string $state, string $message, int $status)
    {
        if ($state !== '') {
            Cache::put($this->fydaResultCacheKey($state), [
                'status' => 'failed',
                'message' => $message,
                'status_code' => $status,
            ], (int) config('fyda.oidc.result_ttl_seconds', 300));
            Cache::forget($this->fydaPendingCacheKey($state));
        }

        if ($redirectUrl = config('fyda.oidc.failure_redirect_url')) {
            return redirect()->away($redirectUrl.(str_contains($redirectUrl, '?') ? '&' : '?').http_build_query([
                'state' => $state,
                'status' => 'failed',
            ]));
        }

        if (request()->acceptsHtml() && ! request()->expectsJson()) {
            return $this->fydaCallbackHtml($state, 'failed', $message, $status);
        }

        return response()->json([
            'status' => 'failed',
            'message' => $message,
        ], $status);
    }

    private function fydaCallbackHtml(string $state, string $status, ?string $message = null, int $httpStatus = Response::HTTP_OK)
    {
        $query = http_build_query([
            'state' => $state,
            'status' => $status,
        ]);
        $deepLink = "bisklet://auth/fyda?{$query}";
        $title = $status === 'completed' ? 'Verification complete' : 'Verification failed';
        $body = $status === 'completed'
            ? 'You can return to Bisklet now.'
            : ($message ?: 'Please return to Bisklet and try again.');
        $background = config('branding.colors.extra_light_teal');
        $primary = config('branding.colors.primary_teal');
        $ink = config('branding.colors.near_black');
        $muted = config('branding.colors.neutral_text');
        $white = config('branding.colors.white');

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'.
            '<meta name="viewport" content="width=device-width, initial-scale=1">'.
            '<title>'.e($title).'</title>'.
            '<style>body{margin:0;font-family:Arial,sans-serif;background:'.$background.';color:'.$ink.';display:grid;min-height:100vh;place-items:center;padding:24px}'.
            '.box{max-width:360px;text-align:center}.mark{width:56px;height:56px;border-radius:16px;background:'.$primary.';margin:0 auto 20px;display:grid;place-items:center;color:'.$white.';font-size:28px;font-weight:700}'.
            'h1{font-size:24px;margin:0 0 10px}p{color:'.$muted.';line-height:1.5;margin:0 0 24px}a{display:inline-block;background:'.$primary.';color:'.$white.';text-decoration:none;padding:14px 22px;border-radius:14px;font-weight:700}</style>'.
            '</head><body><main class="box"><div class="mark">B</div><h1>'.e($title).'</h1><p>'.e($body).'</p>'.
            '<a href="'.e($deepLink).'">Open Bisklet</a></main>'.
            '<script>setTimeout(function(){window.location.href='.json_encode($deepLink).';},300);</script>'.
            '</body></html>';

        return response($html, $httpStatus)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function fydaPendingCacheKey(string $state): string
    {
        return "fyda:oidc:pending:{$state}";
    }

    private function fydaResultCacheKey(string $state): string
    {
        return "fyda:oidc:result:{$state}";
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
