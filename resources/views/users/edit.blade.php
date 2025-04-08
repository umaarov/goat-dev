@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
    <h2>Edit Profile</h2>

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="first_name">First Name</label>
            <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}"
                   required>
            @error('first_name')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="last_name">Last Name (Optional)</label>
            <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}">
            @error('last_name')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username', $user->username) }}" required>
            @error('username')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="profile_picture">Update Profile Picture (Optional)</label>
            @php
                $profilePic = $user->profile_picture
                    ? (Str::startsWith($user->profile_picture, ['http', 'https'])
                        ? $user->profile_picture
                        : asset('storage/' . $user->profile_picture))
                    : asset('images/default-pfp.png');
            @endphp
            <p>Current: <img src="{{ $profilePic }}" alt="Current Profile Picture"></p>
            <input id="profile_picture" type="file" name="profile_picture"
                   accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
            @error('profile_picture')
            <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        <div class="form-group">
            <button type="submit">Update Profile</button>
            <a href="{{ route('profile.show', $user->username) }}" class="button-link">Cancel</a>
        </div>
    </form>

    @if($user->password)
        <p>
            <a href="{{ route('password.change.form') }}">Change Your Password</a>
        </p>
    @elseif($user->google_id)
        <p>
            Password change is not available for accounts created via Google login unless a password has been set.
        </p>
    @endif

@endsection
