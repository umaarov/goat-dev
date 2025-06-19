@extends('layouts.app')

@section('title', __('messages.create_post.title'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h5 class="text-lg font-semibold mb-1">{{ __('messages.create_post.ask_unique_question') }}</h5>
            <p class="text-gray-600 text-sm mb-4">{{ __('messages.create_post.choose_titles_images_categories') }}</p>

            <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data"
                  id="createPostForm">
                @csrf

                {{-- Question Input --}}
                <div class="mb-4">
                    <input type="text"
                           class="w-full px-3 py-2 border @error('question') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           id="question" name="question"
                           placeholder="{{ __('messages.create_post.question_placeholder') }}"
                           value="{{ old('question', isset($post) ? $post->question : '') }}"
                           maxlength="255">
                    @error('question')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    {{-- Option 1 --}}
                    <div>
                        <label for="option_one_image_trigger" class="block mb-2 cursor-pointer">
                            <div id="option_one_preview"
                                 class="bg-gray-100 h-40 rounded-md flex items-center justify-center border-2 border-dashed @error('option_one_image') border-red-500 @else border-gray-300 @enderror hover:border-blue-500">
                                <div id="option_one_placeholder" class="text-center text-gray-500 p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-400"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <p class="mt-1 text-xs">{{ __('messages.create_post.click_to_upload_image') }}</p>
                                </div>
                                <img id="option_one_img" class="h-full w-full object-cover object-center hidden rounded-md" src="#" alt="{{ __('messages.create_post.option_1_preview_alt') }}">
                            </div>
                        </label>
                        <input type="file" id="option_one_image_trigger" class="hidden"
                               accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                               onchange="openImageCropper(event, 'option_one_image_final', 'option_one_img', 'option_one_placeholder', 'option_one_preview')">
                        <input type="file" name="option_one_image" id="option_one_image_final" class="hidden">

                        @error('option_one_image')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror

                        <input type="text"
                               class="mt-2 w-full px-3 py-2 border @error('option_one_title') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               id="option_one_title" name="option_one_title"
                               placeholder="{{ __('messages.create_post.subject_1_placeholder') }}"
                               value="{{ old('option_one_title', isset($post) ? $post->option_one_title : '') }}"
                               maxlength="40">
                        @error('option_one_title')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Option 2 --}}
                    <div>
                        <label for="option_two_image_trigger" class="block mb-2 cursor-pointer">
                            <div id="option_two_preview"
                                 class="bg-gray-100 h-40 rounded-md flex items-center justify-center border-2 border-dashed @error('option_two_image') border-red-500 @else border-gray-300 @enderror hover:border-blue-500">
                                <div id="option_two_placeholder" class="text-center text-gray-500 p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-400"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <p class="mt-1 text-xs">{{ __('messages.create_post.click_to_upload_image') }}</p>
                                </div>
                                <img id="option_two_img" class="h-full w-full object-cover object-center hidden rounded-md" src="#" alt="{{ __('messages.create_post.option_2_preview_alt') }}">
                            </div>
                        </label>
                        <input type="file" id="option_two_image_trigger" class="hidden"
                               accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                               onchange="openImageCropper(event, 'option_two_image_final', 'option_two_img', 'option_two_placeholder', 'option_two_preview')">
                        <input type="file" name="option_two_image" id="option_two_image_final" class="hidden">

                        @error('option_two_image')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror

                        <input type="text"
                               class="mt-2 w-full px-3 py-2 border @error('option_two_title') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               id="option_two_title" name="option_two_title"
                               placeholder="{{ __('messages.create_post.subject_2_placeholder') }}"
                               value="{{ old('option_two_title', isset($post) ? $post->option_two_title : '') }}"
                               maxlength="40">
                        @error('option_two_title')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="mt-6">
                    <button type="submit"
                            class="w-full bg-blue-800 text-white py-3 rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-150">
                        {{ __('messages.create_post.submit_button') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openImageCropper(event, finalInputId, previewImgId, placeholderId, previewContainerId) {
            const triggerInput = event.target;
            const previewImg = document.getElementById(previewImgId);
            const placeholder = document.getElementById(placeholderId);
            const finalInput = document.getElementById(finalInputId);
            const previewContainer = document.getElementById(previewContainerId);

            if (triggerInput.files && triggerInput.files[0]) {
                const file = triggerInput.files[0];

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewImg.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                };
                reader.readAsDataURL(file);

                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                finalInput.files = dataTransfer.files;

                previewContainer?.classList.remove('border-red-500');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const createPostForm = document.getElementById('createPostForm');

            if (createPostForm) {
                createPostForm.addEventListener('submit', function (event) {
                    const questionInput = document.getElementById('question');
                    const optionOneTitleInput = document.getElementById('option_one_title');
                    const optionOneImageInput = document.getElementById('option_one_image_final');
                    const optionTwoTitleInput = document.getElementById('option_two_title');
                    const optionTwoImageInput = document.getElementById('option_two_image_final');

                    let allFieldsValid = true;

                    if (questionInput.value.trim() === '') allFieldsValid = false;
                    if (optionOneTitleInput.value.trim() === '') allFieldsValid = false;
                    if (optionTwoTitleInput.value.trim() === '') allFieldsValid = false;

                    if (!optionOneImageInput || optionOneImageInput.files.length === 0) {
                        allFieldsValid = false;
                        document.getElementById('option_one_preview')?.classList.add('border-red-500');
                    } else {
                        document.getElementById('option_one_preview')?.classList.remove('border-red-500');
                    }

                    if (!optionTwoImageInput || optionTwoImageInput.files.length === 0) {
                        allFieldsValid = false;
                        document.getElementById('option_two_preview')?.classList.add('border-red-500');
                    } else {
                        document.getElementById('option_two_preview')?.classList.remove('border-red-500');
                    }

                    if (!allFieldsValid) {
                        event.preventDefault();
                        if (typeof window.showToast === 'function') {
                            window.showToast("{{ __('messages.create_post.js.fill_all_fields_warning') }}", 'warning');
                        } else {
                            alert("{{ __('messages.create_post.js.fill_all_fields_warning') }}");
                        }
                    }
                });
            }
        });
    </script>
@endsection
