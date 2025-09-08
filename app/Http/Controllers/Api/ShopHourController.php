<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoffeeShop;
use App\Models\ShopHour;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ShopHourController extends Controller
{
    // List hours for a shop
    public function index(CoffeeShop $shop)
    {
        $hours = ShopHour::query()
            ->where('shop_id', $shop->id)
            ->orderBy('day_of_week')
            ->orderBy('open_time')
            ->get();

        return response()->json($hours, 200);
    }

    // Create an hour entry for a shop
    public function store(Request $request, CoffeeShop $shop)
    {
        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'is_24h' => ['sometimes', 'boolean'],
            'open_time' => ['required_unless:is_24h,true', 'nullable', 'date_format:H:i:s'],
            'close_time' => ['required_unless:is_24h,true', 'nullable', 'date_format:H:i:s'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $hour = ShopHour::query()->create([
                'shop_id' => $shop->id,
                'day_of_week' => $validated['day_of_week'],
                'open_time' => $validated['open_time'] ?? null,
                'close_time' => $validated['close_time'] ?? null,
                'is_24h' => (bool) ($validated['is_24h'] ?? false),
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Duplicate or invalid hours entry'], 409);
        }

        return response()->json($hour, 201);
    }

    // Get specific hour entry by composite key
    public function show(CoffeeShop $shop, int $day, string $open)
    {
        $hour = ShopHour::query()
            ->where('shop_id', $shop->id)
            ->where('day_of_week', $day)
            ->where('open_time', $open)
            ->first();

        if (!$hour) {
            return response()->json(['message' => 'Hour entry not found'], 404);
        }

        return response()->json($hour, 200);
    }

    // Update specific hour entry by composite key
    public function update(Request $request, CoffeeShop $shop, int $day, string $open)
    {
        $validated = $request->validate([
            'day_of_week' => ['sometimes', 'integer', 'between:0,6'],
            'is_24h' => ['sometimes', 'boolean'],
            'open_time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'close_time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $query = ShopHour::query()
            ->where('shop_id', $shop->id)
            ->where('day_of_week', $day)
            ->where('open_time', $open);

        $existing = $query->first();
        if (!$existing) {
            return response()->json(['message' => 'Hour entry not found'], 404);
        }

        $update = $validated;
        try {
            $query->update($update);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Update would create duplicate or invalid entry'], 409);
        }

        $newDay = $update['day_of_week'] ?? $day;
        $newOpen = $update['open_time'] ?? $open;

        $fresh = ShopHour::query()
            ->where('shop_id', $shop->id)
            ->where('day_of_week', $newDay)
            ->where('open_time', $newOpen)
            ->first();

        return response()->json($fresh, 200);
    }

    // Delete hour entry
    public function destroy(CoffeeShop $shop, int $day, string $open)
    {
        $deleted = ShopHour::query()
            ->where('shop_id', $shop->id)
            ->where('day_of_week', $day)
            ->where('open_time', $open)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Hour entry not found'], 404);
        }

        return response()->noContent();
    }
}
