<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Post;

class ShopPhoto extends Model
{
    use HasFactory;

    protected $table = 'shop_photos';

    public $timestamps = false; // created_at is set by DB default

    protected $fillable = [
        'shop_id',
        'post_id',
        'url',
        'caption',
        'is_cover',
        'sort_order',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function shop()
    {
        return $this->belongsTo(CoffeeShop::class, 'shop_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
