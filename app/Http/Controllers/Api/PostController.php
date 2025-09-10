<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoffeeShop;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    // Create a post for a shop
    public function store(Request $request, CoffeeShop $shop)
    {
        $validated = $request->validate([
            'is_anonymous' => ['nullable', 'boolean'],
            'body' => ['nullable', 'string'],
            'ratings' => ['required', 'array'],
            'ratings.*' => ['numeric', 'between:0.5,5'],
            'visited_at' => ['nullable', 'date'],
            'spend_php' => ['nullable', 'numeric', 'min:0'],
            'ordered_items' => ['nullable', 'array'],
            'ordered_items.*' => ['string', 'max:255'],
            'taste_profile' => ['nullable', 'array'],
            'seat_context' => ['nullable', 'string'],
            'internet_speed_mbps' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'flagged', 'removed'])],
        ]);

        // Automatically set the author from authenticated user
        $attributes = array_merge([
            'shop_id' => $shop->id,
            'author_user_id' => $request->user()->id,
        ], $validated);

        // Bind ordered_items as native Postgres text[] using ARRAY[...]::text[]
        if (array_key_exists('ordered_items', $attributes) && is_array($attributes['ordered_items'])) {
            $elements = collect($attributes['ordered_items'])->map(function ($t) {
                $s = (string) $t;
                $s = str_replace("'", "''", $s); // escape single quotes
                return "'" . $s . "'";
            })->implode(',');
            $attributes['ordered_items'] = DB::raw('ARRAY[' . $elements . ']::text[]');
        }

        $post = Post::query()->create($attributes);

        return response()->json($post->fresh(), 201);
    }

    // List posts for a shop (paginated)
    public function indexByShop(Request $request, CoffeeShop $shop)
    {
        $query = Post::query()->where('shop_id', $shop->id);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $query->orderBy('created_at', 'desc');
        $perPage = (int) $request->query('per_page', 15);
        return response()->json($query->paginate(max(1, min(100, $perPage))))
            ->setStatusCode(200);
    }

    // Show a post by ID
    public function show(Post $post)
    {
        return response()->json($post, 200);
    }

    // Update a post by ID
    public function update(Request $request, Post $post)
    {
        // Check if user can update this post (must be the author)
        if ($post->author_user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized to update this post'], 403);
        }

        $validated = $request->validate([
            'is_anonymous' => ['sometimes', 'nullable', 'boolean'],
            'body' => ['sometimes', 'nullable', 'string'],
            'ratings' => ['sometimes', 'required', 'array'],
            'ratings.*' => ['numeric', 'between:0.5,5'],
            'visited_at' => ['sometimes', 'nullable', 'date'],
            'spend_php' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'ordered_items' => ['sometimes', 'nullable', 'array'],
            'ordered_items.*' => ['string', 'max:255'],
            'taste_profile' => ['sometimes', 'nullable', 'array'],
            'seat_context' => ['sometimes', 'nullable', 'string'],
            'internet_speed_mbps' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'nullable', Rule::in(['draft', 'published', 'flagged', 'removed'])],
        ]);

        if (array_key_exists('ordered_items', $validated) && is_array($validated['ordered_items'])) {
            $elements = collect($validated['ordered_items'])->map(function ($t) {
                $s = (string) $t;
                $s = str_replace("'", "''", $s);
                return "'" . $s . "'";
            })->implode(',');
            $validated['ordered_items'] = DB::raw('ARRAY[' . $elements . ']::text[]');
        }

        $post->fill($validated);
        $post->save();

        return response()->json($post->fresh(), 200);
    }

    // Delete a post by ID
    public function destroy(Request $request, Post $post)
    {
        // Check if user can delete this post (must be the author)
        if ($post->author_user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized to delete this post'], 403);
        }

        $post->delete();
        return response()->noContent();
    }

    // Get authenticated user's posts (paginated)
    public function indexByAuthUser(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $query = Post::query()
                ->where('author_user_id', $user->id)
                ->with(['shop' => function ($query) {
                    $query->select('id', 'name', 'slug', 'city_municipality', 'province');
                }])
                ->orderBy('created_at', 'desc');

            // Filters
            if ($request->filled('is_anonymous')) {
                $query->where(
                    'is_anonymous',
                    filter_var($request->input('is_anonymous'), FILTER_VALIDATE_BOOLEAN)
                );
            }

            if ($request->filled('status')) {
                $query->where('status', $request->string('status'));
            }

            // Pagination
            $perPage = (int) $request->input('posts_per_page', 15);
            $perPage = max(1, min(100, $perPage)); // clamp between 1 and 100

            $posts = $query->paginate($perPage, ['*'], 'posts_page');

            // ✅ Return a clean structure
            return response()->json([
                'data'         => $posts->items(),     // just the posts
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in indexByAuthUser: ' . $e->getMessage(), [
                'user_id'   => $request->user()?->id,
                'exception' => $e
            ]);

            return response()->json([
                'error'   => 'Failed to fetch posts',
                'message' => app()->environment('local')
                    ? $e->getMessage()
                    : 'An unexpected error occurred'
            ], 500);
        }
    }
}
