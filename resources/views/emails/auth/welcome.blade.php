<x-mail::message>
    # Welcome to {{ config('app.name') }}, {{ $user->first_name }}! 👋

    We are thrilled to have you join our community.

    To get started, we recommend exploring your dashboard and customizing your profile.

    <x-mail::button :url="route('home')">
        Go to Your Dashboard
    </x-mail::button>

    If you have any questions or need assistance, feel free to visit our support center or reply to this email.

    Thanks,<br>
    The {{ config('app.name') }} Team
</x-mail::message>
