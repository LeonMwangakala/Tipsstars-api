<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tipster_id',
        'plan_type',
        'price',
        'currency',
        'start_at',
        'end_at',
        'status',
        'commission_rate',
        'commission_amount',
        'tipster_earnings',
        'commission_config_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'tipster_earnings' => 'decimal:2',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * Get the customer (user) that owns the subscription
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the tipster for this subscription
     */
    public function tipster()
    {
        return $this->belongsTo(User::class, 'tipster_id');
    }

    /**
     * Get payment transactions for this subscription
     */
    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Get the commission config for this subscription
     */
    public function commissionConfig()
    {
        return $this->belongsTo(CommissionConfig::class);
    }

    /**
     * Calculate commission and tipster earnings
     */
    public function calculateCommission()
    {
        $commissionRate = $this->commission_rate ?? 15.00; // Default 15%
        $this->commission_amount = ($this->price * $commissionRate) / 100;
        $this->tipster_earnings = $this->price - $this->commission_amount;
        return $this;
    }

    /**
     * Get total earnings for a tipster
     */
    public static function getTipsterTotalEarnings($tipsterId)
    {
        return static::where('tipster_id', $tipsterId)
                    ->where('status', 'active')
                    ->sum('tipster_earnings');
    }

    /**
     * Get total commission collected
     */
    public static function getTotalCommission()
    {
        return static::where('status', 'active')->sum('commission_amount');
    }

    /**
     * Scope to get active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('start_at', '<=', now())
                    ->where('end_at', '>=', now());
    }

    /**
     * Check if subscription is active
     */
    public function isActive()
    {
        return $this->status === 'active' && 
               $this->start_at <= now() && 
               $this->end_at >= now();
    }

    /**
     * Check if subscription has expired
     */
    public function hasExpired()
    {
        return $this->end_at < now();
    }
}
