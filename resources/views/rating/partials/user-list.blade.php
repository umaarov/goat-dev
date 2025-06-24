@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;
@endphp

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
                            <span class="text-sm font-bold podium-{{$index + 1}}">
                                {{ $index + 1 }}
                            </span>
                        @else
                            <span class="font-semibold text-gray-500 text-sm">{{ $index + 1 }}</span>
                        @endif
                    </div>

                    <div class="flex-shrink-0">
                        {{-- FIX: Updated logic for the image src attribute --}}
                        <img class="h-11 w-11 rounded-full object-cover"
                             src="{{ $user->profile_picture ? (Str::startsWith($user->profile_picture, 'http') ? $user->profile_picture : Storage::disk('public')->url($user->profile_picture)) : asset('images/default-avatar.png') }}"
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
