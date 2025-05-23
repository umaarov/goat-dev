@extends('layouts.app')

@section('title', 'Create New Post')

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h5 class="text-lg font-semibold mb-1">Ask a unique question!</h5>
            <p class="text-gray-600 text-sm mb-4">Choose titles, images and categories to fit your subjects for the
                world to vote on.</p>

            <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data"
                  id="createPostForm">
                @csrf

                <div class="mb-4">
                    <input type="text"
                           class="w-full px-3 py-2 border @error('question') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           id="question" name="question" placeholder="Question..."
                           value="{{ old('question') }}">
                    @error('question')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    {{-- Option 1 --}}
                    <div>
                        <label for="option_one_image"
                               class="block mb-2">
                            <div id="option_one_preview"
                                 class="bg-gray-100 h-40 rounded-md flex items-center justify-center cursor-pointer border-2 border-dashed @error('option_one_image') border-red-500 @else border-gray-300 hover:border-blue-500 @enderror">
                                <div id="option_one_placeholder"
                                     class="text-center text-gray-500 p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-400"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <p class="mt-1 text-xs">Click to upload image</p>
                                </div>
                                <img id="option_one_img"
                                     class="h-full w-full object-cover object-center hidden rounded-md" src="#"
                                     alt="Option 1 Preview">
                            </div>
                            <input type="file" id="option_one_image" name="option_one_image" class="hidden"
                                   accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                   onchange="previewImage(this, 'option_one_img', 'option_one_placeholder', 'option_one_preview')">
                        </label>
                        @error('option_one_image')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror

                        <input type="text"
                               class="mt-2 w-full px-3 py-2 border @error('option_one_title') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               id="option_one_title" name="option_one_title" placeholder="Subject 1"
                               value="{{ old('option_one_title') }}">
                        @error('option_one_title')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Option 2 --}}
                    <div>
                        <label for="option_two_image" class="block mb-2">
                            <div id="option_two_preview"
                                 class="bg-gray-100 h-40 rounded-md flex items-center justify-center cursor-pointer border-2 border-dashed @error('option_two_image') border-red-500 @else border-gray-300 hover:border-blue-500 @enderror">
                                <div id="option_two_placeholder" class="text-center text-gray-500 p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-400"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <p class="mt-1 text-xs">Click to upload image</p>
                                </div>
                                <img id="option_two_img"
                                     class="h-full w-full object-cover object-center hidden rounded-md" src="#"
                                     alt="Option 2 Preview">
                            </div>
                            <input type="file" id="option_two_image" name="option_two_image" class="hidden"
                                   accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                   onchange="previewImage(this, 'option_two_img', 'option_two_placeholder', 'option_two_preview')">
                        </label>
                        @error('option_two_image')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror

                        <input type="text"
                               class="mt-2 w-full px-3 py-2 border @error('option_two_title') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               id="option_two_title" name="option_two_title" placeholder="Subject 2"
                               value="{{ old('option_two_title') }}">
                        @error('option_two_title')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit"
                            class="w-full bg-blue-800 text-white py-3 rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-150">
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input, imgId, placeholderId, previewDivId) {
            const imgElement = document.getElementById(imgId);
            const placeholderElement = document.getElementById(placeholderId);
            const previewDivElement = document.getElementById(previewDivId);

            if (previewDivElement) {
                previewDivElement.classList.remove('border-red-500');
                if (!input.files || !input.files[0]) {
                    previewDivElement.classList.add('border-gray-300', 'hover:border-blue-500');
                }
            }

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    imgElement.src = e.target.result;
                    imgElement.classList.remove('hidden');
                    placeholderElement.classList.add('hidden');
                    if (previewDivElement) {
                        previewDivElement.classList.remove('border-dashed', 'border-gray-300', 'hover:border-blue-500');
                        previewDivElement.classList.add('border-solid', 'border-gray-300');
                    }
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                imgElement.src = '#';
                imgElement.classList.add('hidden');
                placeholderElement.classList.remove('hidden');
                if (previewDivElement) {
                    previewDivElement.classList.remove('border-solid');
                    previewDivElement.classList.add('border-dashed', 'border-gray-300', 'hover:border-blue-500');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const createPostForm = document.getElementById('createPostForm');

            if (createPostForm) {
                createPostForm.addEventListener('submit', function (event) {
                    const questionInput = document.getElementById('question');
                    const optionOneTitleInput = document.getElementById('option_one_title');
                    const optionOneImageInput = document.getElementById('option_one_image');
                    const optionTwoTitleInput = document.getElementById('option_two_title');
                    const optionTwoImageInput = document.getElementById('option_two_image');

                    let allFieldsValid = true;

                    if (questionInput.value.trim() === '') {
                        allFieldsValid = false;
                    }
                    if (optionOneTitleInput.value.trim() === '') {
                        allFieldsValid = false;
                    }
                    if (optionOneImageInput.files.length === 0) {
                        allFieldsValid = false;
                    }
                    if (optionTwoTitleInput.value.trim() === '') {
                        allFieldsValid = false;
                    }
                    if (optionTwoImageInput.files.length === 0) {
                        allFieldsValid = false;
                    }

                    if (!allFieldsValid) {
                        event.preventDefault();
                        if (typeof window.showToast === 'function') {
                            window.showToast('Please fill all required fields.', 'warning');
                        } else {
                            alert('Please fill all required fields.');
                        }
                    }
                });
            }
        });
    </script>
@endsection
