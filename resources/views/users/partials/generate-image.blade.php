<div class="mt-8 border-t border-gray-200 pt-8">
    <div>
        <h3 class="text-lg font-semibold leading-6 text-gray-900">
            Generate Profile Picture with AI
        </h3>
        <p class="mt-1 text-sm text-gray-500">
            Describe the profile picture you want, and our AI will create it for you. Be creative!
        </p>
    </div>

    @php
        use Illuminate\Support\Carbon;$today = Carbon::today();
        $lastGenDate = $user->last_ai_generation_date ? Carbon::parse($user->last_ai_generation_date) : null;
        $monthlyLimit = 5;
        $dailyLimit = 2;
        $monthlyCount = ($lastGenDate && $lastGenDate->isSameMonth($today)) ? $user->ai_generations_monthly_count : 0;
        $dailyCount = ($lastGenDate && $lastGenDate->isSameDay($today)) ? $user->ai_generations_daily_count : 0;
        $monthlyRemaining = $monthlyLimit - $monthlyCount;
        $dailyRemaining = $dailyLimit - $dailyCount;
    @endphp

    <div class="mt-6">
        <label for="ai-prompt" class="block text-sm font-medium text-gray-700">Prompt</label>
        <div class="mt-1">
            <textarea id="ai-prompt" name="ai_prompt" rows="3"
                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                      placeholder="A majestic lion wearing a crown, studio lighting, hyperrealistic..."></textarea>
        </div>
    </div>

    <div class="mt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <button type="button" id="generate-ai-image-btn"
                class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-800 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                @if($monthlyRemaining <= 0 || $dailyRemaining <= 0) disabled @endif>
            <svg id="generate-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                 fill="currentColor">
                <path
                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
            <svg id="loading-spinner" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white hidden"
                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span id="generate-text">Generate Image</span>
        </button>
        <div class="text-sm text-gray-600">
            <p>Limits:
                <span id="daily-remaining" class="font-medium">{{ $dailyRemaining }}</span> Today,
                <span id="monthly-remaining" class="font-medium">{{ $monthlyRemaining }}</span> This Month
            </p>
        </div>
    </div>
    <div id="ai-error-message" class="mt-2 text-sm font-medium text-red-600"></div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const generateBtn = document.getElementById('generate-ai-image-btn');
            const promptInput = document.getElementById('ai-prompt');
            const errorMessageContainer = document.getElementById('ai-error-message');
            const dailyRemainingEl = document.getElementById('daily-remaining');
            const monthlyRemainingEl = document.getElementById('monthly-remaining');
            const profilePicElements = document.querySelectorAll('.profile-picture-display');

            const generateIcon = document.getElementById('generate-icon');
            const loadingSpinner = document.getElementById('loading-spinner');
            const generateText = document.getElementById('generate-text');

            generateBtn.addEventListener('click', async function () {
                const prompt = promptInput.value.trim();
                if (prompt.length < 10) {
                    errorMessageContainer.textContent = 'Prompt must be at least 10 characters.';
                    return;
                }

                errorMessageContainer.textContent = '';
                generateBtn.disabled = true;
                generateIcon.classList.add('hidden');
                loadingSpinner.classList.remove('hidden');
                generateText.textContent = 'Generating...';

                try {
                    const response = await fetch('{{ route("profile.picture.generate") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({prompt: prompt})
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || 'An unknown error occurred.');
                    }

                    if (data.success) {
                        const newImageUrlWithTimestamp = data.new_image_url + '?t=' + new Date().getTime();
                        profilePicElements.forEach(img => img.src = newImageUrlWithTimestamp);

                        dailyRemainingEl.textContent = data.daily_remaining;
                        monthlyRemainingEl.textContent = data.monthly_remaining;
                        promptInput.value = '';

                        if (data.daily_remaining <= 0 || data.monthly_remaining <= 0) {
                            generateBtn.disabled = true;
                        }
                    }

                } catch (error) {
                    errorMessageContainer.textContent = error.message;
                } finally {
                    generateIcon.classList.remove('hidden');
                    loadingSpinner.classList.add('hidden');
                    generateText.textContent = 'Generate Image';
                    if (parseInt(dailyRemainingEl.textContent) > 0 && parseInt(monthlyRemainingEl.textContent) > 0) {
                        generateBtn.disabled = false;
                    }
                }
            });
        });
    </script>
@endpush
