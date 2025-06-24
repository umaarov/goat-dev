<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-xl font-semibold text-gray-800">{{ $title }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
    </div>
    <ul class="divide-y divide-gray-200">
        @forelse($users as $index => $user)
            <li class="p-4 flex items-center space-x-4 transition-colors hover:bg-gray-50">
                <div class="flex items-center justify-center w-8 text-center">
                    @if($index < 3)
                        <span class="text-lg font-bold podium-{{$index + 1}}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M11.25 2.25a.75.75 0 00-1.5 0v1.134a3.752 3.752 0 00-2.821 1.446l-.001.001-3.32 4.013a.75.75 0 00.95 1.13l.001-.001 3.32-4.013a2.25 2.25 0 012.822-1.446V14.25a.75.75 0 001.5 0V2.25z"
                                      clip-rule="evenodd"/>
                                <path
                                    d="M4.152 10.533a.75.75 0 00-1.06 1.06l1.06-1.06zM15.848 10.533a.75.75 0 10-1.06-1.06l1.06 1.06zM8.375 16.125a.75.75 0 001.5 0h-1.5zM12.875 16.125a.75.75 0 001.5 0h-1.5zM4.152 10.533l-.53.53a.75.75 0 001.06 1.06L4.152 10.533zm11.696 0l.53.53a.75.75 0 00-1.06-1.06l.53-.53zM8.375 16.125V12h-1.5v4.125h1.5zm4.5 0V12h-1.5v4.125h1.5zM4.682 11.593l3.693 4.53v-1.06l-3.693-4.53-1.06 1.06zm6.576 4.53l3.693-4.53-1.06-1.06-3.693 4.53v1.06zM15.318 9.473a3.75 3.75 0 01-4.94 0l-1.06 1.06a5.25 5.25 0 006.92 0l-1.06-1.06z"/>
                            </svg>
                        </span>
                    @else
                        <span class="font-semibold text-gray-500 text-sm">{{ $index + 1 }}</span>
                    @endif
                </div>

                <a href="{{-- route('user.profile', $user->username) --}}">
                    <img class="h-11 w-11 rounded-full object-cover"
                         src="{{ $user->profile_picture ?? asset('images/default-avatar.png') }}"
                         alt="{{ $user->username }}">
                </a>
                <div class="flex-shrink-0">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">
                        <a href="{{-- route('user.profile', $user->username) --}}"
                           class="hover:underline">{{ $user->first_name }} {{ $user->last_name }}</a>
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
            </li>
        @empty
            <li class="p-8 text-center text-gray-500">
                {{ __('messages.ratings.no_users_found') }}
            </li>
        @endforelse
    </ul>
</div>
