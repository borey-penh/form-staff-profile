<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    protected $fillable = [
        'date', 'start_time', 'end_time', 'hours', 'reason', 'supervisor_id',
    ];

    protected $casts = ['date' => 'date'];
}
