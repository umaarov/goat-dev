@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <h2>Login</h2>

    {{-- Debug information - remove in production --}}
    @if(Session::has('_token'))
        <div style="padding: 10px; background: #f8f9fa; margin-bottom: 15px; font-size: 0.9em;">
            CSRF Token exists: {{ substr(Session::get('_token'), 0, 10) }}...
        </div>
    @else
        <div style="padding: 10px; background: #f8d7da; margin-bottom: 15px; font-size: 0.9em;">
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

    <div class="form-group" style="margin-top: 15px;">
        <a href="{{ route('auth.google') }}" class="button-link" style="background-color: #dd4b39;">Login with Google</a>
    </div>

    <p>Don't have an account? <a href="{{ route('register') }}">Register here</a></p>
@endsection
