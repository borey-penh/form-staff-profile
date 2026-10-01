<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'user_id', 'type', 'position', 'department',
        'start_date', 'end_date', 'salary', 'file_path', 'status',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'salary' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
