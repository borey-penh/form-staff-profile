<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Spouse extends Model
{
    protected $fillable = ['user_id', 'name', 'occupation', 'phone'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
