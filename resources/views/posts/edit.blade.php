@extends('layouts.app')

@section('title', 'Edit Post')

@section('content')
    <h2>Edit Post</h2>

    @if($post->total_votes > 0)
        <div class="alert alert-info">This post cannot be edited because it has already received votes.</div>
    @else
        <form method="POST" action="{{ route('posts.update', $post) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="question">Your Question</label>
                <textarea id="question" name="question" rows="3"
                          required>{{ old('question', $post->question) }}</textarea>
                @error('question')
                <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <fieldset>
                    <legend>Option 1</legend>
                    <div class="form-group">
                        <label for="option_one_title">Title</label>
                        <input id="option_one_title" type="text" name="option_one_title"
                               value="{{ old('option_one_title', $post->option_one_title) }}" required>
                        @error('option_one_title')
                        <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="option_one_image">Replace Image (Optional)</label>
                        @if($post->option_one_image)
                            <p>Current: <img src="{{ asset('storage/' . $post->option_one_image) }}"
                                             alt="Current Option 1 Image"
                                             ></p>
                            <label>
                                <input type="checkbox" name="remove_option_one_image" value="1"> Remove current image
                            </label>
                        @endif
                        <input id="option_one_image" type="file" name="option_one_image"
                               accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                        @error('option_one_image')
                        <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Option 2</legend>
                    <div class="form-group">
                        <label for="option_two_title">Title</label>
                        <input id="option_two_title" type="text" name="option_two_title"
                               value="{{ old('option_two_title', $post->option_two_title) }}" required>
                        @error('option_two_title')
                        <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="option_two_image">Replace Image (Optional)</label>
                        @if($post->option_two_image)
                            <p>Current: <img src="{{ asset('storage/' . $post->option_two_image) }}"
                                             alt="Current Option 2 Image"></p>
                            <label>
                                <input type="checkbox" name="remove_option_two_image" value="1"> Remove current image
                            </label>
                        @endif
                        <input id="option_two_image" type="file" name="option_two_image"
                               accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                        @error('option_two_image')
                        <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>
            </div>

            <div class="form-group">
                <button type="submit">Update Post</button>
                <a href="{{ route('profile.show', ['username' => Auth::user()->username]) }}"
                   class="button-link">Cancel</a>
            </div>
        </form>
    @endif
@endsection
