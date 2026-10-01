<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileChangeRequest extends Model
{
    protected $table = 'profile_change_requests';

    protected $fillable = [
        'user_id', 'field', 'current_value', 'requested_value',
        'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
