<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = ['title', 'description', 'sections', 'quiz', 'pass_score', 'active'];

    protected $casts = [
        'sections' => 'array',
        'quiz' => 'array',
    ];

    public function assignments()
    {
        return $this->hasMany(TrainingAssignment::class);
    }
}
