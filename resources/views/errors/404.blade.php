@extends('layouts.app')

@section('title', '404')

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            @php
                $path = request()->path();
                $isUserProfile = preg_match('/^@[\w\-\.]+$/', $path);
            @endphp

            @if($isUserProfile)
                <h1 class="text-2xl font-semibold mb-3">404</h1>
                <p class="text-gray-600 mb-6">Even the GOAT gets lost sometimes. The user you're looking for might not
                    exist.</p>
                <div
                    class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm">
                    <img src="{{ asset('images/lost_goat.jpg') }}" alt="Lost Goat"
                         class="h-64 w-full object-cover rounded-lg">
                </div>
            @else
                <h1 class="text-2xl font-semibold mb-3">404/h1>
                    <p class="text-gray-600 mb-8">Even the GOAT gets lost sometimes. The page you're looking for either
                        moved or never existed</p>
                    <div
                        class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm">
                        <img src="{{ asset('images/lost_goat.jpg') }}" alt="Lost Goat"
                             class="h-64 w-full object-cover rounded-lg">
                    </div>
            @endif
        </div>
    </div>
@endsection
