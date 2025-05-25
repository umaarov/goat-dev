@extends('layouts.app')

@section('title', __('messages.edit_post.title'))

@section('content')
    <div
        class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4 p-6">
        <h2 class="text-xl font-semibold mb-4">{{ __('messages.edit_post.heading') }}</h2>

        @if($post->total_votes > 0)
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
                <p>{{ __('messages.edit_post.cannot_edit_voted_post_message') }}</p>
            </div>
        @else
            <form method="POST" action="{{ route('posts.update', $post) }}" enctype="multipart/form-data"
                  id="editPostForm">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="question"
                           class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.edit_post.your_question_label') }}</label>
                    <input type="text"
                           class="w-full px-3 py-2 border @error('question') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           id="question" name="question"
                           placeholder="{{ __('messages.create_post.question_placeholder') }}"
                           {{-- Reusing create placeholder --}}
                           value="{{ old('question', isset($post) ? $post->question : '') }}"
                           maxlength="255">
                    @error('question')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <fieldset class="border border-gray-300 p-4 rounded-md">
                        <legend
                            class="text-sm font-medium text-gray-700 px-1">{{ __('messages.edit_post.option_1_legend') }}</legend>
                        <div class="mb-3">
                            <label for="option_one_title"
                                   class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.edit_post.title_label') }}</label>
                            <input type="text"
                                   class="mt-2 w-full px-3 py-2 border @error('option_one_title') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   id="option_one_title" name="option_one_title"
                                   placeholder="{{ __('messages.create_post.subject_1_placeholder') }}"
                                   {{-- Reusing create placeholder --}}
                                   value="{{ old('option_one_title', isset($post) ? $post->option_one_title : '') }}"
                                   maxlength="40">
                            @error('option_one_title')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.edit_post.image_label') }}</label>
                            @if($post->option_one_image)
                                <div class="mb-2">
                                    <p class="text-xs text-gray-500 mb-1">{{ __('messages.edit_post.current_image_label') }}</p>
                                    <img src="{{ asset('storage/' . $post->option_one_image) }}"
                                         alt="{{ __('messages.edit_post.current_option_1_image_alt') }}"
                                         class="max-h-24 rounded border border-gray-200 zoomable-image">
                                    <label class="mt-1 flex items-center text-xs text-gray-600">
                                        <input type="checkbox" id="remove_option_one_image_checkbox"
                                               name="remove_option_one_image" value="1"
                                               class="mr-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        {{ __('messages.edit_post.remove_current_image_label') }}
                                    </label>
                                </div>
                            @endif

                            {{-- Area to trigger cropper and show new image preview --}}
                            <label for="option_one_image_trigger_edit"
                                   class="inline-block mb-1 text-sm font-medium text-blue-600 hover:text-blue-800 cursor-pointer underline">
                                {{ $post->option_one_image ? __('messages.edit_post.replace_image_button') : __('messages.edit_post.upload_image_button') }}
                            </label>
                            <div id="option_one_new_image_preview_container_edit"
                                 class="w-32 h-32 bg-gray-100 rounded-md flex items-center justify-center border-2 border-dashed border-gray-300 hover:border-blue-500 mb-2 mt-1 {{ $errors->has('option_one_image') ? '' : 'hidden' }}">
                                <div id="option_one_new_image_placeholder_edit" class="text-center text-gray-500 p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <p class="mt-1 text-xs">{{ __('messages.edit_post.new_image_placeholder') }}</p>
                                </div>
                                <img id="option_one_new_image_preview_edit"
                                     class="h-full w-full object-cover object-center hidden rounded-md" src="#"
                                     alt="{{ __('messages.edit_post.new_option_1_preview_alt') }}">
                            </div>

                            <input type="file" id="option_one_image_trigger_edit" class="hidden"
                                   accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                   onchange="document.getElementById('option_one_new_image_preview_container_edit').classList.remove('hidden'); const cb = document.getElementById('remove_option_one_image_checkbox'); if(cb) cb.checked = false; openImageCropper(event, 'option_one_image_final_edit', 'option_one_new_image_preview_edit', 'option_one_new_image_placeholder_edit', 'option_one_new_image_preview_container_edit')">
                            <input type="file" name="option_one_image" id="option_one_image_final_edit" class="hidden">

                            @error('option_one_image')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-300 p-4 rounded-md">
                        <legend
                            class="text-sm font-medium text-gray-700 px-1">{{ __('messages.edit_post.option_2_legend') }}</legend>
                        <div class="mb-3">
                            <label for="option_two_title"
                                   class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.edit_post.title_label') }}</label>
                            <input type="text"
                                   class="mt-2 w-full px-3 py-2 border @error('option_two_title') border-red-500 @else border-gray-300 @enderror rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   id="option_two_title" name="option_two_title"
                                   placeholder="{{ __('messages.create_post.subject_2_placeholder') }}"
                                   {{-- Reusing create placeholder --}}
                                   value="{{ old('option_two_title', isset($post) ? $post->option_two_title : '') }}"
                                   maxlength="40">
                            @error('option_two_title')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.edit_post.image_label') }}</label>
                            @if($post->option_two_image)
                                <div class="mb-2">
                                    <p class="text-xs text-gray-500 mb-1">{{ __('messages.edit_post.current_image_label') }}</p>
                                    <img src="{{ asset('storage/' . $post->option_two_image) }}"
                                         alt="{{ __('messages.edit_post.current_option_2_image_alt') }}"
                                         class="max-h-24 rounded border border-gray-200 zoomable-image">
                                    <label class="mt-1 flex items-center text-xs text-gray-600">
                                        <input type="checkbox" id="remove_option_two_image_checkbox"
                                               name="remove_option_two_image" value="1"
                                               class="mr-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        {{ __('messages.edit_post.remove_current_image_label') }}
                                    </label>
                                </div>
                            @endif

                            <label for="option_two_image_trigger_edit"
                                   class="inline-block mb-1 text-sm font-medium text-blue-600 hover:text-blue-800 cursor-pointer underline">
                                {{ $post->option_two_image ? __('messages.edit_post.replace_image_button') : __('messages.edit_post.upload_image_button') }}
                            </label>
                            <div id="option_two_new_image_preview_container_edit"
                                 class="w-32 h-32 bg-gray-100 rounded-md flex items-center justify-center border-2 border-dashed border-gray-300 hover:border-blue-500 mb-2 mt-1 {{ $errors->has('option_two_image') ? '' : 'hidden' }}">
                                <div id="option_two_new_image_placeholder_edit" class="text-center text-gray-500 p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <p class="mt-1 text-xs">{{ __('messages.edit_post.new_image_placeholder') }}</p>
                                </div>
                                <img id="option_two_new_image_preview_edit"
                                     class="h-full w-full object-cover object-center hidden rounded-md" src="#"
                                     alt="{{ __('messages.edit_post.new_option_2_preview_alt') }}">
                            </div>

                            <input type="file" id="option_two_image_trigger_edit" class="hidden"
                                   accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                   onchange="document.getElementById('option_two_new_image_preview_container_edit').classList.remove('hidden'); const cb = document.getElementById('remove_option_two_image_checkbox'); if(cb) cb.checked = false; openImageCropper(event, 'option_two_image_final_edit', 'option_two_new_image_preview_edit', 'option_two_new_image_placeholder_edit', 'option_two_new_image_preview_container_edit')">
                            <input type="file" name="option_two_image" id="option_two_image_final_edit" class="hidden">

                            @error('option_two_image')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                    </fieldset>
                </div>

                <div class="mt-6 flex items-center space-x-4">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-150">
                        {{ __('messages.edit_post.update_post_button') }}
                    </button>
                    <a href="{{ route('profile.show', ['username' => Auth::user()->username]) }}"
                       class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors duration-150">
                        {{ __('messages.cancel_button') }}
                    </a>
                </div>
            </form>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editPostForm = document.getElementById('editPostForm');

            if (editPostForm) {
                editPostForm.addEventListener('submit', function (event) {
                    const questionInput = document.getElementById('question');
                    const optionOneTitleInput = document.getElementById('option_one_title');
                    const optionTwoTitleInput = document.getElementById('option_two_title');

                    let allFieldsValid = true;

                    questionInput.classList.remove('border-red-500');
                    optionOneTitleInput.classList.remove('border-red-500');
                    optionTwoTitleInput.classList.remove('border-red-500');
                    if (!questionInput.classList.contains('border-gray-300')) questionInput.classList.add('border-gray-300');
                    if (!optionOneTitleInput.classList.contains('border-gray-300')) optionOneTitleInput.classList.add('border-gray-300');
                    if (!optionTwoTitleInput.classList.contains('border-gray-300')) optionTwoTitleInput.classList.add('border-gray-300');


                    if (questionInput.value.trim() === '') {
                        allFieldsValid = false;
                        questionInput.classList.add('border-red-500');
                        questionInput.classList.remove('border-gray-300');
                    }
                    if (optionOneTitleInput.value.trim() === '') {
                        allFieldsValid = false;
                        optionOneTitleInput.classList.add('border-red-500');
                        optionOneTitleInput.classList.remove('border-gray-300');
                    }
                    if (optionTwoTitleInput.value.trim() === '') {
                        allFieldsValid = false;
                        optionTwoTitleInput.classList.add('border-red-500');
                        optionTwoTitleInput.classList.remove('border-gray-300');
                    }

                    if (!allFieldsValid) {
                        event.preventDefault();

                        if (typeof window.showToast === 'function') {
                            window.showToast("{{ __('messages.edit_post.js.fill_required_fields_warning') }}", 'warning');
                        } else {
                            alert("{{ __('messages.edit_post.js.fill_required_fields_warning') }}");
                        }
                    }
                });
            }

            @error('option_one_image')
            document.getElementById('option_one_new_image_preview_container_edit')?.classList.remove('hidden');
            @enderror
            @error('option_two_image')
            document.getElementById('option_two_new_image_preview_container_edit')?.classList.remove('hidden');
            @enderror
        });
    </script>
@endsection
