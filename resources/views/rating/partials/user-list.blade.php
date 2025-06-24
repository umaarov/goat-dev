<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-xl font-semibold text-gray-800">{{ $title }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
    </div>
    <ul class="divide-y divide-gray-200">
        @forelse($users as $index => $user)
            <li>
                <a href="{{ route('profile.show', ['username' => $user->username]) }}"
                   class="p-4 flex items-center space-x-4 transition-colors hover:bg-gray-50/75">
                    <div class="flex items-center justify-center w-8 text-center">
                        @if($index < 3)
                            <span class="text-lg font-bold podium-{{$index + 1}}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                     class="w-6 h-6">
                                    <path fill-rule="evenodd"
                                          d="M12.963 2.286a.75.75 0 00-1.071 1.071l2.122 2.122a.75.75 0 001.07-1.07l-2.121-2.122zM10.463 5.483a.75.75 0 00-1.07 1.07l2.121 2.121a.75.75 0 001.07-1.07l-2.121-2.12zM9.999 12.375a.75.75 0 01.75-.75h2.5a.75.75 0 010 1.5h-2.5a.75.75 0 01-.75-.75zM8.624 6.953a.75.75 0 00-1.07 1.07l2.12 2.121a.75.75 0 001.07-1.07l-2.12-2.12zM6.375 9.75a.75.75 0 01.75-.75h8.5a.75.75 0 010 1.5h-8.5a.75.75 0 01-.75-.75zM4.125 12a.75.75 0 01.75-.75h14a.75.75 0 010 1.5h-14a.75.75 0 01-.75-.75zM12 3.75a8.25 8.25 0 100 16.5 8.25 8.25 0 000-16.5zM4.67 15.11a6.75 6.75 0 019.52-9.52l-9.52 9.52zM19.33 8.89a6.75 6.75 0 01-9.52 9.52l9.52-9.52z"
                                          clip-rule="evenodd"/>
                                </svg>
                            </span>
                        @else
                            <span class="font-semibold text-gray-500 text-sm">{{ $index + 1 }}</span>
                        @endif
                    </div>

                    <div class="flex-shrink-0">
                        <img class="h-11 w-11 rounded-full object-cover"
                             src="{{ $user->profile_picture ?? asset('images/default-avatar.png') }}"
                             alt="{{ $user->username }}">
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">
                            {{ $user->first_name }} {{ $user->last_name }}
                        </p>
                        <p class="text-sm text-gray-500 truncate">
                            &#64;{{ $user->username }}
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        @php
                            $value = $user->{$value_accessor};
                            $label = $value === 1 ? $value_label_singular : $value_label_plural;
                        @endphp
                        <p class="text-md font-bold text-gray-900">{{ number_format($value) }}</p>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">{{ $label }}</p>
                    </div>
                </a>
            </li>
        @empty
            <li class="p-8 text-center text-gray-500">
                {{ __('messages.ratings.no_users_found') }}
            </li>
        @endforelse
    </ul>
</div>
