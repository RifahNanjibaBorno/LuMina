@extends('layouts.auth')
@section('title', 'Reset your password')
@section('subtitle', 'We will send a recovery link to your account email.')
@section('content')
<form action="{{ route('password.email') }}" method="post" class="auth-form">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label><button class="button button-primary">Send reset link</button></form><p class="auth-help"><a href="{{ route('login') }}">Back to log in</a></p>
@endsection
