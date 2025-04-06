<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShareController extends Controller
{
    final function store(Request $request, Post $post): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:twitter,facebook,whatsapp,telegram,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $share = Share::create([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
            'platform' => $request->platform,
        ]);

        return response()->json([
            'message' => 'Post shared successfully',
            'share' => $share,
        ], 201);
    }
}
