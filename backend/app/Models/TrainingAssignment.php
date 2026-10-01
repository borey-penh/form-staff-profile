<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingAssignment extends Model
{
    protected $fillable = [
        'training_id', 'user_id', 'due_date', 'status',
        'progress', 'score', 'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
