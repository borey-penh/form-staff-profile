<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRequest extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'user_id', 'type', 'status', 'current_handler_id',
        'related_id', 'submitted_at', 'decided_at',
    ];

    protected $casts = ['submitted_at' => 'datetime', 'decided_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actions()
    {
        return $this->hasMany(RequestAction::class, 'request_id')->orderBy('created_at');
    }
}
