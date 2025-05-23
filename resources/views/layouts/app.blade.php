<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'GOAT'))</title>
    <link rel="icon" href="{{ asset('images/goat.jpg') }}" type="image/png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('styles')
    @vite('resources/css/app.css')
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>
<body class="flex flex-col min-h-screen bg-gray-100">
<!-- Fixed top toolbar -->
<nav
    class="fixed top-0 left-0 right-0 bg-white rounded-b-xl shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] z-10 h-16 flex items-center px-4 max-w-[450px] mx-auto">
    <div class="w-full max-w-md mx-auto flex items-center justify-between">
        <div class="w-6"></div>
        <a href="{{route('home')}}">
            <img src="{{ asset('images/main_logo.png') }}" alt="Logo" class="h-23 w-23 cursor-pointer">
        </a>
        <div>
            @auth
                <a href="{{ route('profile.show', ['username' => Auth::user()->username]) }}" class="text-black">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="text-black">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </a>
            @endauth
        </div>
    </div>
</nav>

<!-- Main content area with fixed width -->
<main class="flex-grow pt-20 mx-auto w-full max-w-[450px] px-4 pb-16">
    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if (session('info'))
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-md mb-4">
            {{ session('info') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
    <footer class="mb-8 text-center text-gray-500 text-xs leading-relaxed px-4">
        <div class="space-y-4">
            <div class="flex flex-wrap justify-center gap-4 text-sm text-blue-800">
                <a href="{{ route('about') }}" class="hover:underline">About Us</a>
                <a href="{{ route('terms') }}" class="hover:underline">Terms of Use</a>
                <a href="{{ route('sponsorship') }}" class="hover:underline">Sponsorship</a>
                <a href="{{ route('ads') }}" class="hover:underline">Ads</a>
            </div>

            <p class="font-semibold">GOAT © 2025</p>
        </div>
    </footer>

</main>


<!-- Fixed bottom navbar -->
<nav
    class="fixed bottom-0 left-0 right-0 bg-white shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] rounded-t-xl z-10 h-20 max-w-[450px] mx-auto">
    <div class="w-full max-w-md mx-auto flex items-center justify-around h-full">
        <a href="{{ route('home') }}"
           class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="{{ route('search') }}"
           class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <span class="text-xs mt-1">Search</span>
        </a>
        <a href="{{ route('posts.create') }}"
           class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="text-xs mt-1">Post</span>
        </a>
        @auth
            <a href="{{ route('profile.show', ['username' => Auth::user()->username]) }}"
               class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span class="text-xs mt-1">Account</span>
            </a>
        @else
            <a href="{{ route('login') }}"
               class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span class="text-xs mt-1">Account</span>
            </a>
        @endauth
    </div>
</nav>
<x-toast/>
@stack('scripts')
<script src="{{ asset('js/toast.js') }}"></script>
<div id="voteCountTooltip" class="fixed hidden bg-gray-700 text-white text-xs px-2 py-1 rounded-md shadow-lg z-[10001]"
     style="pointer-events: none; white-space: nowrap;">
</div>
<div id="imageViewerModal"
     class="fixed inset-0 bg-black bg-opacity-85 flex items-center justify-center z-[9999] hidden p-4 transition-opacity duration-300 ease-in-out opacity-0">
    <div
        class="relative bg-transparent p-0 rounded-lg shadow-xl max-w-full max-h-full flex items-center justify-center">
        <img id="imageViewerModalImage" src="" alt="Full screen image"
             class="max-w-[90vw] max-h-[90vh] object-contain rounded-md">
        <button id="imageViewerModalClose" title="Close image viewer"
                class="absolute top-[-15px] right-[-15px] md:top-2 md:right-2 bg-gray-700 bg-opacity-60 text-white rounded-full p-2 leading-none hover:bg-opacity-90 focus:outline-none z-10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('imageViewerModal');
        const modalImage = document.getElementById('imageViewerModalImage');
        const closeModalButton = document.getElementById('imageViewerModalClose');
        const tooltipElement = document.getElementById('voteCountTooltip');

        if (!modal || !modalImage || !closeModalButton) {
            console.warn('Image viewer modal elements not found. Zoom functionality will not work.');
            return;
        }

        if (!tooltipElement) {
            console.warn('voteCountTooltip element not found.');
            return;
        }

        let currentHoveredButton = null;
        const postsContainer = document.querySelector('main')

        if (!postsContainer) {
            console.warn('Posts container for tooltip delegation not found.');
            return;
        }

        function positionTooltip(mouseX, mouseY) {
            // Ensure tooltip is not hidden to get accurate dimensions for positioning
            const wasHidden = tooltipElement.classList.contains('hidden');
            if (wasHidden) {
                tooltipElement.classList.remove('hidden');
                tooltipElement.style.opacity = '0'; // Keep it invisible for measurement
            }

            const tooltipRect = tooltipElement.getBoundingClientRect();

            if (wasHidden) { // Hide it back if it was originally hidden
                tooltipElement.classList.add('hidden');
                tooltipElement.style.opacity = ''; // Reset opacity
            }


            let x = mouseX + 15; // Offset from cursor
            let y = mouseY + 15;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const buffer = 10; // Buffer from viewport edges

            // Adjust X position
            if (x + tooltipRect.width + buffer > viewportWidth) {
                x = mouseX - tooltipRect.width - 15; // Place to the left
            }
            if (x < buffer) { // Prevent going off left edge
                x = buffer;
            }

            // Adjust Y position
            if (y + tooltipRect.height + buffer > viewportHeight) {
                y = mouseY - tooltipRect.height - 15; // Place above
            }
            if (y < buffer) { // Prevent going off top edge
                y = buffer;
            }

            tooltipElement.style.left = `${x}px`;
            tooltipElement.style.top = `${y}px`;
        }


        postsContainer.addEventListener('mouseover', function (event) {
            const button = event.target.closest('.vote-button[data-show-tooltip="true"]');
            if (button) {
                currentHoveredButton = button;
                const postArticle = button.closest('article[id^="post-"]');
                if (!postArticle) return;

                const option = button.dataset.option;
                let count = 0;
                if (option === 'option_one') {
                    count = postArticle.dataset.optionOneVotes || 0;
                } else if (option === 'option_two') {
                    count = postArticle.dataset.optionTwoVotes || 0;
                }

                const votesText = parseInt(count) === 1 ? "vote" : "votes";
                tooltipElement.textContent = `${count} ${votesText}`;

                // Position it first (invisibly if needed), then make visible to avoid flicker
                positionTooltip(event.clientX, event.clientY);
                tooltipElement.classList.remove('hidden');
            }
        });

        postsContainer.addEventListener('mouseout', function (event) {
            const button = event.target.closest('.vote-button');
            // Hide if mousing out of the button itself or if relatedTarget is outside the button
            if (button && currentHoveredButton === button) {
                if (!button.contains(event.relatedTarget)) {
                    tooltipElement.classList.add('hidden');
                    currentHoveredButton = null;
                }
            } else if (currentHoveredButton && !postsContainer.contains(event.relatedTarget)) {
                // If mouse leaves the container entirely while a tooltip was active
                tooltipElement.classList.add('hidden');
                currentHoveredButton = null;
            }
        });

        postsContainer.addEventListener('mousemove', function (event) {
            // Only update position if a button is being hovered and tooltip is visible
            if (currentHoveredButton && !tooltipElement.classList.contains('hidden')) {
                // Check if the mouse is still over the current hovered button or its children
                const targetButton = event.target.closest('.vote-button');
                if (targetButton === currentHoveredButton) {
                    positionTooltip(event.clientX, event.clientY);
                } else {
                    // Mouse moved away from the button that triggered the tooltip
                    tooltipElement.classList.add('hidden');
                    currentHoveredButton = null;
                }
            }
        });

        function openModal(imageUrl) {
            modalImage.setAttribute('src', imageUrl);
            modal.classList.remove('hidden');
            requestAnimationFrame(() => {
                modal.classList.remove('opacity-0');
            });
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.add('opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modalImage.setAttribute('src', '');
            }, 300);
            document.body.style.overflow = '';
        }

        document.body.addEventListener('click', function (event) {
            let target = event.target;
            for (let i = 0; i < 3 && target && target !== document.body; i++, target = target.parentNode) {
                if (target.matches && target.matches('img.zoomable-image')) {
                    event.preventDefault();
                    const fullSrc = target.dataset.fullSrc || target.src;
                    if (fullSrc && fullSrc !== window.location.href + '#') {
                        openModal(fullSrc);
                    }
                    return;
                }
            }
        });

        closeModalButton.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    });
</script>
</body>
</html>
