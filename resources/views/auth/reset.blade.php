@extends('layouts.auth')
@section('title', 'Choose a new password')
@section('subtitle', 'Secure your academic workspace.')
@section('content')
<form action="{{ route('password.update') }}" method="post" class="auth-form">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Email<input type="email" name="email" value="{{ old('email', $email) }}" required></label><label>New password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" required></label><button class="button button-primary">Reset password</button></form>
@endsection
