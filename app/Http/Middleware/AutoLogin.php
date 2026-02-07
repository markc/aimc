<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutoLogin
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            $user = User::first();

            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
