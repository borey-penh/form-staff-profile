<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = [
        'user_id', 'full_name', 'dob', 'id_number',
        'relationship', 'contact', 'address', 'share',
    ];

    protected $casts = ['dob' => 'date', 'share' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
