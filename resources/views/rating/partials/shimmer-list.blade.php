<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    {{-- Shimmer Header --}}
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="h-6 w-3/5 rounded shimmer-bg"></div>
        <div class="h-4 w-4/5 mt-2 rounded shimmer-bg"></div>
    </div>

    {{-- Shimmer List --}}
    <ul>
        @foreach(range(1, 10) as $item)
            <li class="p-4 flex items-center space-x-4">
                {{-- Rank --}}
                <div class="flex items-center justify-center w-8 text-center">
                    <div class="h-5 w-5 rounded shimmer-bg"></div>
                </div>

                {{-- Avatar --}}
                <div class="flex-shrink-0">
                    <div class="h-11 w-11 rounded-full shimmer-bg"></div>
                </div>

                {{-- User Info --}}
                <div class="flex-1 min-w-0">
                    <div class="h-4 w-1/2 rounded shimmer-bg"></div>
                    <div class="h-3 w-1/3 mt-2 rounded shimmer-bg"></div>
                </div>

                {{-- Score --}}
                <div class="text-right flex-shrink-0 w-16">
                    <div class="h-5 w-full rounded shimmer-bg"></div>
                    <div class="h-3 w-3/4 mt-2 ml-auto rounded shimmer-bg"></div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
