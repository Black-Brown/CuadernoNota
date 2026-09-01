<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.google', [
            'client_id' => 'google-client-id',
            'client_secret' => 'google-client-secret',
            'redirect' => 'http://localhost/api/auth/google/callback',
            'workspace_domain' => 'happylearningschool.net',
            'frontend_url' => 'http://localhost',
        ]);
    }

    public function test_registered_workspace_user_can_exchange_a_single_use_login_code(): void
    {
        $user = User::factory()->create([
            'email' => 'docente@happylearningschool.net',
            'role' => 'teacher',
            'active' => true,
        ]);

        $state = str_repeat('a', 64);
        Cache::put('google-oauth-state:'.hash('sha256', $state), true, now()->addMinutes(10));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-123',
                'email' => $user->email,
                'email_verified' => true,
                'hd' => 'happylearningschool.net',
                'picture' => 'https://example.com/avatar.jpg',
            ]),
        ]);

        $callback = $this->get('/api/auth/google/callback?'.http_build_query([
            'code' => 'authorization-code',
            'state' => $state,
        ]));

        $callback->assertRedirect();
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $exchange = $this->postJson('/api/auth/google/exchange', [
            'code' => $query['google_code'],
        ]);

        $exchange
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonStructure(['token', 'user']);

        $this->postJson('/api/auth/google/exchange', ['code' => $query['google_code']])
            ->assertUnauthorized();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-user-123',
        ]);
    }

    public function test_account_outside_workspace_domain_is_rejected(): void
    {
        $state = str_repeat('b', 64);
        Cache::put('google-oauth-state:'.hash('sha256', $state), true, now()->addMinutes(10));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'personal-google-user',
                'email' => 'persona@gmail.com',
                'email_verified' => true,
                'hd' => '',
            ]),
        ]);

        $response = $this->get('/api/auth/google/callback?'.http_build_query([
            'code' => 'authorization-code',
            'state' => $state,
        ]));

        $response->assertRedirect('http://localhost/login?google_error=invalid_domain');
    }
}
