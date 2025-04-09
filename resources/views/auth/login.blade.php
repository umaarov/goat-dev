@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <h2>Login</h2>

    @if(Session::has('_token'))
        <div>
            CSRF Token exists: {{ substr(Session::get('_token'), 0, 10) }}...
        </div>
    @else
        <div>
            No CSRF Token found in session!
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>
            @error('password')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="remember">
                <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                Remember Me
            </label>
        </div>

        <div class="form-group">
            <button type="submit">Login</button>
        </div>
    </form>

    <div class="form-group">
        <a href="{{ route('auth.google') }}" class="button-link">Login with
            Google</a>
    </div>

    <p>Don't have an account? <a href="{{ route('register') }}">Register here</a></p>
@endsection
