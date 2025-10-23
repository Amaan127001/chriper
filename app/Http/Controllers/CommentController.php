<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function toggleLike(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if user already liked this comment
        $existingLike = $comment->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            // Unlike
            $existingLike->delete();
            $userLiked = false;
        } else {
            // Like
            $comment->likes()->create([
                'user_id' => $user->id,
            ]);
            $userLiked = true;
        }

        $likesCount = $comment->likes()->count();

        return response()->json([
            'likes_count' => $likesCount,
            'user_liked' => $userLiked,
        ]);
    }
}