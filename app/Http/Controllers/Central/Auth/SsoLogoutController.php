<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\WorkspaceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SsoLogoutController extends Controller
{
    public function __invoke(Request $request, WorkspaceService $service): SymfonyResponse
    {
        return $service->singleLogout($request);
    }
}
