@props([
    'botUsername' => config('services.telegram.bot_username'),
    'callbackRoute' => route('auth.telegram.callback'),
])

@if($botUsername)
    <div class="mb-2">
        <div id="telegram-login-button-container"
             class="w-full flex items-center justify-center bg-[#2AABEE] text-white py-2 rounded-md hover:bg-[#1E98D4] focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
            <svg class="w-5 h-5 flex-shrink-0 mr-2" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path
                    d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.57c-.28 1.13-1.04 1.4-1.74.88L14.25 16l-4.12 3.9c-.78.76-1.36.37-1.57-.49z"/>
            </svg>
            <span>{{ __('messages.auth.login_with_telegram') }}</span>
        </div>
    </div>

    <div id="telegram-script-wrapper" style="position: absolute; top: -9999px; left: -9999px;">
        <script async src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="{{ $botUsername }}"
                data-size="large"
                data-auth-url="{{ $callbackRoute }}"
                data-request-access="write"
        ></script>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Telegram Auth: Ready.');

            function positionTelegramOverlay() {
                const styledButton = document.getElementById('telegram-login-button-container');
                const scriptWrapper = document.getElementById('telegram-script-wrapper');

                if (styledButton && scriptWrapper) {
                    const rect = styledButton.getBoundingClientRect();

                    scriptWrapper.style.top = `${rect.top + window.scrollY}px`;
                    scriptWrapper.style.left = `${rect.left + window.scrollX}px`;
                    scriptWrapper.style.width = `${rect.width}px`;
                    scriptWrapper.style.height = `${rect.height}px`;
                    scriptWrapper.style.zIndex = '100';

                    const iframe = scriptWrapper.querySelector('iframe');
                    if (iframe) {
                        iframe.style.width = '100%';
                        iframe.style.height = '100%';
                        iframe.style.opacity = '0';
                        iframe.style.cursor = 'pointer';
                    }
                }
            }

            const maxAttempts = 50;
            let attempt = 0;
            const interval = setInterval(function () {
                const iframe = document.querySelector('#telegram-script-wrapper iframe');
                if (iframe) {
                    clearInterval(interval);
                    positionTelegramOverlay();
                    window.addEventListener('resize', positionTelegramOverlay);
                } else if (attempt++ > maxAttempts) {
                    clearInterval(interval);
                    console.error('Telegram widget did not load in time. Please check your domain in @BotFather.');
                }
            }, 100);
        });
    </script>
@else
    <div class="mb-2 p-2 text-center bg-red-100 text-red-700 rounded-md">
        Telegram login is not configured.
    </div>
@endif
