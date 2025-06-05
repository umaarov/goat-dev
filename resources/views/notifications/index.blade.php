@extends('layouts.app')

@section('title', __('messages.notifications.page_title'))

@section('content')
    <div class="container mb-4">
{{--        <h1 class="text-2xl font-semibold text-gray-800 mb-6">{{ __('messages.notifications.header') }}</h1>--}}

        @auth
            @if(Auth::user()->username === 'goat')
                <div class="bg-white rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">{{ __('messages.notifications.send_new') }}</h2>
                    <form action="{{ route('notifications.store') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label for="message" class="block text-sm font-medium text-gray-700">{{ __('messages.notifications.message_label') }}</label>
                            <textarea name="message" id="message" rows="3" class="mt-1 block w-full rounded-md border-gray-300 border-2 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required maxlength="255">{{ old('message') }}</textarea>
                            @error('message')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('messages.notifications.send_button') }}
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        @endauth

        <div class="space-y-4">
            @if($notifications->isEmpty())
                <div class="bg-white shadow-md rounded-lg p-6 text-center">
                    <p class="text-gray-500">{{ __('messages.notifications.no_notifications') }}</p>
                </div>
            @else
                @foreach($notifications as $notification)
                    <div class="bg-white rounded-lg p-4 mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)]">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-800">{{ $notification->message }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ __('messages.notifications.sent_by') }}
                                    <a href="{{ route('profile.show', ['username' => $notification->user->username]) }}" class="text-blue-600 hover:underline">
                                        {{ $notification->user->username }}
                                    </a>
                                    - {{ $notification->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection
