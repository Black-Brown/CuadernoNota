<?php

namespace App\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleAuthController extends Controller
{
    private const STATE_TTL_MINUTES = 10;
    private const LOGIN_CODE_TTL_MINUTES = 2;

    public function redirect(): RedirectResponse|JsonResponse
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect');
        $domain = config('services.google.workspace_domain');

        if (!$clientId || !$clientSecret || !$redirectUri || !$domain) {
            return response()->json([
                'message' => 'El acceso con Google todavía no está configurado.',
            ], 503);
        }

        $state = Str::random(64);
        Cache::put($this->stateCacheKey($state), true, now()->addMinutes(self::STATE_TTL_MINUTES));

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'hd' => $domain,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->redirectToLogin('access_denied');
        }

        $validated = $request->validate([
            'code' => 'required|string',
            'state' => 'required|string|size:64',
        ]);

        if (!Cache::pull($this->stateCacheKey($validated['state']))) {
            return $this->redirectToLogin('invalid_state');
        }

        try {
            $tokenResponse = Http::asForm()
                ->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'code' => $validated['code'],
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('services.google.redirect'),
                ])
                ->throw()
                ->json();

            $profile = Http::withToken($tokenResponse['access_token'])
                ->acceptJson()
                ->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'exception' => $exception::class,
            ]);

            return $this->redirectToLogin('provider_error');
        }

        $domain = strtolower((string) config('services.google.workspace_domain'));
        $hostedDomain = strtolower((string) ($profile['hd'] ?? ''));
        $email = strtolower((string) ($profile['email'] ?? ''));
        $emailVerified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
        $googleId = (string) ($profile['sub'] ?? '');

        if ($hostedDomain !== $domain || !$emailVerified || $googleId === '' || $email === '') {
            return $this->redirectToLogin('invalid_domain');
        }

        $user = User::where('google_id', $googleId)->first()
            ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            return $this->redirectToLogin('not_registered');
        }

        if (!$user->active) {
            return $this->redirectToLogin('inactive');
        }

        if ($user->google_id !== null && $user->google_id !== $googleId) {
            return $this->redirectToLogin('account_mismatch');
        }

        $user->update([
            'google_id' => $googleId,
            'google_avatar' => $profile['picture'] ?? null,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login' => now(),
        ]);

        $loginCode = Str::random(64);
        Cache::put(
            $this->loginCodeCacheKey($loginCode),
            $user->id,
            now()->addMinutes(self::LOGIN_CODE_TTL_MINUTES)
        );

        return redirect()->to($this->frontendLoginUrl(['google_code' => $loginCode]));
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:64',
        ]);

        $userId = Cache::pull($this->loginCodeCacheKey($validated['code']));
        $user = $userId ? User::find($userId) : null;

        if (!$user || !$user->active) {
            return response()->json([
                'message' => 'El código de acceso expiró o ya fue utilizado.',
            ], 401);
        }

        return response()->json([
            'token' => $user->createToken('google-login')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->google_avatar,
            ],
        ]);
    }

    private function redirectToLogin(string $error): RedirectResponse
    {
        return redirect()->to($this->frontendLoginUrl(['google_error' => $error]));
    }

    private function frontendLoginUrl(array $query): string
    {
        $baseUrl = rtrim((string) config('services.google.frontend_url'), '/').'/login';

        return $baseUrl.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function stateCacheKey(string $state): string
    {
        return 'google-oauth-state:'.hash('sha256', $state);
    }

    private function loginCodeCacheKey(string $code): string
    {
        return 'google-login-code:'.hash('sha256', $code);
    }
}
