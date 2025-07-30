@props([
    'botUsername' => config('services.telegram.bot_username'),
    'callbackRoute' => route('auth.telegram.callback'),
])

@if($botUsername)
    <div class="mb-2">
        <div id="telegram-login-button-container"
             class="w-full flex items-center justify-center bg-[#2AABEE] text-white py-2 rounded-md hover:bg-[#1E98D4] focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
            <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                <path
                    d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.17.9-.502 1.203-1.008 1.23-1.08.056-1.895-.72-2.968-1.42-1.492-.99-2.32-1.604-3.8-2.593-.284-.188-.506-.347-.78-.71-.038-.048-.07-.092-.102-.132-.383-.49-.284-1.01.213-1.528L13.87 8.32a.506.506 0 0 1 .497-.288c.283.006.471.144.536.216l-2.147 4.957-1.16-3.419a.488.488 0 0 1 .15-.497c.18-.134.4-.188.602-.182.22 0 .42.06.636.148l4.34 2.158.465-4.354z"/>
            </svg>
            <span>{{ __('messages.auth.login_with_telegram') }}</span>
        </div>
    </div>

    <div id="telegram-script-wrapper" style="display: none;">
        <script async src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="{{ $botUsername }}"
                data-size="large"
                data-auth-url="{{ $callbackRoute }}"
                data-request-access="write"
                onload="
                    var container = document.getElementById('telegram-login-button-container');
                    var scriptWrapper = document.getElementById('telegram-script-wrapper');

                    var iframe = scriptWrapper.querySelector('iframe');

                    if (iframe) {
                        container.appendChild(iframe);
                    }
                "
        ></script>
    </div>

    <style>
        #telegram-login-button-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        #telegram-login-button-container {
            position: relative;
        }
    </style>
@else
    <div class="mb-2 p-2 text-center bg-red-100 text-red-700 rounded-md">
        Telegram login is not configured.
    </div>
@endif
