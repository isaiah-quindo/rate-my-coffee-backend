<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopHour extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'shop_hours';

    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'shop_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_24h',
        'notes',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_24h' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(CoffeeShop::class, 'shop_id');
    }
}
