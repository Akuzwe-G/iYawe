<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(Request $request): View
    {

        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $data['email'] = Str::lower($data['email']);
        $key = Str::transliterate($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('Too many attempts. Try again shortly.')]);
        }
        if (! Auth::attempt([...$data, 'active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('These credentials do not match an active account.')]);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget('company_id');

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgot(Request $request): View
    {
        abort_unless(config('iyawe.password_reset_enabled'), 404);

        return view('auth.password', ['reset' => false]);
    }

    public function emailReset(Request $request): RedirectResponse
    {
        abort_unless(config('iyawe.password_reset_enabled'), 404);
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => Str::lower($request->string('email')->toString()), 'active' => true]);

        return back()->with('status', __('If your account is eligible, a reset link has been sent.'));
    }

    public function reset(Request $request, string $token): View
    {
        abort_unless(config('iyawe.password_reset_enabled'), 404);

        return view('auth.password', ['reset' => true, 'token' => $token, 'email' => $request->query('email')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        abort_unless(config('iyawe.password_reset_enabled'), 404);
        $data = $request->validate(['token' => 'required', 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset([...$data, 'active' => true], function (User $user, string $password) {
            $user->password = Hash::make($password);
            $user->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __('Password updated.'));
    }

    public function locale(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:en,rw']]);
        $request->session()->put('locale', $data['locale']);
        if ($request->user()) {
            $request->user()->forceFill(['locale' => $data['locale']])->save();
        }

        return back();
    }
}
