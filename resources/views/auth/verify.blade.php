@extends('layouts.app')

@section('title', __('messages.auth.verify_email_title'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-2xl font-semibold mb-4 text-blue-800">{{ __('messages.auth.verify_email_heading') }}</h2>

            @if (session('resent'))
                <div class="mb-4 p-3 rounded-md bg-green-100 border border-green-300 text-green-700 text-sm"
                     role="alert">
                    {{ __('messages.auth.verify_email_sent_message') }}
                </div>
            @endif

            <p class="text-gray-700 mb-4">
                {{ __('messages.auth.verify_email_check_before_proceeding') }}
            </p>
            <p class="text-gray-700">
                {{ __('messages.auth.verify_email_if_not_receive') }},
            <form class="inline" method="POST" action="{{ route('verification.resend') }}">
                @csrf
                <button type="submit" class="text-blue-800 hover:underline focus:outline-none">
                    {{ __('messages.auth.verify_email_click_here_to_request_another') }}
                </button>
                .
            </form>
            </p>
        </div>
    </div>
@endsection
