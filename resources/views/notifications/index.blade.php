@extends('layouts.app')

@section('title', __('messages.notifications.page_title'))

@section('content')
    <div class="container mb-4 mx-auto px-4">

        <div class="max-w-3xl mx-auto">
{{--            <h1 class="text-2xl font-bold text-gray-800 mb-6">{{ __('messages.notifications.page_title') ?? 'Notifications' }}</h1>--}}

            <div class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)]">
                <ul class="divide-y divide-gray-200">
                    @forelse ($notifications as $notification)
                        <li class="p-4 hover:bg-gray-50 transition-colors duration-150
                        {{ is_null($notification->read_at) ? 'bg-blue-50' : '' }}">
                            @php
                                $notificationType = class_basename($notification->type);
                                $data = $notification->data;

                                $commentId = $data['comment_id'] ?? $data['reply_id'] ?? 0;

                                $postUrl = route('posts.showSlug', [
                                    'id' => $data['post_id'] ?? 0,
                                    'slug' => 'post',
                                    'comment' => $commentId
                                ]);
                            @endphp

                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    @if($notificationType === 'CommentLiked')
                                        <svg class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                                        </svg>
                                    @elseif($notificationType === 'NewReplyToYourComment')
                                        <svg class="h-6 w-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm-1 11H7v-2h2v2zm1-3H8V5h4v5h-2z"></path>
                                        </svg>
                                    @elseif($notificationType === 'YouWereMentioned')
                                        <svg class="h-6 w-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                        </svg>
                                    @endif
                                </div>

                                <div class="flex-grow">
                                    <p class="text-sm text-gray-800">
                                        @if($notificationType === 'CommentLiked')
                                            <a href="{{ route('profile.show', $data['liker_name']) }}" class="font-bold hover:underline">{{ $data['liker_name'] }}</a>
                                            liked your comment: "{{ $data['comment_content'] }}"
                                        @elseif($notificationType === 'NewReplyToYourComment')
                                            <a href="{{ route('profile.show', $data['replier_name']) }}" class="font-bold hover:underline">{{ $data['replier_name'] }}</a>
                                            replied to your comment: "{{ $data['reply_content'] }}"
                                        @elseif($notificationType === 'YouWereMentioned')
                                            <a href="{{ route('profile.show', $data['mentioner_name']) }}" class="font-bold hover:underline">{{ $data['mentioner_name'] }}</a>
                                            mentioned you in a comment: "{{ $data['comment_content'] }}"
                                        @endif
                                    </p>
                                    <a href="{{ $postUrl }}" class="text-xs text-blue-600 hover:underline">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </a>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="p-6 text-center">
                            <p class="text-gray-500">{{ __('messages.notifications.no_personal_notifications_placeholder') ?? 'You have no notifications yet.' }}</p>
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
