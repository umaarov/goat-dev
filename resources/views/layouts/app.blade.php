<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: sans-serif; padding: 1em; max-width: 900px; margin: auto; }
        nav ul { list-style: none; padding: 0; margin: 0 0 1em 0; display: flex; gap: 1em; }
        nav a { text-decoration: none; }
        .alert { padding: 0.8em; margin-bottom: 1em; border: 1px solid transparent; border-radius: 0.25rem; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-error, .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }
        .form-group { margin-bottom: 1em; }
        .form-group label { display: block; margin-bottom: 0.3em; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group textarea { width: 100%; padding: 0.5em; box-sizing: border-box; border: 1px solid #ccc; }
        .form-group input[type="file"] { padding: 0.3em; }
        .error-message { color: red; font-size: 0.85em; margin-top: 0.2em; }
        button, input[type="submit"], .button-link { padding: 0.6em 1.2em; background-color: #007bff; color: white; border: none; border-radius: 0.25rem; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9em; }
        button:hover, input[type="submit"]:hover, .button-link:hover { background-color: #0056b3; }
        .post-card { border: 1px solid #eee; padding: 1em; margin-bottom: 1.5em; }
        .post-header { display: flex; align-items: center; gap: 0.8em; margin-bottom: 0.8em; }
        .post-header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .post-options { display: grid; grid-template-columns: 1fr 1fr; gap: 1em; margin: 1em 0; text-align: center; }
        .post-options img { max-width: 100%; height: auto; max-height: 200px; object-fit: contain; margin-bottom: 0.5em; }
        .post-actions { margin-top: 1em; display: flex; gap: 1em; align-items: center; }
        .post-stats { font-size: 0.9em; color: #555; margin-top: 0.5em; }
        .post-comment-form textarea { width: 100%; margin-bottom: 0.5em; }
        .post-comments { margin-top: 1em; border-top: 1px solid #eee; padding-top: 1em; }
        .comment { margin-bottom: 0.8em; padding-bottom: 0.8em; border-bottom: 1px solid #f5f5f5; }
        .comment:last-child { border-bottom: none; }
        .comment-header { display: flex; align-items: center; gap: 0.5em; font-size: 0.9em; margin-bottom: 0.3em; }
        .comment-header img { width: 25px; height: 25px; border-radius: 50%; object-fit: cover; }
        .comment-actions { font-size: 0.8em; margin-left: auto; }
        .pagination { margin-top: 2em; }
        .profile-header { margin-bottom: 2em; display: flex; align-items: center; gap: 1.5em; }
        .profile-header img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; }
        .profile-tabs button { background: #eee; color: #333; }
        .profile-tabs button.active { background: #007bff; color: white; }
        .hidden { display: none; }
        .vote-bar-container { background-color: #e9ecef; border-radius: .25rem; overflow: hidden; display: flex; height: 20px; margin-top: 5px; }
        .vote-bar { display: flex; flex-direction: column; justify-content: center; color: white; text-align: center; white-space: nowrap; background-color: #007bff; transition: width .6s ease; font-size: 0.75rem; }
        .vote-bar-1 { background-color: #007bff; }
        .vote-bar-2 { background-color: #28a745; }
    </style>
    @stack('styles')
</head>
<body>

<nav>
    <ul>
        <li><a href="{{ route('home') }}">Home</a></li>
        <li><a href="{{ route('search') }}">Search</a></li>
        @auth
            <li><a href="{{ route('posts.create') }}">Create Post</a></li>
            <li><a href="{{ route('profile.show', ['username' => Auth::user()->username]) }}">Profile</a></li>
            <li>
                <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" style="background:none; border:none; padding:0; color: #007bff; cursor:pointer; font-size: 1em;">Logout</button>
                </form>
            </li>
        @else
            <li><a href="{{ route('login') }}">Login</a></li>
            <li><a href="{{ route('register') }}">Register</a></li>
        @endauth
    </ul>
</nav>

<main>
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
    @if (session('info'))
        <div class="alert alert-info">
            {{ session('info') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

@stack('scripts')
</body>
</html>
