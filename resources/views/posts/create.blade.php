@extends('layouts.app')

@section('title', 'Create New Post')

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden">
        <div class="p-6">
            <h5 class="text-lg font-semibold mb-1">Ask a unique question!</h5>
            <p class="text-gray-600 text-sm mb-4">Choose titles, images and categories to fit your subjects for the world to vote on.</p>

            <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           id="question" name="question" placeholder="Question..." value="{{ old('question') }}" required>
                    @error('question')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="option_one_image" class="block mb-2">
                            <div id="option_one_preview" class="bg-gray-100 h-40 rounded-md flex items-center justify-center cursor-pointer">
                                <div id="option_one_placeholder" class="bg-gray-200 rounded-full p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                                <img id="option_one_img" class="h-full w-full object-cover object-center hidden" src="#" alt="Option 1 Preview">
                            </div>
                            <input type="file" id="option_one_image" name="option_one_image" class="hidden" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" onchange="previewImage(this, 'option_one_img', 'option_one_placeholder')">
                        </label>
                        @error('option_one_image')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               id="option_one_title" name="option_one_title" placeholder="Subject 1" value="{{ old('option_one_title') }}" required>
                        @error('option_one_title')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <label for="option_two_image" class="block mb-2">
                            <div id="option_two_preview" class="bg-gray-100 h-40 rounded-md flex items-center justify-center cursor-pointer">
                                <div id="option_two_placeholder" class="bg-gray-200 rounded-full p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                                <img id="option_two_img" class="h-full w-full object-cover object-center hidden" src="#" alt="Option 2 Preview">
                            </div>
                            <input type="file" id="option_two_image" name="option_two_image" class="hidden" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" onchange="previewImage(this, 'option_two_img', 'option_two_placeholder')">
                        </label>
                        @error('option_two_image')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               id="option_two_title" name="option_two_title" placeholder="Subject 2" value="{{ old('option_two_title') }}" required>
                        @error('option_two_title')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="w-full bg-blue-800 text-white py-3 rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input, imgId, placeholderId) {
            const img = document.getElementById(imgId);
            const placeholder = document.getElementById(placeholderId);

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }

                reader.readAsDataURL(input.files[0]);
            } else {
                img.classList.add('hidden');
                placeholder.classList.remove('hidden');
            }
        }
    </script>
@endsection
