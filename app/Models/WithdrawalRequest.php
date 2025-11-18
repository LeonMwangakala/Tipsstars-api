<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipster_id',
        'amount',
        'status',
        'requested_at',
        'paid_at',
        'admin_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the tipster who made the request
     */
    public function tipster()
    {
        return $this->belongsTo(User::class, 'tipster_id');
    }

    /**
     * Get the admin who processed the request
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Scope for pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for paid requests
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Check if request is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request is paid
     */
    public function isPaid()
    {
        return $this->status === 'paid';
    }

    /**
     * Check if request is rejected
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Mark request as paid
     */
    public function markAsPaid($adminId = null, $notes = null)
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'admin_id' => $adminId,
            'notes' => $notes,
        ]);
    }

    /**
     * Mark request as rejected
     */
    public function markAsRejected($adminId = null, $notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'admin_id' => $adminId,
            'notes' => $notes,
        ]);
    }
} 