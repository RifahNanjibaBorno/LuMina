@extends('layouts.auth')
@section('title', 'Create your account')
@section('subtitle', 'Start building your academic identity.')
@section('content')
<form action="{{ route('register.store') }}" method="post" class="auth-form register-form">@csrf
    <label>Full name<input name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="100"></label>
    <label>University ID<input name="university_id" value="{{ old('university_id') }}" required maxlength="30"></label>
    <label>Email<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></label>
    <label>Password<input name="password" type="password" autocomplete="new-password" minlength="8" required><small>Use at least 8 characters.</small></label>
    <label>Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" required></label>
    <button class="button button-primary">Create account</button>
</form>
<div class="auth-switch"><span>Already registered?</span><a href="{{ route('login') }}">Log in</a></div>
@endsection
