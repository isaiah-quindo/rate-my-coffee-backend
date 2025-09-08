<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $fillable = [
        'shop_id',
        'author_user_id',
        'is_anonymous',
        'body',
        'ratings',
        'visited_at',
        'spend_php',
        'ordered_items',
        'taste_profile',
        'seat_context',
        'internet_speed_mbps',
        'status',
        'flagged_count',
        'admin_notes',
        'deleted_at',
        'ip_hash',
        'user_agent',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'ratings' => 'array',
        'taste_profile' => 'array',
        'visited_at' => 'date',
        'spend_php' => 'decimal:2',
        'internet_speed_mbps' => 'decimal:2',
        'flagged_count' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(CoffeeShop::class, 'shop_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
