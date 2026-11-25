<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Auth\OtpService;
use App\Services\Auth\SsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OtpVerificationController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('auth/verify-otp', [
            'email' => $request->session()->get('otp_email', ''),
        ]);
    }

    public function send(SendOtpRequest $request, OtpService $service): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();

        $token = $service->generate($user);

        $user->notify(new OtpCodeNotification($token->plain_code));

        $request->session()->put('otp_email', $user->email);
        $request->session()->put('otp_user_id', $user->id);

        return redirect()->route('auth.otp.show')
            ->with('status', 'Kode OTP telah dikirim ke email Anda.');
    }

    public function store(VerifyOtpRequest $request, OtpService $service, SsoService $ssoService): SymfonyResponse
    {
        $userId = $request->session()->get('otp_user_id');

        if (! $userId) {
            return redirect()->route('auth.otp.show')
                ->withErrors(['code' => 'Sesi OTP tidak ditemukan. Silakan minta kode baru.']);
        }

        $user = User::query()->whereKey($userId)->firstOrFail();

        $service->verify($user, $request->validated('code'));

        $request->session()->forget(['otp_email', 'otp_user_id']);

        Auth::login($user);
        $request->session()->regenerate();

        return $ssoService->resolvePostLoginResponse($request, $user);
    }
}
