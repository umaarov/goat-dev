@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
    <h2>Change Password</h2>

    <form method="POST" action="{{ route('password.change') }}">
        @csrf

        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input id="current_password" type="password" name="current_password" required>
            @error('current_password')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="new_password">New Password</label>
            <input id="new_password" type="password" name="new_password" required>
            @error('new_password')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="new_password_confirmation">Confirm New Password</label>
            <input id="new_password_confirmation" type="password" name="new_password_confirmation" required>
        </div>

        <div class="form-group">
            <button type="submit">Change Password</button>
            <a href="{{ route('profile.edit') }}" class="button-link">Cancel</a>
        </div>
    </form>
@endsection
