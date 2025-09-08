<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoffeeShop extends Model
{
    use HasFactory;

    protected $table = 'coffee_shops';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'country_code',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'street_address',
        'postcode',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website_url',
        'facebook_url',
        'instagram_handle',
        'google_maps_url',
        'description',
        'price',
        'accepts_gcash',
        'accepts_cards',
        'has_wifi',
        'has_outlets',
        'outdoor_seating',
        'parking_available',
        'wheelchair_accessible',
        'pet_friendly',
        'vegan_options',
        'manual_brew',
        'decaf_available',
        'tags',
        'claimed_by_user_id',
        'claiming_notes',
        'rating_overall_cache',
        'rating_count_cache',
    ];

    protected $casts = [
        'accepts_gcash' => 'boolean',
        'accepts_cards' => 'boolean',
        'has_wifi' => 'boolean',
        'has_outlets' => 'boolean',
        'outdoor_seating' => 'boolean',
        'parking_available' => 'boolean',
        'wheelchair_accessible' => 'boolean',
        'pet_friendly' => 'boolean',
        'vegan_options' => 'boolean',
        'manual_brew' => 'boolean',
        'decaf_available' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function hours()
    {
        return $this->hasMany(ShopHour::class, 'shop_id');
    }

    public function photos()
    {
        return $this->hasMany(ShopPhoto::class, 'shop_id')->orderBy('sort_order');
    }

    public function coverPhoto()
    {
        return $this->hasOne(ShopPhoto::class, 'shop_id')->where('is_cover', true);
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'shop_id');
    }

    // public function firstPhoto()
    // {
    //     return $this->hasOne(ShopPhoto::class, 'shop_id')->orderBy('sort_order')->orderBy('id');
    // }

    public function getTagsAttribute($value)
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $trimmed = trim((string) $value, '{}');
        if ($trimmed === '') {
            return [];
        }
        $items = str_getcsv($trimmed, ',', '"', '\\');
        return array_map(function ($s) {
            return stripcslashes($s);
        }, $items);
    }
}
