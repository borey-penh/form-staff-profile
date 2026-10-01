<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'purpose', 'department', 'required_date',
        'items', 'total', 'justification', 'file_path',
    ];

    protected $casts = ['required_date' => 'date', 'items' => 'array', 'total' => 'float'];
}
