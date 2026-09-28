<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every owner dashboard route reads auth()->user()->shop. A self-service
 * owner has none until they finish shop setup, so send them there first.
 */
class EnsureOwnerHasShop
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->shop()->exists()) {
            return redirect()->route('onboarding.create');
        }

        return $next($request);
    }
}
