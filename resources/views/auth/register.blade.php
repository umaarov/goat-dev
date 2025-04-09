@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <h2>Register</h2>

    @if(Session::has('_token'))
        <div>
            CSRF Token exists: {{ substr(Session::get('_token'), 0, 10) }}...
        </div>
    @else
        <div>
            No CSRF Token found in session!
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label for="first_name">First Name</label>
            <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus>
            @error('first_name')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="last_name">Last Name (Optional)</label>
            <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}">
            @error('last_name')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required>
            @error('username')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>
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
            <label for="password-confirm">Confirm Password</label>
            <input id="password-confirm" type="password" name="password_confirmation" required>
        </div>

        <div class="form-group">
            <label for="profile_picture">Profile Picture (Optional)</label>
            <input id="profile_picture" type="file" name="profile_picture"
                   accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
            @error('profile_picture')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <button type="submit">Register</button>
        </div>
    </form>

    <div class="form-group">
        <a href="{{ route('auth.google') }}" class="button-link">Sign up with
            Google</a>
    </div>

    <p>Already have an account? <a href="{{ route('login') }}">Login here</a></p>
@endsection
