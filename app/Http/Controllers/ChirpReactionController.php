<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chirp;
use App\Models\Comment;
use App\Models\UserReaction; // your combined reactions model
use Illuminate\Http\JsonResponse;

class ChirpReactionController extends Controller
{
    // POST /chirps/{chirp}/react
    public function react(Request $request, Chirp $chirp): JsonResponse
    {
        $request->validate([
            'reaction_type' => 'required|in:like,dislike',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $type = $request->input('reaction_type');

        // existing reaction by this user (if any)
        $existing = $chirp->reactions()->where('user_id', $user->id)->first();

        if ($existing && $existing->reaction_type === $type) {
            // toggle off (remove)
            $existing->delete();
            $userReaction = null;
            $status = 'removed';
        } else {
            // create or update to the requested type
            $chirp->reactions()->updateOrCreate(
                ['user_id' => $user->id],
                ['reaction_type' => $type]
            );

            $userReaction = $type;
            $status = 'added';
        }

        $likesCount = $chirp->reactions()->where('reaction_type', 'like')->count();
        $dislikesCount = $chirp->reactions()->where('reaction_type', 'dislike')->count();

        return response()->json([
            'status' => $status,
            'user_reaction' => $userReaction, // 'like' | 'dislike' | null
            'likes_count' => $likesCount,
            'dislikes_count' => $dislikesCount,
        ]);
    }

    // POST /chirps/{chirp}/comment
    public function addComment(Request $request, Chirp $chirp): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'body' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        // optional: ensure parent belongs to same chirp
        if (!empty($data['parent_id'])) {
            $parent = Comment::find($data['parent_id']);
            if (!$parent || $parent->chirp_id !== $chirp->id) {
                return response()->json(['message' => 'Invalid parent comment'], 422);
            }
        }

        $comment = Comment::create([
            'user_id' => $user->id,
            'chirp_id' => $chirp->id,
            'body' => $data['body'],
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        // eager load user for response
        $comment->load('user');

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'parent_id' => $comment->parent_id,
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                ],
                'created_at' => $comment->created_at->toDateTimeString(),
                'created_human' => $comment->created_at->diffForHumans(),
            ]
        ], 201);
    }

    // GET /chirps/{chirp}/comments
    public function getComments(Chirp $chirp): JsonResponse
    {
        $comments = $chirp->comments()
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->latest()
            ->get();

        return response()->json([
            'comments' => $comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                    ],
                    'created_at' => $comment->created_at->toDateTimeString(),
                    'created_human' => $comment->created_at->diffForHumans(),
                    'replies' => $comment->replies->map(function ($reply) {
                        return [
                            'id' => $reply->id,
                            'body' => $reply->body,
                            'user' => [
                                'id' => $reply->user->id,
                                'name' => $reply->user->name,
                            ],
                            'created_at' => $reply->created_at->toDateTimeString(),
                            'created_human' => $reply->created_at->diffForHumans(),
                        ];
                    })
                ];
            })
        ]);
    }
}
