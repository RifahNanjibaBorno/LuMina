@extends('layouts.auth')
@section('title', 'Welcome back')
@section('subtitle', 'Sign in to continue to your workspace.')
@section('content')
<form action="{{ route('login.store') }}" method="post" class="auth-form">@csrf
    <fieldset class="role-tabs"><legend class="sr-only">Account role</legend>@foreach(['student', 'teacher', 'admin'] as $role)<label><input type="radio" name="role" value="{{ $role }}" @checked(old('role', 'student') === $role)><span>{{ ucfirst($role) }}</span></label>@endforeach</fieldset>
    <label>University ID / Email<input name="identity" value="{{ old('identity') }}" autocomplete="username" required placeholder="University ID or email"></label>
    <label>Password<input name="password" type="password" autocomplete="current-password" required placeholder="Enter your password"></label>
    <div class="form-inline"><label class="check-label"><input type="checkbox" name="remember" value="1">Remember me</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
    <button class="button button-primary">Log in</button>
</form>
<div class="auth-divider"></div><div class="auth-switch"><span>New student?</span><a class="button" href="{{ route('register') }}">Create account</a></div>
<p class="auth-help">Registration collects basic account details first. Profile data can later be imported from a resume.</p>
@endsection
