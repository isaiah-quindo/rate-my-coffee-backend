<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoffeeShop;
use App\Models\ShopPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopPhotoController extends Controller
{
    // List photos for a shop
    public function index(CoffeeShop $shop)
    {
        $photos = ShopPhoto::query()
            ->where('shop_id', $shop->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json($photos, 200);
    }

    // Create photo for a shop
    public function store(Request $request, CoffeeShop $shop)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:1024'],
            'is_cover' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'post_id' => [
                'nullable',
                'integer',
                Rule::exists('posts', 'id')->where(function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }),
            ],
        ]);

        $path = 'shops/' . $shop->id . '/' . now()->format('Y/m/d') . '/' . Str::uuid()->toString();
        $ext = $request->file('file')->getClientOriginalExtension();
        if ($ext) {
            $path .= '.' . strtolower($ext);
        }

        // Upload using configured S3-compatible disk
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        try {
            $putOk = $disk->put($path, file_get_contents($request->file('file')->getRealPath()), [
                'visibility' => 'public',
            ]);
        } catch (\Throwable $e) {
            Log::error('S3 upload threw exception', ['path' => $path, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Upload failed', 'error' => 'storage-exception'], 502);
        }
        if (!$putOk) {
            Log::warning('S3 upload failed', ['path' => $path]);
            return response()->json(['message' => 'Upload failed', 'error' => 'storage-put-false'], 502);
        }

        $publicUrl = $this->buildSupabasePublicUrl($path);

        if (!empty($validated['is_cover'])) {
            ShopPhoto::query()->where('shop_id', $shop->id)->update(['is_cover' => false]);
        }

        $photo = ShopPhoto::query()->create([
            'shop_id' => $shop->id,
            'post_id' => $validated['post_id'] ?? null,
            'url' => $publicUrl,
            'caption' => $validated['caption'] ?? null,
            'is_cover' => (bool) ($validated['is_cover'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return response()->json($photo, 201);
    }

    // Show a single photo by ID
    public function show(ShopPhoto $photo)
    {
        return response()->json($photo, 200);
    }

    // Update a photo
    public function update(Request $request, ShopPhoto $photo)
    {
        $validated = $request->validate([
            'file' => ['sometimes', 'required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'is_cover' => ['sometimes', 'nullable', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'post_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('posts', 'id')->where(function ($q) use ($photo) {
                    $q->where('shop_id', $photo->shop_id);
                }),
            ],
        ]);

        $updates = $validated;

        if (array_key_exists('is_cover', $validated) && $validated['is_cover']) {
            ShopPhoto::query()->where('shop_id', $photo->shop_id)->update(['is_cover' => false]);
        }

        if ($request->hasFile('file')) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('s3');

            // Delete old object (best-effort) by deriving key from URL
            $oldUrl = (string) $photo->url;
            $derivedKey = $this->deriveS3ObjectKeyFromUrl($oldUrl);
            if ($derivedKey) {
                $disk->delete($derivedKey);
            }

            $path = 'shops/' . $photo->shop_id . '/' . now()->format('Y/m/d') . '/' . Str::uuid()->toString();
            $ext = $request->file('file')->getClientOriginalExtension();
            if ($ext) {
                $path .= '.' . strtolower($ext);
            }

            try {
                $putOk = $disk->put($path, file_get_contents($request->file('file')->getRealPath()), [
                    'visibility' => 'public',
                ]);
            } catch (\Throwable $e) {
                Log::error('S3 upload (update) threw exception', ['path' => $path, 'error' => $e->getMessage()]);
                return response()->json(['message' => 'Upload failed', 'error' => 'storage-exception'], 502);
            }

            if (!$putOk) {
                Log::warning('S3 upload failed', ['path' => $path]);
                return response()->json(['message' => 'Upload failed', 'error' => 'storage-put-false'], 502);
            }

            $updates['url'] = $this->buildSupabasePublicUrl($path);
        }

        unset($updates['file']);

        $photo->fill($updates);
        $photo->save();

        return response()->json($photo->fresh(), 200);
    }

    // Delete a photo
    public function destroy(ShopPhoto $photo)
    {
        // Attempt to delete the file from S3-compatible storage
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        $oldUrl = (string) $photo->url;
        $derivedKey = $this->deriveS3ObjectKeyFromUrl($oldUrl);
        if ($derivedKey) {
            try {
                $disk->delete($derivedKey);
            } catch (\Throwable $e) {
                Log::warning('S3 delete threw exception', ['key' => $derivedKey, 'error' => $e->getMessage()]);
            }
        }

        $photo->delete();
        return response()->noContent();
    }
    private function deriveS3ObjectKeyFromUrl(string $url): ?string
    {
        $bucket = (string) (env('AWS_BUCKET') ?: env('AWS_BUCKET'));
        $endpoint = rtrim((string) env('AWS_ENDPOINT'), '/');
        $supabaseUrl = rtrim((string) env('AWS_URL'), '/');
        if ($bucket === '') {
            return null;
        }

        // https://vzjinygnxjnqbsqwyyjq.storage.supabase.co/storage/v1/s3

        // Supabase public URL pattern
        if ($supabaseUrl !== '') {
            $publicPrefix = $supabaseUrl . '/storage/v1/object/public/' . $bucket . '/';
            if (str_starts_with($url, $publicPrefix)) {
                return substr($url, strlen($publicPrefix));
            }
        }

        // Path-style: https://endpoint/bucket/key
        if ($endpoint !== '') {
            $pathStylePrefix = $endpoint . '/' . $bucket . '/';
            if (str_starts_with($url, $pathStylePrefix)) {
                return substr($url, strlen($pathStylePrefix));
            }
        }

        // Virtual-hosted-style: https://bucket.endpoint/key
        if ($endpoint !== '') {
            $endpointHost = preg_replace('~^https?://~', '', $endpoint);
            $virtualHostPrefix = 'https://' . $bucket . '.' . $endpointHost . '/';
            if (str_starts_with($url, $virtualHostPrefix)) {
                return substr($url, strlen($virtualHostPrefix));
            }
        }

        return null;
    }

    private function buildPublicUrlFromEnv(string $key): string
    {
        $bucket = (string) env('AWS_BUCKET');
        $endpoint = rtrim((string) env('AWS_ENDPOINT'), '/');
        if ($bucket && $endpoint) {
            return $endpoint . '/' . $bucket . '/' . ltrim($key, '/');
        }
        return $key;
    }

    private function buildSupabasePublicUrl(string $key): string
    {
        $bucket = (string) (env('AWS_BUCKET') ?: env('AWS_BUCKET', 'rate_my_coffee_images'));
        $supabaseUrl = rtrim((string) env('AWS_URL'), '/');
        if ($supabaseUrl !== '' && $bucket !== '') {
            return $supabaseUrl . '/storage/v1/object/public/' . $bucket . '/' . ltrim($key, '/');
        }
        return $this->buildPublicUrlFromEnv($key);
    }
}
