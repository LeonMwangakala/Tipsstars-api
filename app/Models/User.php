<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone_number',
        'role',
        'password',
        'profile_image',
        'id_document',
        'status',
        'admin_notes',
        'commission_config_id',
        'weekly_subscription_amount',
        'monthly_subscription_amount',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'weekly_subscription_amount' => 'decimal:2',
            'monthly_subscription_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the user's profile image URL or default avatar
     */
    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image) {
            return $this->profile_image; // Return base64 data
        }
        return null; // Return null for default avatar handling in frontend
    }

    /**
     * Check if user has a profile image
     */
    public function hasProfileImage()
    {
        return !empty($this->profile_image);
    }

    /**
     * Get predictions if user is a tipster
     */
    public function predictions()
    {
        return $this->hasMany(Prediction::class, 'tipster_id');
    }

    /**
     * Get user's subscriptions as a customer
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    /**
     * Get subscriptions to this tipster
     */
    public function tipsterSubscriptions()
    {
        return $this->hasMany(Subscription::class, 'tipster_id');
    }

    /**
     * Get user's payment transactions
     */
    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Check if user has specific role
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Check if user is tipster
     */
    public function isTipster()
    {
        return $this->role === 'tipster';
    }

    /**
     * Check if user is customer
     */
    public function isCustomer()
    {
        return $this->role === 'customer';
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if user is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if user is rejected
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if tipster has uploaded ID document
     */
    public function hasIdDocument()
    {
        return !empty($this->id_document);
    }

    /**
     * Get tipster rating (one-to-one relationship)
     */
    public function tipsterRating()
    {
        return $this->hasOne(TipsterRating::class, 'tipster_id');
    }

    /**
     * Update tipster ratings
     */
    public function updateRatings()
    {
        if (!$this->isTipster()) {
            return;
        }

        return TipsterRating::updateRatingsForTipster($this->id);
    }

    /**
     * Get withdrawal requests for this tipster
     */
    public function withdrawalRequests()
    {
        return $this->hasMany(WithdrawalRequest::class, 'tipster_id');
    }

    /**
     * Get withdrawal requests processed by this admin
     */
    public function processedWithdrawals()
    {
        return $this->hasMany(WithdrawalRequest::class, 'admin_id');
    }

    /**
     * Calculate tipster's total earnings
     */
    public function getTotalEarnings()
    {
        if (!$this->isTipster()) {
            return 0;
        }

        return $this->tipsterSubscriptions()
            ->where('status', 'active')
            ->sum('commission_amount');
    }

    /**
     * Calculate tipster's available balance (earnings - pending withdrawals)
     */
    public function getAvailableBalance()
    {
        if (!$this->isTipster()) {
            return 0;
        }

        $totalEarnings = $this->getTotalEarnings();
        $pendingWithdrawals = $this->withdrawalRequests()
            ->whereIn('status', ['pending', 'paid'])
            ->sum('amount');

        return $totalEarnings - $pendingWithdrawals;
    }

    /**
     * Check if tipster can request withdrawal
     */
    public function canRequestWithdrawal($amount)
    {
        if (!$this->isTipster()) {
            return false;
        }

        $availableBalance = $this->getAvailableBalance();
        $minWithdrawalLimit = config('app.min_withdrawal_limit', 1000); // Default 1000 TZS

        return $availableBalance >= $amount && $amount >= $minWithdrawalLimit;
    }

    public function commissionConfig()
    {
        return $this->belongsTo(CommissionConfig::class, 'commission_config_id');
    }
}
