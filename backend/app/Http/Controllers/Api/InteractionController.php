<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Post;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    /**
     * POST /api/interactions
     *
     * Log a user interaction (view, reply, or reaction) against a post.
     * This data feeds the relationship-depth signal in the feed ranking algorithm.
     *
     * For 'view' interactions, we use updateOrCreate to avoid duplicate views per user per post.
     * Reactions and replies can be logged multiple times (e.g., re-reactions not supported here,
     * but replies can be multiple).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'type'    => 'required|string|in:view,reply,reaction',
        ]);

        $user = $request->user();

        if ($validated['type'] === 'view') {
            // Deduplicate views: a user "views" a post once
            [$interaction, $created] = [
                Interaction::firstOrCreate([
                    'user_id' => $user->id,
                    'post_id' => $validated['post_id'],
                    'type'    => 'view',
                ]),
                false,
            ];
            $created = ! $interaction->wasRecentlyCreated
                ? false
                : true;
        } else {
            $interaction = Interaction::create([
                'user_id' => $user->id,
                'post_id' => $validated['post_id'],
                'type'    => $validated['type'],
            ]);
            $created = true;
        }

        // Return the total interaction counts for the post (useful for UI update)
        $post = Post::withCount([
            'interactions as reaction_count' => fn($q) => $q->where('type', 'reaction'),
            'interactions as view_count'     => fn($q) => $q->where('type', 'view'),
        ])->find($validated['post_id']);

        return response()->json([
            'status'         => 'logged',
            'interaction'    => $interaction,
            'reaction_count' => $post->reaction_count,
            'view_count'     => $post->view_count,
        ], $created ? 201 : 200);
    }
}
