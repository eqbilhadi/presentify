<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\SsoAuthorizeRequest;
use App\Models\User;
use App\Services\Auth\SsoService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SsoAuthorizeController extends Controller
{
    public function __invoke(SsoAuthorizeRequest $request, SsoService $service): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $service->authorizeSpInitiated(
            $user,
            $request->string('domain')->toString(),
            $request->string('state')->toString(),
            $request,
        );
    }
}
