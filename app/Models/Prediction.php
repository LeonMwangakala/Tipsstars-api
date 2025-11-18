<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipster_id',
        'booker_id',
        'title',
        'description',
        'image_url',
        'winning_slip_url',
        'betting_slip_url',
        'booking_codes',
        'odds_total',
        'kickoff_at',
        'kickend_at',
        'confidence_level',
        'is_premium',
        'status',
        'result_status',
        'result_notes',
        'result_updated_at',
        'result_updated_by',
        'created_by_admin',
        'admin_id',
        'publish_at',
        'lock_at',
    ];

    protected $casts = [
        'booking_codes' => 'array',
        'kickoff_at' => 'datetime',
        'kickend_at' => 'datetime',
        'publish_at' => 'datetime',
        'lock_at' => 'datetime',
        'result_updated_at' => 'datetime',
        'is_premium' => 'boolean',
        'created_by_admin' => 'boolean',
    ];

    /**
     * Get the tipster that owns the prediction
     */
    public function tipster()
    {
        return $this->belongsTo(User::class, 'tipster_id');
    }

    public function booker()
    {
        return $this->belongsTo(Booker::class);
    }

    /**
     * Get the admin who created the prediction (if created by admin)
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Scope to get published predictions
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to get free predictions
     */
    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    /**
     * Scope to get premium predictions
     */
    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    /**
     * Scope to get open predictions (published but not graded)
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'published')
                    ->whereIn('result_status', ['pending', null]);
    }

    /**
     * Scope to get predictions that need result updates (kickend_at + 180 minutes has passed)
     */
    public function scopeNeedsResultUpdate($query)
    {
        return $query->where('status', 'published')
                    ->whereIn('result_status', ['pending', null])
                    ->where('kickend_at', '<', now()->subMinutes(180));
    }

    /**
     * Check if prediction is locked
     */
    public function isLocked()
    {
        return $this->status === 'locked' || ($this->lock_at && $this->lock_at->isPast());
    }

    /**
     * Check if prediction can be edited
     */
    public function canBeEdited()
    {
        return !$this->isLocked() && $this->status !== 'graded';
    }

    /**
     * Check if prediction needs result update (kickend_at + 180 minutes has passed)
     */
    public function needsResultUpdate()
    {
        return $this->status === 'published' && 
               in_array($this->result_status, ['pending', null]) && 
               $this->kickend_at && 
               $this->kickend_at->addMinutes(180)->isPast();
    }

    /**
     * Check if prediction has been graded
     */
    public function isGraded()
    {
        return in_array($this->result_status, ['won', 'lost', 'void', 'refunded']);
    }

    /**
     * Update prediction result with winning slip
     */
    public function updateResult($resultStatus, $resultNotes = null, $winningSlipUrl = null)
    {
        $this->update([
            'result_status' => $resultStatus,
            'result_notes' => $resultNotes,
            'winning_slip_url' => $winningSlipUrl,
            'result_updated_at' => now(),
            'result_updated_by' => auth()->id(),
        ]);

        // Update tipster ratings after result update
        if (in_array($resultStatus, ['won', 'lost', 'void', 'refunded'])) {
            $this->tipster->updateRatings();
        }
    }

    /**
     * Check if tipster has open predictions that need result updates
     */
    public static function tipsterHasOpenPredictions($tipsterId)
    {
        return static::where('tipster_id', $tipsterId)
                    ->needsResultUpdate()
                    ->exists();
    }
}
