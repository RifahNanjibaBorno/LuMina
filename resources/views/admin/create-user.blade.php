@extends('layouts.workspace')
@section('title', 'Create user')
@section('content')
<form method="post" action="{{ route('users.store') }}" class="card editor-form">
    @csrf
    <h2>Create a teacher or student account</h2>
    <div class="form-grid">
        <label>Full name<input name="name" value="{{ old('name') }}" maxlength="100" autocomplete="name" required></label>
        <label>Email<input name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></label>
        <label>University ID<input name="university_id" value="{{ old('university_id') }}" maxlength="30" required></label>
        <label>Role<select data-enhanced-select name="role" required>
            @foreach(['student', 'teacher'] as $role)
                <option value="{{ $role }}" @selected(old('role', 'student') === $role)>{{ ucfirst($role) }}</option>
            @endforeach
        </select></label>
        <label>Password<input name="password" type="password" minlength="8" autocomplete="new-password" required><small>Use at least 8 characters.</small></label>
        <label>Confirm password<input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required></label>
    </div>
    <p>The account will be active immediately. Share the login details with the user.</p>
    <div class="form-actions"><a class="button" href="{{ route('users.index') }}">Cancel</a><button class="button button-primary">Create account</button></div>
</form>
@endsection
