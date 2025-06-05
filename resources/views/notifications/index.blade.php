@extends('layouts.app')

@section('title', __('messages.notifications.page_title'))

@section('content')
    <div class="container mb-4 mx-auto px-4">

        {{-- Tab Navigation --}}
        <div class="mb-6 border-b border-gray-200">
            <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                <button onclick="openTab(event, 'globalNotifications')"
                        class="tab-link whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600 focus:outline-none">
                    {{ __('messages.notifications.global_tab') ?? 'Global Notifications' }}
                </button>
                <button onclick="openTab(event, 'personalNotifications')"
                        class="tab-link whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none">
                    {{ __('messages.notifications.personal_tab') ?? 'Personal Notifications' }}
                </button>
            </nav>
        </div>

        {{-- Tab Content --}}
        <div id="globalNotifications" class="tab-content">
            @auth
                @if(Auth::user()->username === 'goat')
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4">{{ __('messages.notifications.send_new_global') ?? 'Send New Global Notification' }}</h2>
                        <form action="{{ route('notifications.store') }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label for="message"
                                       class="block text-sm font-medium text-gray-700">{{ __('messages.notifications.message_label') }}</label>
                                <textarea name="message" id="message" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                          required maxlength="255">{{ old('message') }}</textarea>
                                @error('message')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                    {{ __('messages.notifications.send_button') }}
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            @endauth

            <div class="space-y-4">
                @if($globalNotifications->isEmpty())
                    <div class="bg-white shadow-md rounded-lg p-6 text-center">
                        <p class="text-gray-500">{{ __('messages.notifications.no_global_notifications') ?? 'No global notifications yet.' }}</p>
                    </div>
                @else
                    @foreach($globalNotifications as $notification)
                        <div
                            class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-gray-800">{{ $notification->message }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ __('messages.notifications.sent_by') }}
                                        @if($notification->user)
                                            <a href="{{ route('profile.show', ['username' => $notification->user->username]) }}"
                                               class="text-blue-600 hover:underline">
                                                {{ $notification->user->username }}
                                            </a>
                                        @else
                                            <span
                                                class="text-gray-400">{{ __('messages.notifications.unknown_user') ?? 'Unknown User' }}</span>
                                        @endif
                                        - {{ $notification->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <div id="personalNotifications" class="tab-content" style="display: none;">
            <div class="bg-white bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] p-6 text-center">
                <p class="text-gray-500">{{ __('messages.notifications.no_personal_notifications_placeholder') ?? 'Personal notifications will appear here in the future.' }}</p>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function openTab(event, tabId) {
                document.querySelectorAll('.tab-content').forEach(function (tabContent) {
                    tabContent.style.display = 'none';
                });

                document.querySelectorAll('.tab-link').forEach(function (tabLink) {
                    tabLink.classList.remove('border-blue-500', 'text-blue-600');
                    tabLink.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                });

                document.getElementById(tabId).style.display = 'block';

                event.currentTarget.classList.add('border-blue-500', 'text-blue-600');
                event.currentTarget.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            }

            document.addEventListener('DOMContentLoaded', function () {
                const firstTab = document.querySelector('.tab-link');
                if (firstTab) {
                    firstTab.click();
                }
            });
        </script>
    @endpush
@endsection
