<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguageSkill extends Model
{
    protected $fillable = [
        'user_id', 'language', 'is_mother_tongue',
        'reading', 'writing', 'speaking', 'understanding',
    ];

    protected $casts = ['is_mother_tongue' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
