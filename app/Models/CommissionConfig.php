<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'commission_rate',
        'description',
        'is_active',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get subscriptions using this commission config
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the default commission config
     */
    public static function getDefault()
    {
        return static::where('name', 'default')->first();
    }

    /**
     * Get active commission configs
     */
    public static function getActive()
    {
        return static::where('is_active', true)->get();
    }
}
