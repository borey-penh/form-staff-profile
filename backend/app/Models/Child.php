<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    protected $table = 'children';

    protected $fillable = ['user_id', 'name', 'dob', 'gender', 'status'];

    protected $casts = ['dob' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
