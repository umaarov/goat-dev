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
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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

        if (!modal || !modalImage || !closeModalButton) {
            console.warn('Image viewer modal elements not found. Zoom functionality will not work.');
            return;
        }

        function openModal(imageUrl) {
            modalImage.setAttribute('src', imageUrl);
            modal.classList.remove('hidden');
            requestAnimationFrame(() => { // Ensure 'hidden' is removed before starting opacity transition
                modal.classList.remove('opacity-0');
            });
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeModal() {
            modal.classList.add('opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modalImage.setAttribute('src', ''); // Clear image src to free memory
            }, 300); // Match duration of opacity transition
            document.body.style.overflow = ''; // Restore background scrolling
        }

        document.body.addEventListener('click', function (event) {
            let target = event.target;
            // Traverse up to 3 levels to find a zoomable image (if image is wrapped in divs)
            for (let i = 0; i < 3 && target && target !== document.body; i++, target = target.parentNode) {
                if (target.matches && target.matches('img.zoomable-image')) {
                    event.preventDefault(); // Important if the image is wrapped in an <a> tag
                    const fullSrc = target.dataset.fullSrc || target.src;
                    if (fullSrc && fullSrc !== window.location.href + '#') { // Avoid empty or '#' links
                        openModal(fullSrc);
                    }
                    return;
                }
            }
        });

        closeModalButton.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) { // Click on the overlay itself
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
