<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoffeeShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class CoffeeShopController extends Controller
{
    public function store(Request $request)
    {
        // Get and parse JSON data manually to handle potential formatting issues
        $rawContent = $request->getContent();
        $data = json_decode($rawContent, true);

        // Validate the data
        $validator = validator($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('coffee_shops', 'slug')],
            'description' => ['nullable', 'string', 'max:1024'],
            'status' => ['nullable', Rule::in(['active', 'temporarily_closed', 'permanently_closed', 'draft', 'pending_verification'])],
            'country_code' => ['nullable', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city_municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'street_address' => ['nullable', 'string', 'max:1024'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'instagram_handle' => ['nullable', 'string', 'max:255'],
            'google_maps_url' => ['nullable', 'url', 'max:2048'],
            'price' => ['nullable', Rule::in(['₱', '₱₱', '₱₱₱'])],
            'accepts_gcash' => ['nullable', 'boolean'],
            'accepts_cards' => ['nullable', 'boolean'],
            'has_wifi' => ['nullable', 'boolean'],
            'has_outlets' => ['nullable', 'boolean'],
            'outdoor_seating' => ['nullable', 'boolean'],
            'parking_available' => ['nullable', 'boolean'],
            'wheelchair_accessible' => ['nullable', 'boolean'],
            'pet_friendly' => ['nullable', 'boolean'],
            'vegan_options' => ['nullable', 'boolean'],
            'manual_brew' => ['nullable', 'boolean'],
            'decaf_available' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'claimed_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'claiming_notes' => ['nullable', 'string'],
        ]);

        $validated = $validator->validate();

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $slug = $this->ensureUniqueSlug($slug);

        $attributes = array_merge(
            $validated,
            [
                'slug' => $slug,
                'status' => $validated['status'] ?? 'active',
                'country_code' => $validated['country_code'] ?? 'PH',
            ]
        );

        // Handle Postgres text[] for tags: convert PHP array to array literal string
        if (array_key_exists('tags', $attributes) && is_array($attributes['tags'])) {
            $encoded = collect($attributes['tags'])
                ->map(function ($t) {
                    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $t);
                    return '"' . $escaped . '"';
                })->implode(',');
            $attributes['tags'] = '{' . $encoded . '}';
        }

        $shop = CoffeeShop::query()->create($attributes);

        return response()->json($shop->fresh(), 201);
    }

    public function update(Request $request, CoffeeShop $shop)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('coffee_shops', 'slug')->ignore($shop->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'temporarily_closed', 'permanently_closed', 'draft', 'pending_verification'])],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'province' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city_municipality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay' => ['sometimes', 'nullable', 'string', 'max:255'],
            'street_address' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'facebook_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'instagram_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'google_maps_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'price' => ['sometimes', 'nullable', Rule::in(['₱', '₱₱', '₱₱₱'])],
            'accepts_gcash' => ['sometimes', 'nullable', 'boolean'],
            'accepts_cards' => ['sometimes', 'nullable', 'boolean'],
            'has_wifi' => ['sometimes', 'nullable', 'boolean'],
            'has_outlets' => ['sometimes', 'nullable', 'boolean'],
            'outdoor_seating' => ['sometimes', 'nullable', 'boolean'],
            'parking_available' => ['sometimes', 'nullable', 'boolean'],
            'wheelchair_accessible' => ['sometimes', 'nullable', 'boolean'],
            'pet_friendly' => ['sometimes', 'nullable', 'boolean'],
            'vegan_options' => ['sometimes', 'nullable', 'boolean'],
            'manual_brew' => ['sometimes', 'nullable', 'boolean'],
            'decaf_available' => ['sometimes', 'nullable', 'boolean'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'claimed_by_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'claiming_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if (array_key_exists('slug', $validated) && $validated['slug']) {
            $validated['slug'] = $this->ensureUniqueSlug($validated['slug'], $shop->id);
        }

        if (array_key_exists('tags', $validated) && is_array($validated['tags'])) {
            $encoded = collect($validated['tags'])
                ->map(function ($t) {
                    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $t);
                    return '"' . $escaped . '"';
                })->implode(',');
            $validated['tags'] = '{' . $encoded . '}';
        }

        $shop->fill($validated);
        $shop->save();

        return response()->json($shop->fresh(), 200);
    }


    // Get Coffee Shop by ID
    public function show(CoffeeShop $shop)
    {
        return response()->json($shop, 200);
    }

    // Get Coffee Shop by Slug
    public function showBySlug(Request $request, string $slug)
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT
                cs.id,
                cs.name,
                cs.slug,
                cs.status,
                cs.description,
                cs.country_code,
                cs.region,
                cs.province,
                cs.city_municipality,
                cs.barangay,
                cs.street_address,
                cs.postcode,
                cs.latitude,
                cs.longitude,
                cs.phone,
                cs.email,
                cs.website_url,
                cs.facebook_url,
                cs.instagram_handle,
                cs.google_maps_url,
                cs.price,
                cs.accepts_gcash,
                cs.accepts_cards,
                cs.has_wifi,
                cs.has_outlets,
                cs.outdoor_seating,
                cs.parking_available,
                cs.wheelchair_accessible,
                cs.pet_friendly,
                cs.vegan_options,
                cs.manual_brew,
                cs.decaf_available,
                cs.tags,
                cs.claimed_by_user_id,
                cs.claiming_notes,
                cs.rating_overall_cache,
                cs.rating_count_cache,
                cs.created_at,
                cs.updated_at,
                COALESCE(
                    (
                        SELECT jsonb_agg(h ORDER BY h.day_of_week, h.open_time)
                        FROM (
                            SELECT day_of_week, open_time, close_time, is_24h, notes
                            FROM shop_hours sh
                            WHERE sh.shop_id = cs.id
                            ORDER BY day_of_week, open_time
                        ) h
                    ), '[]'::jsonb
                ) AS hours,
                COALESCE(
                    (
                        SELECT jsonb_agg(p ORDER BY p.sort_order)
                        FROM (
                            SELECT id, post_id, url, caption, is_cover, sort_order, created_at
                            FROM shop_photos sp
                            WHERE sp.shop_id = cs.id
                            ORDER BY sort_order
                        ) p
                    ), '[]'::jsonb
                ) AS photos,
                COALESCE(
                    (
                        SELECT to_jsonb(x)
                        FROM (
                            SELECT id, post_id, url, caption, is_cover, sort_order, created_at
                            FROM shop_photos sp
                            WHERE sp.shop_id = cs.id AND sp.is_cover = TRUE
                            ORDER BY sp.sort_order, sp.id
                            LIMIT 1
                        ) x
                    ), 'null'::jsonb
                ) AS cover_photo
                -- posts will be paginated separately
            FROM coffee_shops cs
            WHERE cs.slug = ?
            LIMIT 1
        SQL, [$slug]);

        if (!$row) {
            return response()->json(['message' => 'Coffee shop not found'], 404);
        }

        $shop = (array) $row;
        $shop['hours'] = isset($row->hours) ? json_decode((string) $row->hours, true) : [];
        $shop['photos'] = isset($row->photos) ? json_decode((string) $row->photos, true) : [];
        $shop['cover_photo'] = isset($row->cover_photo) && $row->cover_photo !== null ? json_decode((string) $row->cover_photo, true) : null;

        // Paginate posts
        $perPage = (int) $request->query('posts_per_page', 5);
        $perPage = max(1, min(50, $perPage));
        $page = (int) $request->query('posts_page', 1);

        $posts = Post::query()
            ->where('shop_id', $shop['id'])
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'posts_page', $page);

        $shop['posts'] = $posts->items();
        $shop['posts_total'] = (int) $posts->total();
        $shop['posts_pagination'] = [
            'current_page' => $posts->currentPage(),
            'per_page' => $posts->perPage(),
            'total' => $posts->total(),
            'last_page' => $posts->lastPage(),
            'next_page_url' => $posts->nextPageUrl(),
            'prev_page_url' => $posts->previousPageUrl(),
        ];

        return response()->json($shop, 200);
    }

    // Get All Coffee Shops
    public function index(Request $request)
    {
        $query = CoffeeShop::query()
            ->with([
                'coverPhoto',
            ]);

        // Dynamic filters from query string
        $columns = Schema::getColumnListing('coffee_shops'); // whitelist
        $ops = [
            '__lt' => '<',
            '__lte' => '<=',
            '__gt' => '>',
            '__gte' => '>=',
            '__ne' => '!=',
            '__like' => 'like',
            '__ilike' => 'ilike',
            '__in' => 'in',
            '__nin' => 'not in',
        ];

        foreach ($request->query() as $key => $value) {
            if (in_array($key, ['page', 'per_page', 'sort', 'dir', 'q'])) continue;

            $suffix = null;
            $field = $key;
            $op = '=';
            foreach ($ops as $sfx => $sql) {
                if (str_ends_with($key, $sfx)) {
                    $suffix = $sfx;
                    $field = substr($key, 0, -strlen($sfx));
                    $op = $sql;
                    break;
                }
            }
            if (!in_array($field, $columns)) continue;

            if (in_array($op, ['like', 'ilike'])) {
                $query->where($field, $op, '%' . $value . '%');
            } elseif (in_array($op, ['in', 'not in'])) {
                $vals = is_array($value) ? $value : explode(',', (string)$value);
                $op === 'in' ? $query->whereIn($field, $vals) : $query->whereNotIn($field, $vals);
            } else {
                $query->where($field, $op, $value);
            }
        }

        // Special: tags array contains
        if ($request->filled('tags')) {
            $vals = (array) $request->input('tags');
            $arr = '{' . collect($vals)->map(fn($t) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$t) . '"')->implode(',') . '}';
            $query->whereRaw('tags @> ?', [$arr]);
        }

        // Optional free-text search
        if ($request->filled('q')) {
            $q = (string) $request->query('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'ilike', "%$q%")
                    ->orWhere('city_municipality', 'ilike', "%$q%")
                    ->orWhere('province', 'ilike', "%$q%");
            });
        }

        // Sort + paginate (your existing block)
        $sort = $request->string('sort', 'created_at');
        $dir  = strtolower((string)$request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (!in_array($sort, ['created_at', 'name', 'rating_overall_cache'])) $sort = 'created_at';
        $query->orderBy($sort, $dir);

        $perPage = (int) $request->query('per_page', 15);
        return response()->json($query->paginate(max(1, min(100, $perPage))));
    }

    // Distinct city/province pairs used in coffee_shops
    public function locations(Request $request)
    {
        $includeEmpty = filter_var($request->query('include_empty', false), FILTER_VALIDATE_BOOLEAN);

        $query = DB::table('coffee_shops')
            ->select('city_municipality', 'province')
            ->distinct();

        if (!$includeEmpty) {
            $query->whereNotNull('city_municipality')
                ->where('city_municipality', '<>', '')
                ->whereNotNull('province')
                ->where('province', '<>', '');
        }

        if ($request->filled('q')) {
            $q = (string) $request->query('q');
            $query->where(function ($w) use ($q) {
                $w->where('city_municipality', 'ilike', "%$q%")
                    ->orWhere('province', 'ilike', "%$q%");
            });
        }

        $rows = $query
            ->orderBy('province')
            ->orderBy('city_municipality')
            ->get();

        return response()->json($rows, 200);
    }

    private function ensureUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $i = 2;
        while (CoffeeShop::query()->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = Str::limit($baseSlug, 240, '') . '-' . $i;
            $i++;
        }
        return $slug;
    }
}
