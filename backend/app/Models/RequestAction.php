<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestAction extends Model
{
    protected $fillable = ['request_id', 'user_id', 'action', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
