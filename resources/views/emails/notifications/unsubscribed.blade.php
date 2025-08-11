<x-mail::message>
    # Unsubscribe Confirmation

    Hi {{ $user->first_name }},

    This email confirms that you have been unsubscribed from new post notifications.

    This action was initiated on **{{ now()->toDayDateTimeString() }} (Tashkent Time)** from the IP address: **{{ $ipAddress }}**.

    If you did not request this or wish to receive notifications again, you can securely manage your preferences in your profile settings at any time.

    <x-mail::button :url="route('profile.edit')">
        Manage Preferences
    </x-mail::button>

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>
