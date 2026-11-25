<?php

use App\Models\OtpToken;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Auth\OtpService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    RateLimiter::clear('otp:1');
});

it('generates a hashed otp token stored in auth_otp_tokens', function () {
    $user = User::factory()->create();
    $service = app(OtpService::class);

    $token = $service->generate($user);

    expect($token->plain_code)->toHaveLength(6)
        ->and(ctype_digit($token->plain_code))->toBeTrue()
        ->and($token->token_hash)->toBe(hash('sha256', $token->plain_code))
        ->and($token->used_at)->toBeNull()
        ->and($token->expires_at->isFuture())->toBeTrue();

    $this->assertDatabaseHas('auth_otp_tokens', [
        'user_id'    => $user->id,
        'token_hash' => hash('sha256', $token->plain_code),
    ]);
});

it('verifies a valid otp successfully and marks it used', function () {
    $user = User::factory()->create();
    $service = app(OtpService::class);

    $token = $service->generate($user);
    $result = $service->verify($user, $token->plain_code);

    expect($result)->toBeTrue();

    $this->assertDatabaseHas('auth_otp_tokens', [
        'id'      => $token->id,
        'user_id' => $user->id,
    ]);

    $updated = OtpToken::find($token->id);
    expect($updated->used_at)->not->toBeNull();
});

it('rejects an expired otp', function () {
    $user = User::factory()->create();

    $plain = '123456';
    OtpToken::create([
        'user_id'    => $user->id,
        'token_hash' => hash('sha256', $plain),
        'expires_at' => now()->subMinute(),
    ]);

    $service = app(OtpService::class);

    expect(fn () => $service->verify($user, $plain))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects a used otp', function () {
    $user = User::factory()->create();
    $service = app(OtpService::class);

    $token = $service->generate($user);
    $service->verify($user, $token->plain_code);

    expect(fn () => $service->verify($user, $token->plain_code))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('enforces rate limit after 3 failed attempts', function () {
    $user = User::factory()->create();
    $service = app(OtpService::class);

    RateLimiter::clear('otp:' . $user->id);

    // 3 failed attempts
    for ($i = 0; $i < 3; $i++) {
        try {
            $service->verify($user, '000000');
        } catch (\Illuminate\Validation\ValidationException) {
        }
    }

    // 4th attempt should hit rate limit
    expect(fn () => $service->verify($user, '000000'))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'Terlalu banyak percobaan');
});

it('sends otp via POST auth/otp/send and redirects to verify page', function () {
    $user = User::factory()->create();

    $response = $this->post('/auth/otp/send', ['email' => $user->email]);

    $response->assertRedirect(route('auth.otp.show'));
    Notification::assertSentTo($user, OtpCodeNotification::class);
});

it('show page is accessible to guests', function () {
    $response = $this->withoutVite()->get('/auth/otp');

    $response->assertOk();
});

it('otp store rejects with no active session', function () {
    $response = $this->post('/auth/otp', ['code' => '123456']);

    $response->assertRedirect(route('auth.otp.show'));
});

it('generates new otp and invalidates previous unused token', function () {
    $user = User::factory()->create();
    $service = app(OtpService::class);

    $first = $service->generate($user);
    $second = $service->generate($user);

    $firstUpdated = OtpToken::find($first->id);
    expect($firstUpdated->used_at)->not->toBeNull();

    expect(fn () => $service->verify($user, $first->plain_code))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('authenticates user and redirects to dashboard when otp is verified', function () {
    $user = User::factory()->create();
    $service = app(OtpService::class);
    $token = $service->generate($user);

    $response = $this->withSession([
        'otp_email'   => $user->email,
        'otp_user_id' => $user->id,
    ])->post('/auth/otp', [
        'code' => $token->plain_code,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});
