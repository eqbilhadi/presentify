<?php

namespace App\Services\Auth;

use App\Models\OtpToken;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    private const TTL_MINUTES = 5;
    private const RATE_LIMIT_MAX = 3;
    private const RATE_LIMIT_DECAY_SECONDS = 60;

    public function generate(User $user): OtpToken
    {
        // Invalidate all previous unused tokens for this user
        OtpToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $plain = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $token = OtpToken::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $token->plain_code = $plain;

        return $token;
    }

    /**
     * @throws ValidationException when rate limited, expired, or invalid
     */
    public function verify(User $user, string $code): bool
    {
        $key = 'otp:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'code' => __('Terlalu banyak percobaan. Coba lagi dalam :seconds detik.', ['seconds' => $seconds]),
            ]);
        }

        $token = OtpToken::where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $code))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            RateLimiter::hit($key, self::RATE_LIMIT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'code' => __('Kode OTP tidak valid atau sudah kedaluwarsa.'),
            ]);
        }

        $token->update(['used_at' => now()]);
        RateLimiter::clear($key);

        return true;
    }
}
