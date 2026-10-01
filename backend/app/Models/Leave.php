<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $fillable = [
        'type', 'start_date', 'end_date', 'days', 'reason', 'file_path',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
}
