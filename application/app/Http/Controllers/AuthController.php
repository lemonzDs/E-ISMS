<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = Str::lower($data['email']).'|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Terlalu banyak percubaan. Cuba semula selepas satu minit.');
        if (! Auth::attempt($data + ['is_active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'E-mel atau kata laluan tidak sah, atau akaun tidak aktif.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended($request->user()->role === 'admin' ? '/admin/users' : '/documents');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
