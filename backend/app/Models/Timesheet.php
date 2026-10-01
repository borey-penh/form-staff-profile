<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    protected $fillable = ['user_id', 'month', 'year', 'entries', 'total_hours'];

    protected $casts = ['entries' => 'array', 'total_hours' => 'float'];
}
