@extends('layouts.app')

@section('title', 'Create New Post')

@section('content')
    <h2>Create New Post</h2>

    <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label for="question">Your Question</label>
            <textarea id="question" name="question" rows="3" required>{{ old('question') }}</textarea>
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
                           value="{{ old('option_one_title') }}" required>
                    @error('option_one_title')
                    <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="option_one_image">Image (Optional)</label>
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
                           value="{{ old('option_two_title') }}" required>
                    @error('option_two_title')
                    <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="option_two_image">Image (Optional)</label>
                    <input id="option_two_image" type="file" name="option_two_image"
                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                    @error('option_two_image')
                    <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </fieldset>
        </div>

        <div class="form-group">
            <button type="submit">Create Post</button>
        </div>
    </form>
@endsection
