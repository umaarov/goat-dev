<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Vote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class PostController extends Controller
{

    private const MAX_POST_IMAGE_WIDTH = 1024;
    private const MAX_POST_IMAGE_HEIGHT = 1024;
    private const POST_IMAGE_QUALITY = 75;

    private function processAndStoreImage(UploadedFile $uploadedFile, string $directory, string $baseFilename): string
    {
        $manager = ImageManager::gd();
        $image = $manager->read($uploadedFile->getRealPath());

        // $image = Image::make($uploadedFile->getRealPath());

        $image->scaleDown(self::MAX_POST_IMAGE_WIDTH, self::MAX_POST_IMAGE_HEIGHT);

        $originalExtension = $uploadedFile->getClientOriginalExtension();
        $filename = $baseFilename . '.' . $originalExtension;
        $path = $directory . '/' . $filename;

        $extension = strtolower($originalExtension);

        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                $encodedImage = $image->toJpeg(self::POST_IMAGE_QUALITY)->toString();
                break;
            case 'png':
                $encodedImage = $image->toPng()->toString();
                break;
            case 'gif':
                $encodedImage = $image->toGif()->toString();
                break;
            case 'webp':
                $encodedImage = $image->toWebp(self::POST_IMAGE_QUALITY)->toString();
                break;
            default:
                $newExtension = 'jpg';
                $filename = $baseFilename . '.' . $newExtension;
                $path = $directory . '/' . $filename;
                $encodedImage = $image->toJpeg(self::POST_IMAGE_QUALITY)->toString();
        }

        Storage::disk('public')->put($path, $encodedImage);
        return $path;
    }

    final public function index(Request $request): View
    {
        $query = Post::query()->withPostData();

        switch ($request->input('filter')) {
            case 'trending':
                $query->where('created_at', '>=', now()->subDays(7))
                    ->orderByDesc('total_votes')
                    ->orderByDesc('created_at');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }

        $posts = $query->paginate(15)->withQueryString();
        $this->attachUserVoteStatus($posts);
        return view('home', compact('posts'));
    }

    final public function create(): View
    {
        return view('posts.create');
    }

    final public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:40',
            'option_one_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'option_two_title' => 'required|string|max:40',
            'option_two_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $optionOneImagePath = null;
        if ($request->hasFile('option_one_image')) {
            $optionOneImagePath = $this->processAndStoreImage(
                $request->file('option_one_image'),
                'post_images',
                uniqid('post_opt1_')
            );
        }

        $optionTwoImagePath = null;
        if ($request->hasFile('option_two_image')) {
            $optionTwoImagePath = $this->processAndStoreImage(
                $request->file('option_two_image'),
                'post_images',
                uniqid('post_opt2_')
            );
        }

        $user = Auth::user();

        $post = Post::create([
            'user_id' => Auth::id(),
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_one_image' => $optionOneImagePath,
            'option_two_title' => $request->option_two_title,
            'option_two_image' => $optionTwoImagePath,
        ]);

        Log::channel('audit_trail')->info('Post created.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'post_id' => $post->id,
            'question' => Str::limit($post->question, 100),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('home')->with('success', 'Post created successfully!');
    }

    final public function edit(Post $post): View|RedirectResponse
    {
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }
        if ($post->total_votes > 0) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('error', 'Cannot edit a post that has already received votes.');
        }
        return view('posts.edit', compact('post'));
    }

    final public function update(Request $request, Post $post): RedirectResponse
    {
        $user = Auth::user();
        if ($user->id !== $post->user_id) {
            Log::channel('audit_trail')->warning('Unauthorized post update attempt.', [
                'attempting_user_id' => $user->id,
                'attempting_username' => $user->username,
                'post_id' => $post->id,
                'post_owner_id' => $post->user_id,
                'ip_address' => $request->ip(),
            ]);
            abort(403, 'Unauthorized action.');
        }
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }
        if ($post->total_votes > 0) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('error', 'Cannot update a post that has already received votes.');
        }

        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:40',
            'option_one_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'option_two_title' => 'required|string|max:40',
            'option_two_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_option_one_image' => 'nullable|boolean',
            'remove_option_two_image' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->only(['question', 'option_one_title', 'option_two_title']);

        if ($request->boolean('remove_option_one_image') && $post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
            $data['option_one_image'] = null;
        } elseif ($request->hasFile('option_one_image')) {
            if ($post->option_one_image) {
                Storage::disk('public')->delete($post->option_one_image);
            }
            $data['option_one_image'] = $this->processAndStoreImage(
                $request->file('option_one_image'),
                'post_images',
                uniqid('post_opt1_')
            );
        }

        if ($request->boolean('remove_option_two_image') && $post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
            $data['option_two_image'] = null;
        } elseif ($request->hasFile('option_two_image')) {
            if ($post->option_two_image) {
                Storage::disk('public')->delete($post->option_two_image);
            }
            $data['option_two_image'] = $this->processAndStoreImage(
                $request->file('option_two_image'),
                'post_images',
                uniqid('post_opt2_')
            );
        }

        $post->update($data);
        Log::channel('audit_trail')->info('Post updated.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'post_id' => $post->id,
            'updated_fields' => array_keys($data),
            'ip_address' => $request->ip(),
        ]);
        return redirect()->route('profile.show', ['username' => $post->user->username])
            ->with('success', 'Post updated successfully.');
    }

    final public function destroy(Post $post): RedirectResponse
    {
        $user = Auth::user();
        if ($user->id !== $post->user_id) {
            Log::channel('audit_trail')->warning('Unauthorized post deletion attempt.', [
                'attempting_user_id' => $user->id,
                'attempting_username' => $user->username,
                'post_id' => $post->id,
                'post_owner_id' => $post->user_id,
                'ip_address' => request()->ip(),
            ]);
            abort(403, 'Unauthorized action.');
        }
        if (Auth::id() !== $post->user_id /* && !Auth::user()->isAdmin() */) {
            abort(403, 'Unauthorized action.');
        }

        $postId = $post->id;
        $postQuestion = $post->question;

        if ($post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
        }
        if ($post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
        }

        $post->delete();

        $post->delete();

        Log::channel('audit_trail')->info('Post deleted.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'deleted_post_id' => $postId,
            'original_post_owner_id' => $post->user_id,
            'original_post_question' => Str::limit($postQuestion, 100),
            'ip_address' => request()->ip(),
        ]);

        if (url()->previous() == route('profile.show', ['username' => Auth::user()->username])) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('success', 'Post deleted successfully.');
        }

        return redirect()->route('home')->with('success', 'Post deleted successfully.');
    }

    final public function vote(Request $request, Post $post): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'option' => 'required|in:option_one,option_two',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $loggedInUserId = Auth::id();

        $existingVote = Vote::where('user_id', $loggedInUserId)
            ->where('post_id', $post->id)
            ->first();

        if ($existingVote) {
            return response()->json(['error' => 'You have already voted on this post.'], 409);
        }

        Vote::create([
            'user_id' => $loggedInUserId,
            'post_id' => $post->id,
            'vote_option' => $request->option,
        ]);

        Log::channel('audit_trail')->info('User voted on post.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'post_id' => $post->id,
            'voted_option' => $request->option,
            'ip_address' => $request->ip(),
        ]);

        if ($request->option === 'option_one') {
            $post->increment('option_one_votes');
        } else {
            $post->increment('option_two_votes');
        }
        $post->increment('total_votes');

        $post->refresh();
        return response()->json([
            'message' => 'Vote registered successfully!',
            'option_one_votes' => $post->option_one_votes,
            'option_two_votes' => $post->option_two_votes,
            'total_votes' => $post->total_votes,
            'user_vote' => $request->option,
        ]);
    }

    public function showBySlug($id, $slug = null)
    {
        $post = Post::findOrFail($id);

        $perPage = 10;
        $allPosts = Post::orderBy('created_at', 'desc')->get();

        $postIndex = $allPosts->search(function ($item) use ($id) {
            return $item->id == $id;
        });

        $page = floor($postIndex / $perPage) + 1;

        return redirect()->route('home', ['page' => $page])
            ->with('scrollToPost', $id);
    }

    public function incrementShareCount(Request $request, Post $post)
    {
        $post->increment('shares_count');
        $user = Auth::user();
        Log::channel('audit_trail')->info('Post share count incremented (likely client-side share action).', [
            'user_id' => $user ? $user->id : null,
            'username' => $user ? $user->username : 'Guest/Unconfirmed',
            'post_id' => $post->id,
            'ip_address' => $request->ip(),
        ]);
        return response()->json(['shares_count' => $post->shares()->count()]);
    }

    final public function search(Request $request): View|JsonResponse
    {
        $queryTerm = $request->input('q');

        if (!$queryTerm) {
            return view('search.results', ['posts' => collect(), 'queryTerm' => null]);
        }

        $query = Post::query()->withPostData();

        $query->where(function (Builder $subQuery) use ($queryTerm) {
            $subQuery->where('question', 'LIKE', "%{$queryTerm}%")
                ->orWhere('option_one_title', 'LIKE', "%{$queryTerm}%")
                ->orWhere('option_two_title', 'LIKE', "%{$queryTerm}%")
                ->orWhereHas('user', function (Builder $userQuery) use ($queryTerm) {
                    $userQuery->where('username', 'LIKE', "%{$queryTerm}%");
                });
        });

        $posts = $query->latest()->paginate(15)->withQueryString();

        $this->attachUserVoteStatus($posts);

        if ($request->expectsJson()) {
            return response()->json($posts);
        }

        return view('search.results', compact('posts', 'queryTerm'));
    }

    private function attachUserVoteStatus(LengthAwarePaginator $posts): void
    {
        $userVoteMap = collect();
        if (Auth::check()) {
            $loggedInUserId = Auth::id();
            $postIds = $posts->pluck('id')->all();

            if (!empty($postIds)) {
                $userVoteMap = Vote::where('user_id', $loggedInUserId)
                    ->whereIn('post_id', $postIds)
                    ->pluck('vote_option', 'post_id');
            }
        }

        $posts->getCollection()->transform(function ($post) use ($userVoteMap) {
            $post->user_vote = $userVoteMap->get($post->id);
            return $post;
        });
    }
}
