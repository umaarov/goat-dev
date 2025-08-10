@extends('layouts.app')

@section('title', __('messages.notifications.page_title'))

@section('content')
    <div class="container mb-4 mx-auto px-4">

        <div class="max-w-3xl mx-auto">
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] dark:shadow-[inset_0_0_0_0.5px_rgba(255,255,255,0.1)]">
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($notifications as $notification)
                        <li class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150
                        {{ is_null($notification->read_at) ? 'bg-blue-50 dark:bg-blue-900/50' : '' }}">
                            @php
                                $notificationType = class_basename($notification->type);
                                $data = $notification->data;
                                $commentId = $data['comment_id'] ?? $data['reply_id'] ?? 0;
                                $postUrl = route('posts.show', [
                                    'post' => $data['post_id'] ?? 0,
                                    '#comment-' . $commentId
                                    ]);
                            @endphp

                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    {{-- Icons have vibrant colors that work well on both themes --}}
                                    @if($notificationType === 'CommentLiked')
                                        <svg class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                                                  clip-rule="evenodd"></path>
                                        </svg>
                                    @elseif($notificationType === 'NewReplyToYourComment')
                                        <svg class="h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg"
                                             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 016 6v3"/>
                                        </svg>
                                    @elseif($notificationType === 'YouWereMentioned')
                                        <svg class="h-6 w-6 text-green-500" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                        </svg>
                                    @endif
                                </div>

                                <div class="flex-grow">
                                    <p class="text-sm text-gray-800 dark:text-gray-200">
                                        @if($notificationType === 'CommentLiked')
                                            <a href="{{ route('profile.show', $data['liker_name']) }}"
                                               class="font-bold hover:underline">{{ $data['liker_name'] }}</a>
                                            {{ __('messages.notifications.liked_your_comment') }}:
                                            "{{ Str::limit($data['comment_content'], 50) }}"
                                        @elseif($notificationType === 'NewReplyToYourComment')
                                            <a href="{{ route('profile.show', $data['replier_name']) }}"
                                               class="font-bold hover:underline">{{ $data['replier_name'] }}</a>
                                            {{ __('messages.notifications.replied_to_your_comment') }}:
                                            "{{ Str::limit($data['reply_content'], 50) }}"
                                        @elseif($notificationType === 'YouWereMentioned')
                                            <a href="{{ route('profile.show', $data['mentioner_name']) }}"
                                               class="font-bold hover:underline">{{ $data['mentioner_name'] }}</a>
                                            {{ __('messages.notifications.mentioned_you_in_comment') }}:
                                            "{{ Str::limit($data['comment_content'], 50) }}"
                                        @endif
                                    </p>
                                    <a href="{{ $postUrl }}"
                                       class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </a>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="p-6 text-center">
                            <p class="text-gray-500 dark:text-gray-400">{{ __('messages.notifications.no_personal_notifications_placeholder') ?? 'You have no notifications yet.' }}</p>
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        </div>
    </div>
@endsection
