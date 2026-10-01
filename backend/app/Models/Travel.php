<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Travel extends Model
{
    protected $fillable = [
        'purpose', 'destination', 'start_date', 'end_date',
        'transport', 'accommodation', 'costs', 'total_cost', 'file_path',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'costs' => 'array',
        'total_cost' => 'float',
    ];
}
