<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifiedAgricultor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Solo permitir acceso a 'admin' o 'agricultor'
        if (in_array($user->role, ['admin', 'agricultor'])) {
            return $next($request);
        }

        return redirect()->route('login')->withErrors('Acceso denegado. Cuenta no autorizada.');
    }
}