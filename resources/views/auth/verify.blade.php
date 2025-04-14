@extends('layouts.app')

@section('title', 'Verify Email')

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-2xl font-semibold mb-4 text-blue-800">Verify Your Email Address</h2>

            @if (session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            <div class="mb-4">
                <p class="text-gray-700">
                    Before proceeding, please check your email for a verification link.
                    If you did not receive the email,
                </p>
            </div>

            <form class="mb-4" method="POST" action="{{ route('verification.resend') }}">
                @csrf
                <button type="submit"
                        class="w-full bg-blue-800 text-white py-3 rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Click here to request another
                </button>
            </form>

            <div class="text-center text-gray-600 mt-4">
                <a href="{{ route('home') }}" class="text-blue-800 hover:underline">
                    Return to Home
                </a>
            </div>
        </div>
    </div>
@endsection
