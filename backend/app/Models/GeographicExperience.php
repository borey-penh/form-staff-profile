<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeographicExperience extends Model
{
    protected $fillable = ['user_id', 'country', 'province'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
