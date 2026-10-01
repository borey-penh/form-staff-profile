<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Qualification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'institution', 'field',
        'start_date', 'end_date', 'description', 'file_path',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
