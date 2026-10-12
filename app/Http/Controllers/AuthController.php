<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['identity' => ['required', 'string', 'max:255'], 'password' => ['required', 'string'], 'role' => ['required', 'in:student,teacher,admin']]);
        $field = filter_var($data['identity'], FILTER_VALIDATE_EMAIL) ? 'email' : 'university_id';
        if (! Auth::attempt([$field => $data['identity'], 'password' => $data['password'], 'role' => $data['role'], 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['identity' => 'The credentials or selected role are incorrect, or your account is disabled.'])->onlyInput('identity', 'role');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'university_id' => ['required', 'string', 'max:30', 'unique:users,university_id'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $user = User::create($data + ['portfolio_slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Welcome to LUMiNA. Complete your profile or import your resume to get started.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function forgot(): View
    {
        return view('auth.forgot');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If that email belongs to an account, a password reset link has been sent.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(8)]]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
